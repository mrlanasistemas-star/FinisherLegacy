<?php

use App\Enums\InventoryLocationType;
use App\Enums\LegacyTagStatus;
use App\Enums\TagAccessSource;
use App\Enums\TagScanEvent;
use App\Models\Ally;
use App\Models\Athlete;
use App\Models\EventParticipant;
use App\Models\LegacyStory;
use App\Models\LegacyTagScan;
use App\Models\NfcTag;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

require_once __DIR__.'/story-helpers.php';

function scanSources(int $tagId, TagScanEvent $event): array
{
    return LegacyTagScan::query()->where('legacy_tag_id', $tagId)->where('event', $event->value)->pluck('source')->map->value->all();
}

// (1) New tag + new user: register, come back via url.intended, activate.
test('a new user registers from the tag and activates it on return', function () {
    $this->seed(RolePermissionSeeder::class);
    $tag = deliveredTag();
    $code = $tag->public_code;

    $this->get("/t/{$code}")->assertRedirect(route('activate.show', ['code' => $code, 'src' => 'nfc']));
    $this->get(route('activate.show', ['code' => $code, 'src' => 'nfc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('activate/Show')->where('state', 'activatable')->where('source', 'nfc'));

    $this->get(route('activate.continue', ['code' => $code, 'provider' => 'register']))->assertRedirect(route('register'));

    $this->post(route('register.store'), [
        'first_name' => 'Ana',
        'last_name' => 'Ruiz',
        'email' => 'ana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('activate.show', $code));

    // Each real request reloads the user; the test client keeps the
    // in-memory model from registration, so hand it the stored one.
    $user = User::query()->where('email', 'ana@example.com')->firstOrFail();
    $this->actingAs($user);

    $this->post(route('activate.store', $code))->assertRedirect(route('activate.show', $code));
    $tag->refresh();

    expect($tag->status)->toBe(LegacyTagStatus::Activated)
        ->and($tag->owner_athlete_id)->toBe($user->athlete->id)
        ->and(scanSources($tag->id, TagScanEvent::ActivationCompleted))->toBe(['nfc'])
        ->and(scanSources($tag->id, TagScanEvent::ActivationStarted))->toContain('nfc');

    // Step 4 → "Otra carrera" → story editor.
    $this->get(route('activate.show', $code))
        ->assertInertia(fn ($page) => $page->component('activate/Show')->where('state', 'mine'));

    $story = $tag->story;
    $this->post(route('activate.complete', $code), [
        'race_mode' => 'manual',
        'event_name' => 'Maratón CDMX',
        'distance' => '42 km',
        'result_time' => '3:58:12',
        'race_date' => '2026-08-30',
        'location' => 'CDMX',
    ])->assertRedirect(route('my-legacy.edit', $story->public_id));

    expect($story->fresh()->event_name)->toBe('Maratón CDMX')
        ->and($story->fresh()->completed_at)->not->toBeNull();

    // Done: opening the tag again goes straight to the memory.
    $this->get("/t/{$code}")->assertRedirect(route('legacy.story.show', $story->public_id));
});

// (2) New tag + existing user, choosing one of their real participations.
test('an existing user activates and links one of their participations', function () {
    $user = User::factory()->create();
    $athlete = Athlete::factory()->create(['user_id' => $user->id]);
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $foreign = EventParticipant::factory()->create();
    $tag = deliveredTag();

    $this->actingAs($user)->post(route('activate.store', $tag->public_code))->assertRedirect(route('activate.show', $tag->public_code));
    $this->actingAs($user)->get(route('activate.show', $tag->public_code))
        ->assertInertia(fn ($page) => $page->where('state', 'mine')->where('participations.0.id', $participant->id));

    // Someone else's participation is never accepted.
    $this->actingAs($user)->post(route('activate.complete', $tag->public_code), ['race_mode' => 'participation', 'event_participant_id' => $foreign->id])
        ->assertSessionHasErrors('event_participant_id');

    $this->actingAs($user)->post(route('activate.complete', $tag->public_code), ['race_mode' => 'participation', 'event_participant_id' => $participant->id])
        ->assertRedirect();

    expect($tag->fresh()->story->event_participant_id)->toBe($participant->id)
        ->and($tag->fresh()->owner_athlete_id)->toBe($athlete->id);
});

// (3) Already activated by the same user → straight to the story.
test('the owner opening their activated tag goes to the story', function () {
    [$story, $owner, $tag] = ownedStory();

    $this->actingAs($owner)->get("/t/{$tag->public_code}")->assertRedirect(route('legacy.story.show', $story->public_id));
    $this->actingAs($owner)->get(route('activate.show', $tag->public_code))->assertRedirect(route('legacy.story.show', $story->public_id));

    // Activating again is idempotent.
    $this->actingAs($owner)->post(route('activate.store', $tag->public_code))->assertRedirect();
    expect(LegacyStory::query()->count())->toBe(1);
});

// (4) Claimed by someone else → honest message, ownership unchanged.
test('a tag activated by someone else explains it and never changes owner', function () {
    [, $owner, $tag] = ownedStory();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->post(route('activate.store', $tag->public_code))
        ->assertRedirect(route('activate.show', $tag->public_code))
        ->assertInertiaFlash('toast.message', 'Este Finisher Legacy ya fue activado por otra persona. Si es tuyo, escríbenos y lo revisamos.');

    $this->actingAs($intruder)->get(route('activate.show', $tag->public_code))
        ->assertInertia(fn ($page) => $page->component('activate/Show')->where('state', 'taken')->where('message', fn ($m) => str_contains($m, 'otra persona')));

    expect($tag->fresh()->owner_athlete_id)->toBe($owner->athlete->id);
});

// (5) Invalid / unknown code.
test('invalid and unknown codes are handled without hints', function () {
    $this->get('/t/ZZZZ-ZZZZ-ZZZZ')->assertNotFound();
    $this->get('/t/OOOOIIIILLLL')->assertNotFound();

    $this->post(route('activate.lookup'), ['code' => 'hola'])->assertSessionHasErrors('code');
    $this->post(route('activate.lookup'), ['code' => 'ZZZZ-ZZZZ-ZZZZ'])->assertSessionHasErrors(['code' => 'No encontramos ese código. Revisa que esté bien escrito o escríbenos.']);

    $this->get(route('activate.show', 'ZZZZZZZZZZZZ'))
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('activate/Unavailable')->where('reason', 'not_found'));
});

// (6) Disabled tag.
test('a disabled tag shows an honest page and cannot be activated', function () {
    $tag = deliveredTag(['status' => LegacyTagStatus::Disabled]);

    $this->get("/t/{$tag->public_code}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('activate/Unavailable')->where('reason', 'disabled'));

    $this->actingAs(User::factory()->create())->post(route('activate.store', $tag->public_code))->assertRedirect();

    expect($tag->fresh()->status)->toBe(LegacyTagStatus::Disabled)
        ->and($tag->fresh()->owner_athlete_id)->toBeNull();
});

test('warehouse stock is never activatable but an ally counter piece is', function () {
    $warehouseTag = tagStock(tagVariant(), tagLocation(), 1)->first();

    $this->get("/t/{$warehouseTag->public_code}")
        ->assertInertia(fn ($page) => $page->component('activate/Unavailable')->where('reason', 'not_sold'));

    $allyTag = tagStock(tagVariant(), tagLocation(InventoryLocationType::Ally, Ally::factory()->create()), 1)->first();
    $this->get("/t/{$allyTag->public_code}")->assertRedirect(route('activate.show', ['code' => $allyTag->public_code, 'src' => 'nfc']));
});

// (7) Old ?src=qr links. The tag no longer has a printed QR, but links that
// were already shared keep working and stay traceable.
test('an old ?src=qr link still works and is recorded as qr through the whole flow', function () {
    $tag = deliveredTag();
    $user = User::factory()->create();

    $this->actingAs($user)->get("/t/{$tag->public_code}?src=qr")
        ->assertRedirect(route('activate.show', ['code' => $tag->public_code, 'src' => 'qr']));
    $this->actingAs($user)->get(route('activate.show', ['code' => $tag->public_code, 'src' => 'qr']))->assertOk();
    $this->actingAs($user)->post(route('activate.store', $tag->public_code));

    expect(scanSources($tag->id, TagScanEvent::TagOpened))->toBe(['qr'])
        ->and(scanSources($tag->id, TagScanEvent::ActivationCompleted))->toBe(['qr']);
});

// (8) NFC chip (no src).
test('a tap without src is recorded as nfc and bumps the open counters', function () {
    $tag = deliveredTag();

    $this->get("/t/{$tag->public_code}");
    $this->get('/t/'.strtolower($tag->public_code));

    expect(scanSources($tag->id, TagScanEvent::TagOpened))->toBe(['nfc', 'nfc'])
        ->and($tag->fresh()->open_count)->toBe(2)
        ->and($tag->fresh()->first_opened_at)->not->toBeNull();

    $scan = LegacyTagScan::query()->first();
    expect($scan->visitor_hash)->toHaveLength(32)
        ->and(array_keys($scan->getAttributes()))->not->toContain('ip', 'user_agent');
});

// (9) Typed Legacy Code at /activar.
test('a typed code at /activar resolves and activates as code', function () {
    $tag = deliveredTag();
    $user = User::factory()->create();
    $typed = strtolower(implode(' ', str_split($tag->public_code, 4)));

    $this->get(route('activate.index'))->assertOk()->assertInertia(fn ($page) => $page->component('activate/Index'));

    $this->actingAs($user)->post(route('activate.lookup'), ['code' => $typed])
        ->assertRedirect(route('activate.show', ['code' => $tag->public_code, 'src' => 'code']));

    // A pasted /t/ link works too (including an old ?src=qr one).
    $this->actingAs($user)->post(route('activate.lookup'), ['code' => 'https://finisherlegacy.com/t/'.$tag->public_code.'?src=qr'])
        ->assertRedirect(route('activate.show', ['code' => $tag->public_code, 'src' => 'code']));

    $this->actingAs($user)->get(route('activate.show', ['code' => $tag->public_code, 'src' => 'code']));
    $this->actingAs($user)->post(route('activate.store', $tag->public_code));

    expect($tag->fresh()->status)->toBe(LegacyTagStatus::Activated)
        ->and(scanSources($tag->id, TagScanEvent::ActivationCompleted))->toBe([TagAccessSource::Code->value]);
});

// (10) Double request: two people → one owner; same person twice → idempotent.
test('double activation requests leave exactly one owner and one story', function () {
    $tag = deliveredTag();
    $a = User::factory()->create();
    $b = User::factory()->create();

    $this->actingAs($a)->post(route('activate.store', $tag->public_code))->assertInertiaFlash('toast.type', 'success');
    $this->actingAs($a)->post(route('activate.store', $tag->public_code))->assertInertiaFlash('toast.type', 'success');
    $this->actingAs($b)->post(route('activate.store', $tag->public_code))->assertInertiaFlash('toast.type', 'error');

    expect($tag->fresh()->owner_athlete_id)->toBe($a->athlete->id)
        ->and(LegacyStory::query()->count())->toBe(1)
        ->and(LegacyTagScan::query()->where('event', TagScanEvent::ActivationCompleted->value)->count())->toBe(2);
});

test('activation requires an account and continue only accepts login or register', function () {
    $tag = deliveredTag();

    $this->post(route('activate.store', $tag->public_code))->assertRedirect(route('login'));
    $this->get("/activar/{$tag->public_code}/continuar/admin")->assertNotFound();

    $this->get(route('activate.continue', ['code' => $tag->public_code, 'provider' => 'login']))->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe(route('activate.show', $tag->public_code));
});

test('public tag routes are throttled by tag-open', function () {
    expect(RateLimiter::limiter('tag-open'))->not->toBeNull();

    foreach (['tag.open', 'activate.show', 'activate.lookup', 'legacy.story.show', 'legacy.share.show', 'api.v1.tags.show'] as $name) {
        expect(app('router')->getRoutes()->getByName($name)->gatherMiddleware())->toContain('throttle:tag-open');
    }

    expect(app('router')->getRoutes()->getByName('my-legacy.media.store')->gatherMiddleware())->toContain('throttle:uploads');
});

test('a legacy NFC chip linked to a Finisher Legacy Tag opens /t/{code}', function () {
    $nfc = NfcTag::factory()->create();
    $tag = deliveredTag(['nfc_tag_id' => $nfc->id]);

    $this->get("/n/{$nfc->token}")->assertRedirect(route('tag.open', $tag->public_code));
});
