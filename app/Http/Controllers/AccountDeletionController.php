<?php

namespace App\Http\Controllers;

use App\Enums\ContactMessageStatus;
use App\Enums\ContactMessageType;
use App\Models\CompanySetting;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public /eliminar-cuenta — the web resource Google Play requires for
 * account deletion (also linked from the App Store listing).
 *
 * Explains the self-service paths (app: Configuración › Contraseña y
 * cuenta; web: Ajustes › Perfil) and takes a request from people who no
 * longer have the app. A request NEVER deletes anything by itself: it is
 * stored as a ContactMessage (type account_deletion) for Administración ›
 * Mensajes, where staff verify that the requester owns the email before
 * running the deletion. Nobody can erase someone else's account from here.
 */
class AccountDeletionController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('AccountDeletion', [
            'contactEmail' => CompanySetting::contactChannels()['email'] ?? null,
            'signedIn' => $request->user() !== null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['accepted'],
            // Honeypot — real people never fill a hidden field.
            'website' => ['prohibited'],
        ], [
            'confirm.accepted' => __('Confirma que entiendes que la eliminación es definitiva.'),
            'website.prohibited' => __('No pudimos enviar tu solicitud.'),
        ]);

        $reason = trim((string) ($data['reason'] ?? ''));

        ContactMessage::create([
            'user_id' => $request->user()?->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'type' => ContactMessageType::AccountDeletion,
            'message' => "Solicitud de eliminación de cuenta desde /eliminar-cuenta.\n"
                ."Verificar que quien escribe controla el correo {$data['email']} antes de eliminar.\n\n"
                .'Motivo: '.($reason !== '' ? $reason : '(sin motivo)'),
            'status' => ContactMessageStatus::New,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recibimos tu solicitud. Te escribiremos a ese correo para confirmarla.')]);

        return back();
    }
}
