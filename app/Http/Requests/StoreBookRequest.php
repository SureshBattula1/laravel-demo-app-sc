<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Create/update validation for library books. Authorization is handled by the
 * route's `permission:library.create` / `library.edit` middleware, so authorize()
 * simply allows the request through to validation.
 */
class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = in_array($this->method(), ['PUT', 'PATCH'], true);
        $req = $isUpdate ? 'sometimes' : 'required';

        $bookId = $this->route('id');
        // isbn is unique per branch (see migration); resolve the branch for the scope.
        $branchId = $this->input('branch_id')
            ?? ($bookId ? DB::table('books')->where('id', $bookId)->value('branch_id') : null);

        return [
            'branch_id' => "$req|exists:branches,id",
            'title' => "$req|string|max:255",
            'author' => "$req|string|max:255",
            'isbn' => [
                'nullable', 'string', 'max:50',
                Rule::unique('books', 'isbn')
                    ->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at'))
                    ->ignore($bookId),
            ],
            'category' => "$req|string|max:100",
            'publisher' => 'nullable|string|max:255',
            'published_year' => 'nullable|integer|min:1800|max:' . (date('Y') + 1),
            'language' => "$req|string|max:50",
            'edition' => 'nullable|string|max:50',
            'pages' => 'nullable|integer|min:1',
            'total_copies' => "$req|integer|min:1",
            'available_copies' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'isbn.unique' => 'A book with this ISBN already exists in this branch.',
        ];
    }
}
