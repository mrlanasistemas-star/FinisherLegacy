<?php

/**
 * Shared fixtures for the Entrenamientos tests: an athlete with a cheer
 * link, and uploads whose bytes fileinfo really sniffs as audio (or not).
 */

use App\Actions\Training\ManageCheerLinks;
use App\Models\CheerLink;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function cheerLinkFor(?User $owner = null, ?int $days = null): CheerLink
{
    $owner ??= User::factory()->create();

    return app(ManageCheerLinks::class)->create($owner, 'Mi maratón', $days);
}

function cheerUpload(string $name, string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'flcheer');
    file_put_contents($path, $contents);

    return new UploadedFile($path, $name, null, null, true);
}

/** Minimal MP3 (ID3v2 header + MPEG frames) — sniffed as audio/mpeg. */
function mp3Bytes(): string
{
    return "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x00", 200);
}

/** Minimal WebM/EBML header — what Chrome's MediaRecorder produces. */
function webmBytes(): string
{
    return "\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\xF7\x81\x01\x42\xF2\x81\x04\x42\xF3\x81\x08\x42\x82\x84webm\x42\x87\x81\x04\x42\x85\x81\x02".str_repeat("\0", 256);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function cheerPayload(array $overrides = []): array
{
    return [
        'sender_name' => 'Mamá',
        'relationship' => 'Mamá',
        'body' => '¡Tú puedes, hijo!',
        'icon' => 'heart',
        ...$overrides,
    ];
}
