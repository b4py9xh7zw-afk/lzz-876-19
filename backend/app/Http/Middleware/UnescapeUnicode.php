<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnescapeUnicode
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $options = $response->getEncodingOptions();
            $response->setEncodingOptions($options | JSON_UNESCAPED_UNICODE);
        }

        return $response;
    }
}
