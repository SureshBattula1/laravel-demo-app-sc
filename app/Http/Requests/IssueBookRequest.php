<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for issuing a book to a member (student or teacher).
 * Gated by `permission:library.issue` on the route.
 */
class IssueBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => 'required|exists:users,id',
            'borrower_type' => 'required|in:Student,Teacher',
            'issue_date' => 'nullable|date',
            // Optional: server computes a default due date from the loan rules when omitted.
            'due_date' => 'nullable|date',
            'remarks' => 'nullable|string|max:500',
        ];
    }
}
