<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        $allowed = match ($role) {
            'admin' => $user?->isAdmin(),
            'client' => $user?->isClient(),
            default => false,
        };

        // 404 instead of 403, so the admin area is not advertised to clients.
        abort_unless($allowed, 404);

        return $next($request);
    }
}
