<?php

namespace App\Http\Middleware;

use App\Support\IdHasher;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Outermost middleware: after the response is built, walks the JSON body and
 * replaces integer values on id-like keys (id, *_id, *_by) with their hashid
 * token. String columns that merely end in "_id" (tax_id, employee_id) are
 * never integers, so they pass through untouched.
 */
class EncodeHashids
{
    public function __construct(private IdHasher $hasher) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->hasher->enabled() || ! $response instanceof JsonResponse) {
            return $response;
        }

        $data = $response->getData(true);
        if (is_array($data)) {
            $response->setData($this->encodeArray($data));
        }

        return $response;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function encodeArray(array $data): array
    {
        $patterns = (array) config('hashids.key_patterns', []);
        $denylist = (array) config('hashids.denylist', []);

        foreach ($data as $key => $value) {
            $isIdKey = is_string($key) && ! in_array($key, $denylist, true) && $this->keyMatches($key, $patterns);

            if (is_array($value)) {
                if ($isIdKey && $this->isIntList($value)) {
                    $data[$key] = array_map(fn ($v) => $this->hasher->encode($v), $value);
                } else {
                    $data[$key] = $this->encodeArray($value);
                }
                continue;
            }

            // Encode only true integer IDs; strings (e.g. employee codes) are left alone.
            if ($isIdKey && is_int($value)) {
                $data[$key] = $this->hasher->encode($value);
            }
        }

        return $data;
    }

    /** @param array<mixed> $value */
    private function isIntList(array $value): bool
    {
        if (! array_is_list($value) || $value === []) {
            return false;
        }
        foreach ($value as $v) {
            if (! is_int($v)) {
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
