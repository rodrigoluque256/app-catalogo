<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 5 intentos por minuto por email+IP: frena el adivinado de contraseñas sin bloquear a otros usuarios de la misma IP.
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        // "Olvidé mi contraseña": pocos pedidos por email+IP (cada uno manda un email).
        RateLimiter::for('password-email', fn (Request $request): Limit => Limit::perMinute(3)
            ->by('password-email|'.strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Links de invitación y reseteo: frena probar tokens a ciegas.
        RateLimiter::for('access-links', fn (Request $request): Limit => Limit::perMinute(10)->by('access-links|'.$request->ip()));

        // Super panel: además del límite por email+IP, uno por IP para frenar el barrido de emails.
        RateLimiter::for('platform-login', fn (Request $request): array => [
            Limit::perMinute(5)->by('platform|'.strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by('platform-ip|'.$request->ip()),
        ]);

        // Códigos de 6 dígitos: 5 intentos por minuto por administrador+IP.
        RateLimiter::for('platform-2fa', fn (Request $request): Limit => Limit::perMinute(5)
            ->by('platform-2fa|'.$request->user()?->getAuthIdentifier().'|'.$request->ip()));
    }
}
