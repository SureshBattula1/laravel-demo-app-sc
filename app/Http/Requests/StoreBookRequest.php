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
            'author' => 'nullable|string|max:255',
            'author_id' => 'nullable|exists:library_authors,id',
            'isbn' => [
                'nullable', 'string', 'max:50',
                Rule::unique('books', 'isbn')
                    ->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at'))
                    ->ignore($bookId),
            ],
            'category' => 'nullable|string|max:100',
            'category_id' => 'nullable|exists:library_categories,id',
            'publisher' => 'nullable|string|max:255',
            'publisher_id' => 'nullable|exists:library_publishers,id',
            'shelf_id' => 'nullable|exists:library_shelves,id',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:library_subjects,id',
            'published_year' => 'nullable|integer|min:1800|max:'.(date('Y') + 1),
            'language' => "$req|string|max:50",
            'edition' => 'nullable|string|max:50',
            'ddc_code' => 'nullable|string|max:50',
            'call_number' => 'nullable|string|max:50',
            'pages' => 'nullable|integer|min:1',
            'total_copies' => "$req|integer|min:1",
            'available_copies' => 'nullable|integer|min:0',
            'copies_mode' => 'nullable|in:auto,custom',
            'barcode_prefix' => 'nullable|string|max:20',
            'accession_prefix' => 'nullable|string|max:20',
            'copies' => 'nullable|array',
            'copies.*.barcode' => 'nullable|string|max:100',
            'copies.*.accession_number' => 'nullable|string|max:100',
            'copies.*.shelf_id' => 'nullable|exists:library_shelves,id',
            'copies.*.condition' => 'nullable|in:New,Good,Fair,Damaged,Lost,Weeded',
            'copies.*.purchase_price' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $isUpdate = in_array($this->method(), ['PUT', 'PATCH'], true);
            if (! $isUpdate) {
                if (empty($this->author) && empty($this->author_id)) {
                    $validator->errors()->add('author', 'Author is required.');
                }
                if (empty($this->category) && empty($this->category_id)) {
                    $validator->errors()->add('category', 'Category is required.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'isbn.unique' => 'A book with this ISBN already exists in this branch.',
        ];
    }
}
