<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies incoming Vapi webhooks. A shared secret is accepted from the
 * legacy `x-vapi-secret` header or as an `Authorization: Bearer` token;
 * HMAC signature verification is supported as an alternative. Requests
 * are rejected outright when no verification method is configured.
 */
final class VerifyVapiWebhook
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('vapi.webhook.secret');
        $hmacSecret = config('vapi.webhook.hmac.secret');

        $hasSecret = is_string($secret) && $secret !== '';
        $hasHmac = is_string($hmacSecret) && $hmacSecret !== '';

        if (! $hasSecret && ! $hasHmac) {
            abort(500, 'No Vapi webhook verification is configured. Set VAPI_WEBHOOK_SECRET or VAPI_WEBHOOK_HMAC_SECRET.');
        }

        if ($hasSecret && $this->matchesSecret($request, $secret)) {
            return $next($request);
        }

        if ($hasHmac && $this->matchesHmac($request, $hmacSecret)) {
            return $next($request);
        }

        abort(401, 'Invalid Vapi webhook credentials.');
    }

    private function matchesSecret(Request $request, string $secret): bool
    {
        $provided = $request->header('x-vapi-secret');
        if (is_string($provided) && hash_equals($secret, $provided)) {
            return true;
        }

        $bearer = $request->bearerToken();

        return is_string($bearer) && hash_equals($secret, $bearer);
    }

    private function matchesHmac(Request $request, string $secret): bool
    {
        $header = config('vapi.webhook.hmac.header');
        $algorithm = config('vapi.webhook.hmac.algorithm');
        $header = is_string($header) && $header !== '' ? $header : 'x-vapi-signature';
        $algorithm = is_string($algorithm) && $algorithm !== '' ? $algorithm : 'sha256';

        if (! in_array($algorithm, hash_hmac_algos(), true)) {
            return false;
        }

        $provided = $request->header($header);
        if (! is_string($provided) || $provided === '') {
            return false;
        }

        // Accept an "algorithm=" prefix, e.g. "sha256=<digest>".
        $prefix = $algorithm.'=';
        if (str_starts_with(strtolower($provided), $prefix)) {
            $provided = substr($provided, strlen($prefix));
        }

        $payload = $request->getContent();

        if (hash_equals(hash_hmac($algorithm, $payload, $secret), strtolower($provided))) {
            return true;
        }

        return hash_equals(base64_encode(hash_hmac($algorithm, $payload, $secret, true)), $provided);
    }
}
