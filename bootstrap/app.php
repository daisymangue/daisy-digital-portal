<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->web(append: [SecurityHeaders::class, EnsureAccountActive::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->homeRoute());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A friendly message instead of "Too Many Attempts." when someone repeats an action very fast.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            $wait = (int) ($e->getHeaders()['Retry-After'] ?? 60);
            $message = 'That was a lot of tries in a short time. Please wait '.($wait >= 120 ? ceil($wait / 60).' minutes' : 'a minute').' and try again.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 429, $e->getHeaders());
            }

            // Sign-in forms show errors under the email box; everywhere else the page banner shows it.
            $key = $request->routeIs('login', 'register', 'login.link.send', 'login.link') || $request->is('login', 'register') ? 'email' : 'throttle';

            return back()->withInput($request->except('password', 'password_confirmation', 'files', 'proof'))->withErrors([$key => $message]);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
