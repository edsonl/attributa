<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Para requests web/inertia, redireciona para a tela de login.
     * Para requests que "esperam JSON", retorna 401 (sem redirect).
     */
    protected function redirectTo($request): ?string
    {
        if (! $request->expectsJson()) {
            // ajuste a rota de login que você criou
            return route('auth.login.show');
        }
        return null;
    }
}
