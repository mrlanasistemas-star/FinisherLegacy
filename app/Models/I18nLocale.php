<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin switch for a configured language: disabled languages are hidden
 * from the public switcher and never resolved for visitors. The list of
 * languages itself lives in config/i18n.php; no row = enabled.
 *
 * @property string $locale
 * @property bool $enabled
 */
#[Fillable(['locale', 'enabled'])]
class I18nLocale extends Model
{
    protected $primaryKey = 'locale';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
