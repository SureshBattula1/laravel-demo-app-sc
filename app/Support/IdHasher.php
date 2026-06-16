<?php

namespace App\Support;

use Hashids\Hashids;

/**
 * Reversible obfuscation of integer primary keys for use in URLs / API payloads.
 *
 * Wraps hashids/hashids with the project's configured salt/alphabet. Encoding is
 * deterministic; decoding returns null for anything that is not a valid token.
 */
class IdHasher
{
    private Hashids $hashids;

    /** @var array<string,string> simple in-process memo to avoid re-hashing */
    private array $encodeCache = [];

    public function __construct()
    {
        $this->hashids = new Hashids(
            (string) config('hashids.salt'),
            (int) config('hashids.min_length', 12),
            (string) config('hashids.alphabet')
        );
    }

    public function enabled(): bool
    {
        return (bool) config('hashids.enabled', false);
    }

    /**
     * Encode an integer id to its token. Non-integer / null values are returned
     * unchanged so callers can pass mixed data safely.
     */
    public function encode(mixed $value): mixed
    {
        if (! $this->isIntegerLike($value)) {
            return $value;
        }

        $int = (int) $value;
        return $this->encodeCache[$int] ??= $this->hashids->encode($int);
    }

    /**
     * Decode a token back to its integer id, or null if the token is invalid.
     */
    public function decode(mixed $token): ?int
    {
        if (is_int($token)) {
            return $token;
        }

        if (! is_string($token) || $token === '') {
            return null;
        }

        $decoded = $this->hashids->decode($token);

        return count($decoded) === 1 ? (int) $decoded[0] : null;
    }

    private function isIntegerLike(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        // Numeric strings that are whole numbers (e.g. "42") - but not floats.
        return is_string($value) && ctype_digit($value);
    }
}
