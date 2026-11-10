<?php

namespace App\Http\Controllers\LegacyTags;

use App\Enums\LegacyTagActivationPlatform;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * One validation contract for the NFC self-activation, shared by the API
 * (Api\V1\LegacyTagActivationController) and the Web NFC page
 * (ActivationNfcController) so both accept exactly the same input.
 */
trait ActivationNfcRules
{
    /**
     * @return array{activation_code?: ?string, tag_code?: ?string, nfc_uid?: ?string, platform: string}
     */
    protected function validateStart(Request $request): array
    {
        /** @var array{activation_code?: ?string, tag_code?: ?string, nfc_uid?: ?string, platform: string} */
        return $request->validate([
            'activation_code' => ['nullable', 'string', 'max:32', 'required_without:tag_code'],
            'tag_code' => ['nullable', 'string', 'max:200', 'required_without:activation_code'],
            'nfc_uid' => ['nullable', 'string', 'max:64', 'regex:/^[0-9A-Fa-f:\s-]+$/'],
            'platform' => ['required', Rule::enum(LegacyTagActivationPlatform::class)],
        ], [
            'activation_code.required_without' => __('Escribe el código de activación de la tarjeta que venía en el empaque.'),
            'tag_code.required_without' => __('Escribe el código de activación de la tarjeta que venía en el empaque.'),
            'nfc_uid.regex' => __('No pudimos leer bien la pieza. Vuelve a acercarla.'),
        ]);
    }

    /**
     * @return array{written_url: string, nfc_uid?: ?string}
     */
    protected function validateConfirm(Request $request): array
    {
        /** @var array{written_url: string, nfc_uid?: ?string} */
        return $request->validate([
            'written_url' => ['required', 'string', 'max:255'],
            'nfc_uid' => ['nullable', 'string', 'max:64', 'regex:/^[0-9A-Fa-f:\s-]+$/'],
        ]);
    }

    /**
     * @return array{reason: string}
     */
    protected function validateFail(Request $request): array
    {
        /** @var array{reason: string} */
        return $request->validate([
            'reason' => ['required', 'string', 'max:120'],
        ]);
    }
}
