<?php

namespace App\Http\Controllers;

use App\Enums\ContactMessageStatus;
use App\Enums\ContactMessageType;
use App\Models\CompanySetting;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public /contact. Submissions are stored (ContactMessage) and reviewed
 * from Administración → Mensajes; nothing is emailed from here, so the
 * form works the same whether or not mail is configured.
 */
class ContactController extends Controller
{
    public function show(Request $request): Response
    {
        $type = ContactMessageType::tryFrom($request->string('tipo')->toString());
        $settings = CompanySetting::values();

        return Inertia::render('Contact', [
            'types' => ContactMessageType::options(),
            'initialType' => ($type ?? ContactMessageType::General)->value,
            'channels' => CompanySetting::contactChannels(),
            'location' => [
                'country' => $settings['country'],
                'city' => $settings['city'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'type' => ['required', Rule::enum(ContactMessageType::class)],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            // Honeypot — real people never fill a hidden field.
            'website' => ['prohibited'],
        ], [
            'message.min' => 'Cuéntanos un poco más (mínimo :min caracteres).',
            'website.prohibited' => 'No pudimos enviar tu mensaje.',
        ]);

        ContactMessage::create([
            ...collect($data)->except('website')->all(),
            'user_id' => $request->user()?->id,
            'status' => ContactMessageStatus::New,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gracias. Recibimos tu mensaje y te responderemos pronto.']);

        return back();
    }
}
