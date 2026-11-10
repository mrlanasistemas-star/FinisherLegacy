<?php

namespace App\Console\Commands;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\LegacyTags\ActivateTag;
use App\Enums\MedalStatus;
use App\Enums\MedalVisibility;
use App\Enums\ProfileVisibility;
use App\Enums\StoryVisibility;
use App\Enums\UserStatus;
use App\Models\AthleteProfile;
use App\Models\LegacyStory;
use App\Models\Medal;
use App\Models\User;
use App\Services\LegacyIdService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Creates (or refreshes) the account App Store / Google Play reviewers sign
 * in with, so every feature is reviewable without a physical tag or an
 * event history (mobile repo: docs/store/review-notes-es-en.md).
 *
 * Clearly labelled as demo data and visible only to that account: private
 * profile, one private sample legacy story and one private sample medal,
 * all titled "[DEMO]". Idempotent — running it again resets the password
 * and the sample data, never duplicates them. Run it on PRODUCTION right
 * before each submission and paste the printed credentials into App Store
 * Connect › App Review Information and Play Console › App access.
 *
 *   php artisan app:review-account revision@finisherlegacy.com
 *   php artisan app:review-account revision@finisherlegacy.com --password="..."
 */
class CreateAppReviewAccount extends Command
{
    public const string DEMO_PREFIX = '[DEMO]';

    protected $signature = 'app:review-account
        {email : Correo de la cuenta para revisores (App Store / Google Play)}
        {--password= : Contraseña a usar (si se omite se genera una segura)}';

    protected $description = 'Crea o renueva la cuenta de revisión de tiendas con datos de demostración privados';

    public function handle(LegacyIdService $legacyIds, EnsureAthleteForUser $ensureAthlete): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('El correo no es válido.');

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::password(16, symbols: false));

        if (strlen($password) < 8) {
            $this->error('La contraseña debe tener al menos 8 caracteres.');

            return self::FAILURE;
        }

        $existing = User::withTrashed()->where('email', $email)->first();

        if ($existing !== null && ! $this->isReviewAccount($existing)) {
            $this->error('Ese correo ya pertenece a una cuenta real. Usa un correo exclusivo para revisión.');

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($existing, $email, $password, $legacyIds, $ensureAthlete) {
            $user = $existing ?? new User;
            if ($user->trashed()) {
                $user->restore();
            }

            $user->forceFill([
                'first_name' => 'Revisión',
                'last_name' => 'App (demo)',
                'email' => $email,
                'password' => $password,
                'status' => UserStatus::Active,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            if (Role::query()->where('name', 'athlete')->exists() && ! $user->hasRole('athlete')) {
                $user->assignRole('athlete');
            }

            $legacyIds->issueFor($user);
            $athlete = $ensureAthlete->handle($user, 'registration');

            AthleteProfile::query()->updateOrCreate(['user_id' => $user->id], [
                'username' => AthleteProfile::query()->where('user_id', $user->id)->value('username') ?? $this->freeUsername(),
                'bio' => self::DEMO_PREFIX.' Cuenta de demostración para la revisión de App Store y Google Play. Todos los datos son de ejemplo.',
                'country' => 'México',
                'profile_visibility' => ProfileVisibility::Private,
            ]);

            $story = LegacyStory::query()->firstOrNew([
                'athlete_id' => $athlete->id,
                'title' => self::DEMO_PREFIX.' Mi primer medio maratón',
            ]);
            if (! $story->exists) {
                // Identifiers stay stable across runs (shared links, media).
                $story->uuid = (string) Str::uuid();
                $story->public_id = ActivateTag::newPublicId();
            }
            $story->fill([
                'event_name' => 'Medio Maratón de Ejemplo (datos de demostración)',
                'distance' => '21K',
                'result_time' => '01:52:30',
                'race_date' => now()->subMonths(2)->toDateString(),
                'location' => 'Ciudad de México',
                'dedication' => 'Para quienes me acompañaron en cada kilómetro.',
                'body' => 'Historia de ejemplo creada para la revisión de la app. No corresponde a un evento real ni a una persona real.',
                'visibility' => StoryVisibility::Private,
                'completed_at' => now(),
            ])->save();

            Medal::query()->updateOrCreate(
                ['user_id' => $user->id, 'title' => self::DEMO_PREFIX.' Medio Maratón de Ejemplo'],
                [
                    'athlete_id' => $athlete->id,
                    'event_name_manual' => 'Medio Maratón de Ejemplo (demo)',
                    'event_date' => now()->subMonths(2)->toDateString(),
                    'distance_label' => '21K',
                    'official_time' => '01:52:30',
                    'pace' => '5:20',
                    'city' => 'Ciudad de México',
                    'country' => 'México',
                    'story' => 'Medalla de ejemplo para la revisión de la app. Datos de demostración.',
                    'visibility' => MedalVisibility::Private,
                    'status' => MedalStatus::Active,
                ],
            );

            // Fresh sign-in for the reviewer: drop any token from a past run.
            $user->tokens()->delete();

            return $user;
        });

        $this->info('Cuenta de revisión lista (datos de demostración privados).');
        $this->table(['Campo', 'Valor'], [
            ['Correo', $user->email],
            ['Contraseña', $password],
        ]);
        $this->line('Pega estas credenciales en App Store Connect › App Review y en Play Console › Acceso a la app.');

        return self::SUCCESS;
    }

    /** Only accounts this command created may be refreshed — never a real user's. */
    private function isReviewAccount(User $user): bool
    {
        return $user->first_name === 'Revisión' && $user->last_name === 'App (demo)';
    }

    private function freeUsername(): string
    {
        $base = 'revision_app';
        $candidate = $base;
        $n = 1;

        while (AthleteProfile::query()->where('username', $candidate)->exists()) {
            $candidate = $base.'_'.(++$n);
        }

        return $candidate;
    }
}
