<?php

namespace App\Http\Controllers\LegacyTags\Concerns;

use App\Models\LegacyTag;
use App\Support\LegacyTags\TagCode;

trait FindsLegacyTag
{
    /** Canonical code → tag, or null (never a hint about which part was wrong). */
    protected function findTag(?string $input): ?LegacyTag
    {
        $code = TagCode::normalize($input);

        return $code === null ? null : LegacyTag::query()->where('public_code', $code)->first();
    }

    protected function findTagOrFail(?string $input): LegacyTag
    {
        $tag = $this->findTag($input);
        abort_if($tag === null, 404);

        return $tag;
    }
}
