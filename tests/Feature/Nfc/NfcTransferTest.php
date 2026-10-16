<?php

require_once __DIR__.'/helpers.php';

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Nfc\AssignNfcTag;
use App\Actions\Nfc\ClaimNfcTag;
use App\Actions\Nfc\TransferNfcAssignment;
use App\Exceptions\Nfc\NfcTransferInvalidException;
use App\Models\AthleteOwnedProduct;
use App\Models\NfcAssignment;
use App\Models\NfcTag;
use App\Models\Product;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

function connectedProductFor(User $owner): NfcAssignment
{
    $tag = NfcTag::factory()->create();
    app(AssignNfcTag::class)->toProduct($tag, Product::factory()->create(), null, User::factory()->create());

    return app(ClaimNfcTag::class)->handle($tag, $owner);
}

test('an owner transfers a connected product with a confirmation token; history is kept and audited', function () {
    $owner = User::factory()->create();
    $recipientUser = User::factory()->create();
    $recipient = app(EnsureAthleteForUser::class)->handle($recipientUser, 'test');
    $assignment = connectedProductFor($owner);

    $token = app(TransferNfcAssignment::class)->request($assignment, $owner, $recipient);
    expect($token)->toHaveLength(40)
        ->and($assignment->fresh()->transfer_token_hash)->not->toBe($token);

    $next = app(TransferNfcAssignment::class)->confirm($assignment, $token, $recipientUser);

    expect($next->athlete_id)->toBe($recipient->id)
        ->and($next->ended_at)->toBeNull()
        ->and($assignment->fresh()->ended_at)->not->toBeNull()
        ->and($assignment->fresh()->end_reason)->toBe('transferred')
        ->and(AthleteOwnedProduct::query()->find($assignment->athlete_owned_product_id)->athlete_id)->toBe($recipient->id)
        ->and(Activity::query()->where('description', 'nfc.transfer.completed')->exists())->toBeTrue();
});

test('a wrong token, a wrong recipient, an expired request or a non-owner cannot transfer', function () {
    $owner = User::factory()->create();
    $recipientUser = User::factory()->create();
    $recipient = app(EnsureAthleteForUser::class)->handle($recipientUser, 'test');
    $intruder = User::factory()->create();
    app(EnsureAthleteForUser::class)->handle($intruder, 'test');
    $assignment = connectedProductFor($owner);
    $transfer = app(TransferNfcAssignment::class);

    expect(fn () => $transfer->request($assignment, $intruder, $recipient))->toThrow(NfcTransferInvalidException::class);

    $token = $transfer->request($assignment, $owner, $recipient);

    expect(fn () => $transfer->confirm($assignment, 'nope', $recipientUser))->toThrow(NfcTransferInvalidException::class)
        ->and(fn () => $transfer->confirm($assignment, $token, $intruder))->toThrow(NfcTransferInvalidException::class);

    $this->travel(73)->hours();
    expect(fn () => $transfer->confirm($assignment, $token, $recipientUser))->toThrow(NfcTransferInvalidException::class);
});

test('a Legacy Plate is never transferable', function () {
    [, $tag] = nfcPlateWithTag();
    $owner = User::factory()->create();
    $assignment = app(ClaimNfcTag::class)->handle($tag, $owner);
    $recipient = app(EnsureAthleteForUser::class)->handle(User::factory()->create(), 'test');

    expect(fn () => app(TransferNfcAssignment::class)->request($assignment, $owner, $recipient))
        ->toThrow(NfcTransferInvalidException::class);
});
