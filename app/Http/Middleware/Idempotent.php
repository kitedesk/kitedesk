<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets API clients retry a POST safely: send an `Idempotency-Key` header and a repeat of the
 * same request within 24 hours gets the first response back (with `Idempotent-Replayed: true`)
 * instead of creating the record again. Reusing a key for a different request, or while the
 * first one is still running, is a 409. Only successful responses are kept, so a request that
 * failed can be retried with the same key.
 */
class Idempotent
{
    public const string HEADER = 'Idempotency-Key';

    private const int TTL_SECONDS = 86400;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if (! $request->isMethod('POST') || ! is_string($key) || $key === '') {
            return $next($request);
        }

        abort_if(strlen($key) > 255, Response::HTTP_UNPROCESSABLE_ENTITY, __('The idempotency key may not be longer than 255 characters.'));

        $cacheKey = 'api-idempotency:'.$this->caller($request).':'.hash('sha256', $key);
        $fingerprint = $this->fingerprint($request);

        $lock = Cache::lock($cacheKey.':lock', 60);

        abort_unless($lock->get(), Response::HTTP_CONFLICT, __('A request with this idempotency key is still being processed.'));

        try {
            $stored = Cache::get($cacheKey);

            if (is_array($stored)) {
                return $this->replay($stored, $fingerprint);
            }

            $response = $next($request);

            if ($response->isSuccessful()) {
                Cache::put($cacheKey, [
                    'fingerprint' => $fingerprint,
                    'status' => $response->getStatusCode(),
                    'content_type' => $response->headers->get('Content-Type'),
                    'body' => $response->getContent(),
                ], self::TTL_SECONDS);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<mixed>  $stored  What `handle()` cached: fingerprint, status, content type and body.
     */
    private function replay(array $stored, string $fingerprint): Response
    {
        abort_unless(hash_equals((string) ($stored['fingerprint'] ?? ''), $fingerprint), Response::HTTP_CONFLICT, __('This idempotency key was already used for a different request.'));

        return response((string) ($stored['body'] ?? ''), (int) ($stored['status'] ?? Response::HTTP_OK), array_filter([
            'Content-Type' => $stored['content_type'] ?? null,
            'Idempotent-Replayed' => 'true',
        ]));
    }

    private function caller(Request $request): string
    {
        $token = $request->user()?->currentAccessToken();

        return $token instanceof PersonalAccessToken
            ? 'token:'.$token->getKey()
            : 'user:'.$request->user()?->getAuthIdentifier();
    }

    /**
     * The method, path and body, with uploaded files reduced to their name, size and hash.
     */
    private function fingerprint(Request $request): string
    {
        $files = array_map(
            fn (UploadedFile $file): array => [$file->getClientOriginalName(), $file->getSize(), hash_file('sha256', $file->getRealPath())],
            array_filter(collect($request->allFiles())->flatten()->all(), fn (mixed $file): bool => $file instanceof UploadedFile),
        );

        return hash('sha256', (string) json_encode([$request->method(), $request->path(), $request->except(array_keys($request->allFiles())), array_values($files)]));
    }
}
