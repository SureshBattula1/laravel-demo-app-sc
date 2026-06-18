<?php

namespace App\Http\Middleware;

use App\Support\IdHasher;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs just before controllers. Rewrites incoming hashid tokens back to integer
 * IDs in (a) route parameters and (b) request input, so existing controllers
 * that expect numeric IDs work unchanged.
 *
 * Numeric values are left as-is, so raw integer IDs continue to work (useful
 * while the feature flag is off or during migration).
 */
class DecodeHashids
{
    public function __construct(private IdHasher $hasher) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->hasher->enabled()) {
            return $next($request);
        }

        $this->decodeHeaders($request);
        $this->decodeRouteParameters($request);
        $request->replace($this->decodeArray($request->all()));

        return $next($request);
    }

    private function decodeHeaders(Request $request): void
    {
        foreach ((array) config('hashids.headers', []) as $name) {
            $value = $request->header($name);
            if (is_string($value) && $value !== '' && ! ctype_digit($value)) {
                $decoded = $this->hasher->decode($value);
                if ($decoded !== null) {
                    $request->headers->set($name, (string) $decoded);
                }
            }
        }
    }

    private function decodeRouteParameters(Request $request): void
    {
        $route = $request->route();
        if (! $route) {
            return;
        }

        $patterns = (array) config('hashids.route_param_patterns', []);

        foreach ($route->parameters() as $name => $value) {
            if (! $this->keyMatches($name, $patterns)) {
                continue;
            }
            // Only decode non-numeric tokens; leave raw integers alone.
            if (is_string($value) && ! ctype_digit($value)) {
                $decoded = $this->hasher->decode($value);
                if ($decoded !== null) {
                    $route->setParameter($name, $decoded);
                }
            }
        }
    }

    /**
     * Recursively decode hashid string values on id-like keys.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function decodeArray(array $data): array
    {
        $patterns = (array) config('hashids.key_patterns', []);
        $denylist = (array) config('hashids.denylist', []);

        foreach ($data as $key => $value) {
            $isIdKey = is_string($key) && ! in_array($key, $denylist, true) && $this->keyMatches($key, $patterns);

            if (is_array($value)) {
                if ($isIdKey && $this->isScalarList($value)) {
                    $data[$key] = array_map(fn ($v) => $this->decodeScalar($v), $value);
                } else {
                    $data[$key] = $this->decodeArray($value);
                }
                continue;
            }

            if ($isIdKey) {
                $data[$key] = $this->decodeScalar($value);
            }
        }

        return $data;
    }

    /** Decode a single token; leave raw integers / non-tokens untouched. */
    private function decodeScalar(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && ! ctype_digit($value)) {
            $decoded = $this->hasher->decode($value);
            if ($decoded !== null) {
                return $decoded;
            }
        }
        return $value;
    }

    /** @param array<mixed> $value */
    private function isScalarList(array $value): bool
    {
        if (! array_is_list($value)) {
            return false;
        }
        foreach ($value as $v) {
            if (! is_string($v) && ! is_int($v)) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string> $patterns */
    private function keyMatches(string $key, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $key)) {
                return true;
            }
        }
        return false;
    }
}
