<?php

use App\Actions\LegacyTags\ActivateTag;
use App\Enums\LegacyTagStatus;
use App\Enums\StoryVisibility;
use App\Models\LegacyStory;
use App\Models\LegacyTag;
use App\Models\User;
use Illuminate\Http\UploadedFile;

require_once __DIR__.'/helpers.php';

/*
| Fixtures for the activation / story / API tests — required with
| require_once so each function is declared once per worker.
*/

/** A sold & delivered tag, ready to be activated by its buyer. */
function deliveredTag(array $overrides = []): LegacyTag
{
    return LegacyTag::factory()->create([
        'product_variant_id' => tagVariant()->id,
        'status' => LegacyTagStatus::Delivered,
        'sold_at' => now(),
        'delivered_at' => now(),
        ...$overrides,
    ]);
}

/**
 * An activated tag + its story, owned by `$owner`.
 *
 * @return array{0: LegacyStory, 1: User, 2: LegacyTag}
 */
function ownedStory(StoryVisibility $visibility = StoryVisibility::Unlisted, ?User $owner = null): array
{
    $owner ??= User::factory()->create();
    $tag = deliveredTag();
    $story = app(ActivateTag::class)->handle($tag, $owner);
    $story->forceFill(['visibility' => $visibility, 'title' => 'Mi medio maratón', 'completed_at' => now()])->save();

    return [$story->fresh(), $owner, $tag->fresh()];
}

/**
 * A real (non-fake) upload: Symfony sniffs its MIME from the bytes, like
 * production. Illuminate's fake files report the MIME of their name.
 */
function sniffedUpload(string $name, string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'flup');
    file_put_contents($path, $contents);

    return new UploadedFile($path, $name, null, null, true);
}

function pngBytes(int $w = 20, int $h = 20): string
{
    $img = imagecreatetruecolor($w, $h);
    ob_start();
    imagepng($img);

    return (string) ob_get_clean();
}
