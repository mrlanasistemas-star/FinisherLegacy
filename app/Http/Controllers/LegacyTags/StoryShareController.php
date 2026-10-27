<?php

namespace App\Http\Controllers\LegacyTags;

use App\Actions\LegacyTags\ManageStoryShares;
use App\Http\Controllers\Controller;
use App\Models\LegacyStory;
use App\Models\LegacyStoryShare;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Owner creates / revokes recipient links ("Para mamá"). */
class StoryShareController extends Controller
{
    public function store(Request $request, LegacyStory $story, ManageStoryShares $shares): RedirectResponse
    {
        $this->authorize('share', $story);
        $data = $request->validate(['label' => ['required', 'string', 'max:80']], [
            'label.required' => '¿Para quién es? Por ejemplo: Mamá.',
        ]);

        $share = $shares->create($story, $request->user(), $data['label']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Enlace listo para '.$share->label.'.']);
        Inertia::flash('share_url', $share->url());

        return back();
    }

    public function destroy(Request $request, LegacyStory $story, LegacyStoryShare $share, ManageStoryShares $shares): RedirectResponse
    {
        $this->authorize('share', $story);
        abort_unless($share->legacy_story_id === $story->id, 404);

        $shares->revoke($share, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Enlace desactivado.']);

        return back();
    }
}
