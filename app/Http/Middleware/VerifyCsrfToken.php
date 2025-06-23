<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Support\Facades\Log;

class VerifyCsrfToken extends Middleware
{
    protected $except = [
        '/fbmorders/fetch-work-history',
        'force-logout',
        'keep-alive',
        'csrf-token',
    ];

    /**
     * Override and log token comparison process
     */
    protected function tokensMatch($request)
    {
        $sessionToken = $request->session()->token();
        $tokenFromRequest = $this->getTokenFromRequest($request);

        Log::channel('csrf')->info('🔐 CSRF Debug - ' . now()->toDateTimeString(), [
            'session_token' => $sessionToken,
            'token_from_request' => $tokenFromRequest,
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'cookies' => $request->cookies->all(),
            'headers' => $request->headers->all(),
        ]);

        return is_string($sessionToken) &&
            is_string($tokenFromRequest) &&
            hash_equals($sessionToken, $tokenFromRequest);
    }
}
