<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->app['events']->listen(Response::class, function ($response) {
            if ($response instanceof JsonResponse) {
                $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);
            }
        });
    }
}
