<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Account\DeleteAccount;
use App\Actions\Account\SocialSignIn;
use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Account lifecycle for API clients: password reset (same broker, same
 * email and same web reset page as Fortify), native Google/Apple sign-in,
 * and self-service deletion.
 */
class AccountController extends Controller
{
    use ApiResponses;
    use PasswordValidationRules;
    use ResolvesAuthenticatedUser;

    /**
     * Always the same answer whether or not the email exists — never an
     * account-enumeration oracle.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        Password::broker()->sendResetLink(['email' => Str::lower($data['email'])]);

        return $this->respond(null, 'Si existe una cuenta con ese correo, te enviamos un enlace para restablecer tu contraseña.');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => $this->passwordRules(),
        ]);

        $status = Password::broker()->reset(
            ['email' => Str::lower($data['email']), 'token' => $data['token'], 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation')],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                // A reset signs every other device out.
                $user->tokens()->delete();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return $this->respond(null, 'Listo. Ya puedes iniciar sesión con tu nueva contraseña.');
    }

    public function socialSignIn(Request $request, string $provider, SocialSignIn $signIn): JsonResponse
    {
        abort_unless(in_array($provider, ['google', 'apple'], true), 404);

        $data = $request->validate([
            'id_token' => ['required', 'string', 'max:8192'],
            'nonce' => ['nullable', 'string', 'max:255'],
            'given_name' => ['nullable', 'string', 'max:255'],
            'family_name' => ['nullable', 'string', 'max:255'],
        ]);

        ['user' => $user, 'created' => $created] = $signIn->handle(
            $provider,
            $data['id_token'],
            ['given_name' => $data['given_name'] ?? null, 'family_name' => $data['family_name'] ?? null],
            $data['nonce'] ?? null,
        );

        $user->loadMissing(['legacyId', 'athlete']);

        return $this->respond([
            'user' => new UserResource($user),
            'token' => $user->createToken('api')->plainTextToken,
            'created' => $created,
        ], $created ? 'Tu Legacy comienza aquí.' : null, status: $created ? 201 : 200);
    }

    /**
     * `DELETE /me/account` — re-authentication: the current password, or
     * for an account that only ever signed in with Google/Apple (no known
     * password), typing the confirmation word.
     */
    public function destroy(Request $request, DeleteAccount $delete): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $hasSocialLogin = $user->socialAccounts()->exists();

        $data = $request->validate([
            'password' => [$hasSocialLogin ? 'nullable' : 'required', 'string'],
            'confirmation' => [$hasSocialLogin ? 'required_without:password' : 'nullable', 'nullable', 'string', Rule::in(['ELIMINAR'])],
        ], [
            'password.required' => 'Escribe tu contraseña para confirmar.',
            'confirmation.in' => 'Escribe ELIMINAR para confirmar.',
        ]);

        $passwordOk = isset($data['password']) && Hash::check($data['password'], $user->password);
        $confirmationOk = $hasSocialLogin && ($data['confirmation'] ?? null) === 'ELIMINAR';

        if (! $passwordOk && ! $confirmationOk) {
            throw ValidationException::withMessages(['password' => ['La contraseña no es correcta.']]);
        }

        $delete->handle($user);

        return $this->respond(null, 'Tu cuenta fue eliminada.');
    }
}
