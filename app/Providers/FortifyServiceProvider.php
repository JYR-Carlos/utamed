<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            \Laravel\Fortify\Contracts\LoginResponse::class,
            \App\Http\Responses\LoginResponse::class
        );

        // Añade Inertia::clearHistory() al logout para que "atrás" no repinte
        // los datos de la sesión cerrada desde el historial del navegador.
        $this->app->singleton(
            \Laravel\Fortify\Contracts\LogoutResponse::class,
            \App\Http\Responses\LogoutResponse::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        // Custom Authentication Logic (RUT only)
        Fortify::authenticateUsing(function (Request $request) {
            // Field is named 'email' for Fortify compatibility, but contains the RUT
            $rutInput = $request->input('email');

            // Normalize: strip dots and dash so both sides are comparable
            // e.g. "11.111.111-1" or "11111111-1" or "111111111" all normalize to "111111111"
            $normalizedInput = preg_replace('/[.\-]/', '', $rutInput ?? '');

            Log::channel('single')->info('[LOGIN] Intento de login', [
                'rut_input'      => $rutInput,
                'rut_normalized' => $normalizedInput,
                'ip'             => $request->ip(),
            ]);

            try {
                $user = \App\Models\Usuario\Usuario::whereRaw(
                    "REPLACE(REPLACE(rut, '.', ''), '-', '') = ?",
                    [$normalizedInput]
                )->first();

                if (!$user) {
                    Log::channel('single')->warning('[LOGIN] Usuario no encontrado', ['rut_normalized' => $normalizedInput]);
                    throw new \App\Exceptions\LoginException(\App\Enums\LoginErrorCode::RUT_NOT_FOUND);
                }

                Log::channel('single')->info('[LOGIN] Usuario encontrado', [
                    'id'              => $user->id_usuario,
                    'username'        => $user->username,
                    'esta_activo'     => $user->esta_activo,
                    'passhash_length' => strlen($user->passhash ?? ''),
                ]);

                // Validar que el usuario está activo
                if (!$user->esta_activo) {
                    Log::channel('single')->warning('[LOGIN] Usuario inactivo', ['id' => $user->id_usuario]);
                    throw new \App\Exceptions\LoginException(\App\Enums\LoginErrorCode::USER_INACTIVE);
                }

                // Validar contraseña
                $passwordOk = \Illuminate\Support\Facades\Hash::check($request->password, $user->passhash);

                if (!$passwordOk) {
                    Log::channel('single')->warning('[LOGIN] Contraseña incorrecta', [
                        'id'       => $user->id_usuario,
                        'username' => $user->username,
                    ]);
                    throw new \App\Exceptions\LoginException(\App\Enums\LoginErrorCode::PASSWORD_INCORRECT);
                }

                Log::channel('single')->info('[LOGIN] Autenticación exitosa', [
                    'id'       => $user->id_usuario,
                    'username' => $user->username,
                ]);

                return $user;
            } catch (\App\Exceptions\LoginException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::channel('single')->error('[LOGIN] Excepción durante autenticación', [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                ]);
                throw new \App\Exceptions\LoginException(\App\Enums\LoginErrorCode::RUT_NOT_FOUND);
            }
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn(Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn(Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn(Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn(Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn() => Inertia::render('auth/Register'));

        Fortify::twoFactorChallengeView(fn() => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn() => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            // En local se usa un límite más holgado para no bloquear durante desarrollo
            $maxAttempts = app()->isLocal() ? 20 : 5;

            return Limit::perMinute($maxAttempts)->by($throttleKey);
        });
    }
}
