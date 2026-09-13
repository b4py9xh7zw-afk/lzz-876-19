<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        if (!$request->expectsJson()) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }
            return route('login');
        }
        return null;
    }

    protected function unauthenticated($request, array $guards)
    {
        throw new \Illuminate\Auth\AuthenticationException(
            'Unauthenticated.',
            $guards,
            $request->is('api/*') || $request->expectsJson()
                ? null
                : $this->redirectTo($request)
        );
    }
}
