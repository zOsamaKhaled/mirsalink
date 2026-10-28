<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The signature on every request between Rizq and the platform, both ways.
 *
 * The signer hashes the time, the method, the path and the body with the
 * shared secret; the receiver does the same and compares. A request more
 * than five minutes off, or one already seen, is refused, so a copied
 * request cannot be sent again. Rizq keeps the same class.
 */
final class SignedRequest
{
    public const string TIMESTAMP_HEADER = 'X-Internal-Timestamp';

    public const string SIGNATURE_HEADER = 'X-Internal-Signature';

    public const int TOLERANCE_SECONDS = 300;

    /**
     * @return array<string, string>
     */
    public static function headers(string $method, string $url, string $body, string $secret, ?int $timestamp = null): array
    {
        $timestamp ??= now()->timestamp;

        return [
            self::TIMESTAMP_HEADER => (string) $timestamp,
            self::SIGNATURE_HEADER => 'sha256='.self::sign($method, (string) parse_url($url, PHP_URL_PATH), $body, $secret, $timestamp),
        ];
    }

    public static function verify(Request $request, string $secret): bool
    {
        $timestamp = (string) $request->header(self::TIMESTAMP_HEADER);

        if ($secret === '' || ! ctype_digit($timestamp) || abs(now()->timestamp - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = 'sha256='.self::sign($request->method(), $request->getPathInfo(), $request->getContent(), $secret, (int) $timestamp);

        if (! hash_equals($expected, (string) $request->header(self::SIGNATURE_HEADER))) {
            return false;
        }

        // Good once only: kept past the tolerance, so a replay is always caught.
        return Cache::add('signed-request:'.hash('sha256', $expected), true, self::TOLERANCE_SECONDS * 2);
    }

    private static function sign(string $method, string $path, string $body, string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', implode("\n", [$timestamp, strtoupper($method), '/'.ltrim($path, '/'), $body]), $secret);
    }
}
