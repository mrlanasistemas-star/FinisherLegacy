<?php

namespace App\Http\Controllers;

use App\Actions\Photos\RegisterPhotographer;
use App\Enums\EventPhotoStatus;
use App\Enums\PhotographerStatus;
use App\Models\EventPhoto;
use App\Models\PhotographerProfile;
use App\Services\Photos\PhotoFeeCalculator;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Public "Fotógrafos" zone: what it means to sell on Finisher Legacy
 * (free upload, transparent commission) and the dedicated registration —
 * for new visitors (creates the account too) or signed-in users.
 */
class PhotographerJoinController extends Controller
{
    public function landing(PhotoFeeCalculator $fees): Response
    {
        return Inertia::render('photographers/Landing', [
            'fees' => $fees->rules(),
            'example' => $fees->split((int) config('finisher.photos.default_price_minor')),
            'stats' => [
                'photographers' => PhotographerProfile::query()->where('status', PhotographerStatus::Approved)->count(),
                'photos' => EventPhoto::query()->where('status', EventPhotoStatus::Published)->count(),
            ],
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->photographerProfile()->exists()) {
            return to_route('photographer.dashboard');
        }

        return Inertia::render('photographers/Register', [
            'fees' => app(PhotoFeeCalculator::class)->rules(),
        ]);
    }

    public function store(Request $request, CreatesNewUsers $createUser, RegisterPhotographer $register): RedirectResponse
    {
        $profileData = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'accept_terms' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'Acepta los términos para fotógrafos para continuar.',
        ]);

        $user = $request->user();

        if ($user === null) {
            $user = $createUser->create($request->only(['first_name', 'last_name', 'email', 'password', 'password_confirmation']));
            event(new Registered($user));
            Auth::login($user);
            $request->session()->regenerate();
        }

        $register->handle($user, $profileData);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recibimos tu solicitud. Te avisaremos al aprobar tu perfil de fotógrafo.']);

        return to_route('photographer.dashboard');
    }
}
