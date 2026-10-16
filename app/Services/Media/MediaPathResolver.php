<?php

namespace App\Services\Media;

use App\Models\Athlete;
use App\Models\EventEdition;
use App\Models\MediaFile;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * THE folder convention of the media disk (spec §62-§64). Returns paths
 * RELATIVE to the media disk — the DB stores exactly these, never an
 * absolute NAS path.
 *
 *   athletes/{athlete_uuid}--{slug-del-nombre}/
 *       profile/ legacy/ gear/ posts/ support/
 *       events/{event_uuid}/photos|videos|tracking|support/
 *   products/{product_uuid}/images|videos|models3d/
 *
 * The uuid is the identity; the name slug is only for humans browsing the
 * NAS. Renaming an athlete NEVER moves files: once an athlete has any
 * stored media, the existing folder (found by its `{uuid}--` prefix in
 * `media_files.directory`) keeps being used, even if the slug is now stale.
 * A new slug only appears for an athlete with no stored media yet.
 */
class MediaPathResolver
{
    public const ATHLETE_SECTIONS = ['profile', 'legacy', 'gear', 'posts', 'support'];

    public const EVENT_KINDS = ['photos', 'videos', 'tracking', 'support'];

    public const PRODUCT_KINDS = ['images', 'videos', 'models3d'];

    private const SLUG_MAX = 40;

    /** @var array<string, string> athlete uuid => folder (per request/job) */
    private array $athleteFolders = [];

    public function athleteRoot(Athlete $athlete): string
    {
        $uuid = $this->assertUuid((string) $athlete->uuid);

        return $this->athleteFolders[$uuid] ??= $this->existingAthleteRoot($uuid)
            ?? 'athletes/'.$uuid.'--'.$this->slug((string) $athlete->full_name);
    }

    public function athleteSection(Athlete $athlete, string $section): string
    {
        $this->assertOneOf($section, self::ATHLETE_SECTIONS);

        return $this->athleteRoot($athlete).'/'.$section;
    }

    public function athleteEvent(Athlete $athlete, EventEdition|string $event, string $kind): string
    {
        $this->assertOneOf($kind, self::EVENT_KINDS);
        $eventUuid = $this->assertUuid($event instanceof EventEdition ? $this->eventUuid($event) : $event);

        return $this->athleteRoot($athlete).'/events/'.$eventUuid.'/'.$kind;
    }

    /**
     * Folder for content a User owns: their Athlete folder when they have
     * one, else `users/{id}/{section}` (internal only — never in a URL).
     */
    public function forUser(User $user, string $section): string
    {
        $this->assertOneOf($section, self::ATHLETE_SECTIONS);
        $athlete = $user->athlete()->first();

        return $athlete !== null
            ? $this->athleteSection($athlete, $section)
            : 'users/'.(int) $user->id.'/'.$section;
    }

    public function product(Product|string $product, string $kind): string
    {
        $this->assertOneOf($kind, self::PRODUCT_KINDS);
        $uuid = $this->assertUuid($product instanceof Product ? (string) $product->uuid : $product);

        return 'products/'.$uuid.'/'.$kind;
    }

    /**
     * Readable, filesystem-safe slug: ASCII, lowercase, dashes, ≤ 40 chars,
     * never empty, never `.`/`..`/slashes.
     */
    public function slug(string $name): string
    {
        $slug = Str::slug(Str::ascii($name));
        $slug = trim(substr($slug, 0, self::SLUG_MAX), '-');

        return $slug !== '' ? $slug : 'atleta';
    }

    /**
     * Events have no public uuid historically — assign one lazily, race-
     * safe (only the first writer wins).
     */
    public function eventUuid(EventEdition $edition): string
    {
        $uuid = $edition->getAttribute('uuid');

        if (is_string($uuid) && $uuid !== '') {
            return $uuid;
        }

        EventEdition::query()->whereKey($edition->getKey())->whereNull('uuid')
            ->update(['uuid' => (string) Str::uuid()]);

        $uuid = (string) EventEdition::query()->whereKey($edition->getKey())->value('uuid');
        $edition->setAttribute('uuid', $uuid);
        $edition->syncOriginalAttribute('uuid');

        return $uuid;
    }

    private function existingAthleteRoot(string $uuid): ?string
    {
        $directory = MediaFile::query()
            ->where('directory', 'like', 'athletes/'.$uuid.'--%')
            ->orderBy('id')
            ->value('directory');

        if (! is_string($directory)) {
            return null;
        }

        return implode('/', array_slice(explode('/', $directory), 0, 2));
    }

    private function assertUuid(string $value): string
    {
        if (! Str::isUuid($value)) {
            throw new InvalidArgumentException('Media folders require a valid uuid.');
        }

        return strtolower($value);
    }

    /**
     * @param  list<string>  $allowed
     */
    private function assertOneOf(string $value, array $allowed): void
    {
        if (! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("Unknown media folder [{$value}].");
        }
    }
}
