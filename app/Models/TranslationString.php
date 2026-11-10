<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One translatable string in one language (docs/renovacion/idiomas.md).
 * The lang/*.json files and the mobile catalogs are built from these rows.
 *
 * @property int $id
 * @property string $locale
 * @property string $group ui | mobile | email | content
 * @property string $key_hash sha256 of the key (the Spanish text for ui/mobile/email)
 * @property string|null $key
 * @property string $source_text
 * @property string|null $translated_text
 * @property string $status missing | machine | reviewed | stale
 * @property string $source_hash
 * @property bool $needs_review
 * @property list<string>|null $origins
 * @property Carbon|null $obsolete_at
 * @property Carbon|null $translated_at
 * @property int|null $reviewed_by
 */
#[Fillable([
    'locale', 'group', 'key_hash', 'key', 'source_text', 'translated_text', 'status', 'source_hash',
    'needs_review', 'origins', 'obsolete_at', 'translated_at', 'reviewed_by',
])]
class TranslationString extends Model
{
    public const string MISSING = 'missing';

    public const string MACHINE = 'machine';

    public const string REVIEWED = 'reviewed';

    public const string STALE = 'stale';

    public const array GROUPS = ['ui', 'mobile', 'email', 'content'];

    protected function casts(): array
    {
        return [
            'needs_review' => 'boolean',
            'origins' => 'array',
            'obsolete_at' => 'datetime',
            'translated_at' => 'datetime',
        ];
    }

    public static function hash(string $text): string
    {
        return hash('sha256', $text);
    }

    /** A usable translation exists (a stale one is still better than nothing). */
    public function isTranslated(): bool
    {
        return $this->translated_text !== null && $this->translated_text !== '';
    }
}
