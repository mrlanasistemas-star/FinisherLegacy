<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Translation of one field of an admin-entered record (product name,
 * collection tagline…). The Spanish original stays in the record itself
 * (docs/renovacion/idiomas.md §Contenido dinámico).
 *
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property string $locale
 * @property string $field
 * @property string|null $value
 * @property string $status machine | reviewed | stale | needs_review
 * @property string $source_hash
 */
#[Fillable(['model_type', 'model_id', 'locale', 'field', 'value', 'status', 'source_hash'])]
class ModelTranslation extends Model
{
    public const string MACHINE = 'machine';

    public const string REVIEWED = 'reviewed';

    public const string STALE = 'stale';

    /** Written by a person, but the Spanish original changed afterwards. */
    public const string NEEDS_REVIEW = 'needs_review';

    /** @return MorphTo<Model, $this> */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
