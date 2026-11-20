<?php

namespace App\Http\Controllers\Settings;

use App\Exceptions\Privacy\ContentPinException;
use App\Http\Controllers\Controller;
use App\Services\Privacy\ContentPin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Web side of the content PIN: unlock / lock only, with a session-bound
 * grant (App\Services\Privacy\ContentPin). Creating, changing, disabling and
 * recovering the PIN live in the app (Ajustes → Seguridad).
 * App\Http\Middleware\EnsureContentPinUnlocked sends the owner here when a
 * protected memory is opened on the web.
 */
class ContentPinController extends Controller
{
    public function __construct(private readonly ContentPin $pins) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $status = $this->pins->status($user, $request);

        return Inertia::render('settings/ContentPin', [
            'enabled' => $status['enabled'],
            'unlocked' => $status['unlocked'],
            'lockedUntil' => $status['locked_until'],
            'redirect' => $this->safeRedirect($request->query('redirect')),
        ]);
    }

    public function unlock(Request $request): RedirectResponse
    {
        $data = $request->validate(['pin' => ContentPin::rules()], ['pin.regex' => __('El NIP debe tener de 4 a 6 números.')]);
        $user = $request->user();

        try {
            $this->pins->verify($user, $data['pin']);
        } catch (ContentPinException $e) {
            throw ValidationException::withMessages(['pin' => [$e->getMessage()]]);
        }

        $this->pins->issueGrant($user, $request);

        $redirect = $this->safeRedirect($request->input('redirect'));

        return $redirect !== null
            ? redirect()->to($redirect)
            : redirect()->route('content-pin.show')->with('success', __('Contenido desbloqueado por 10 minutos.'));
    }

    public function lock(Request $request): RedirectResponse
    {
        $this->pins->revokePresentedGrant($request);

        return redirect()->route('content-pin.show');
    }

    /** Same-site relative paths only (no open redirect). */
    private function safeRedirect(mixed $value): ?string
    {
        return is_string($value) && preg_match('#^/(?![/\\\\])[^\s]*$#', $value) === 1 ? $value : null;
    }
}
