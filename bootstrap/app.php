<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__));

/*
|--------------------------------------------------------------------------
| Load .env.production when APP_ENV=production
|--------------------------------------------------------------------------
|
| This makes Laravel use .env.production automatically when your app is
| deployed in production (like on Render). Locally, it will still use .env.
|
*/

$envFile = '.env';

if (env('APP_ENV') === 'production' && file_exists(dirname(__DIR__).'/.env.production')) {
    $envFile = '.env.production';
}

$app->loadEnvironmentFrom($envFile);

return $app
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apply CORS globally to ALL requests (remove the alias)
        $middleware->web(append: [
            \App\Http\Middleware\Cors::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\Cors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
