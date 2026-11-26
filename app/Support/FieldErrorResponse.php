<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;

class FieldErrorResponse
{
    public static function validation(MessageBag|array $errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors instanceof MessageBag ? $errors->toArray() : $errors,
        ], 422);
    }

    /**
     * Map a duplicate or invalid-data database error onto a form field.
     * Returns null when the failure is not a field problem.
     */
    public static function fromQueryException(QueryException $e): ?JsonResponse
    {
        $state = (string) ($e->errorInfo[0] ?? '');
        $detail = $e->getMessage();
        $field = self::fieldFromMessage($detail);

        $isConstraint = $state === '23000' || str_contains($detail, 'Duplicate entry');
        $isInvalid = in_array($state, ['22001', '22007', 'HY000'], true)
            || str_contains($detail, 'Data truncated')
            || str_contains($detail, 'Incorrect');

        if (! $isConstraint && ! $isInvalid) {
            return null;
        }

        if ($field === null) {
            return null;
        }

        $label = str_replace('_', ' ', $field);
        $message = $isConstraint
            ? 'This '.$label.' is already in use.'
            : 'This '.$label.' is not valid.';

        return self::validation([$field => [$message]]);
    }

    private static function fieldFromMessage(string $detail): ?string
    {
        $known = [
            'email',
            'employee_id',
            'admission_number',
            'phone',
            'pan_number',
            'aadhaar_number',
            'branch_id',
            'department_id',
            'date_of_birth',
            'gender',
            'category_type',
        ];

        if (preg_match("/for key '([^']+)'/", $detail, $match) || preg_match("/column '([^']+)'/", $detail, $match)) {
            $token = strtolower($match[1]);
            foreach ($known as $field) {
                if (str_contains($token, $field)) {
                    return $field;
                }
            }
        }

        foreach ($known as $field) {
            if (str_contains(strtolower($detail), $field)) {
                return $field;
            }
        }

        return null;
    }
}
