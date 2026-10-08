<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\LibraryAuthor;
use App\Models\LibraryCategory;
use App\Models\LibraryPublisher;
use App\Models\LibrarySubject;
use Illuminate\Http\Request;

class LibraryCatalogController extends Controller
{
    use PaginatesAndSorts;

    // ================= CATEGORIES =================
    public function getCategories(Request $request)
    {
        $query = LibraryCategory::with('parent')->withCount('books');
        $this->applyBranchFilter($query, $request);

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $categories = $this->paginateAndSort($query, $request, ['name', 'code', 'books_count', 'created_at'], 'name', 'asc');

        return response()->json(['success' => true, 'data' => $categories->items(), 'meta' => $this->formatMeta($categories)]);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'parent_id' => 'nullable|exists:library_categories,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $category = LibraryCategory::create(array_merge($validated, [
            'school_id' => $this->getCurrentSchoolId($request),
        ]));

        return response()->json(['success' => true, 'message' => 'Category created successfully', 'data' => $category], 201);
    }

    public function updateCategory(Request $request, string $id)
    {
        $category = LibraryCategory::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:50',
            'parent_id' => 'nullable|exists:library_categories,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $category->update($validated);

        return response()->json(['success' => true, 'message' => 'Category updated successfully', 'data' => $category]);
    }

    public function destroyCategory(string $id)
    {
        $category = LibraryCategory::findOrFail($id);
        if ($category->books()->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete category with associated books'], 422);
        }
        $category->delete();

        return response()->json(['success' => true, 'message' => 'Category deleted successfully']);
    }

    // ================= AUTHORS =================
    public function getAuthors(Request $request)
    {
        $query = LibraryAuthor::withCount('books');
        $this->applyBranchFilter($query, $request);

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $authors = $this->paginateAndSort($query, $request, ['name', 'nationality', 'books_count', 'created_at'], 'name', 'asc');

        return response()->json(['success' => true, 'data' => $authors->items(), 'meta' => $this->formatMeta($authors)]);
    }

    public function storeAuthor(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'biography' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'born_year' => 'nullable|integer',
            'website' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $author = LibraryAuthor::create(array_merge($validated, [
            'school_id' => $this->getCurrentSchoolId($request),
        ]));

        return response()->json(['success' => true, 'message' => 'Author created successfully', 'data' => $author], 201);
    }

    public function updateAuthor(Request $request, string $id)
    {
        $author = LibraryAuthor::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'biography' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'born_year' => 'nullable|integer',
            'website' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $author->update($validated);

        return response()->json(['success' => true, 'message' => 'Author updated successfully', 'data' => $author]);
    }

    public function destroyAuthor(string $id)
    {
        $author = LibraryAuthor::findOrFail($id);
        if ($author->books()->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete author with associated books'], 422);
        }
        $author->delete();

        return response()->json(['success' => true, 'message' => 'Author deleted successfully']);
    }

    // ================= PUBLISHERS =================
    public function getPublishers(Request $request)
    {
        $query = LibraryPublisher::withCount('books');
        $this->applyBranchFilter($query, $request);

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $publishers = $this->paginateAndSort($query, $request, ['name', 'contact_person', 'books_count', 'created_at'], 'name', 'asc');

        return response()->json(['success' => true, 'data' => $publishers->items(), 'meta' => $this->formatMeta($publishers)]);
    }

    public function storePublisher(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'website' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $publisher = LibraryPublisher::create(array_merge($validated, [
            'school_id' => $this->getCurrentSchoolId($request),
        ]));

        return response()->json(['success' => true, 'message' => 'Publisher created successfully', 'data' => $publisher], 201);
    }

    public function updatePublisher(Request $request, string $id)
    {
        $publisher = LibraryPublisher::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'website' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $publisher->update($validated);

        return response()->json(['success' => true, 'message' => 'Publisher updated successfully', 'data' => $publisher]);
    }

    public function destroyPublisher(string $id)
    {
        $publisher = LibraryPublisher::findOrFail($id);
        if ($publisher->books()->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete publisher with associated books'], 422);
        }
        $publisher->delete();

        return response()->json(['success' => true, 'message' => 'Publisher deleted successfully']);
    }

    // ================= SUBJECTS =================
    public function getSubjects(Request $request)
    {
        $query = LibrarySubject::withCount('books');
        $this->applyBranchFilter($query, $request);

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $subjects = $this->paginateAndSort($query, $request, ['name', 'code', 'books_count', 'created_at'], 'name', 'asc');

        return response()->json(['success' => true, 'data' => $subjects->items(), 'meta' => $this->formatMeta($subjects)]);
    }

    public function storeSubject(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $subject = LibrarySubject::create(array_merge($validated, [
            'school_id' => $this->getCurrentSchoolId($request),
        ]));

        return response()->json(['success' => true, 'message' => 'Subject created successfully', 'data' => $subject], 201);
    }

    public function updateSubject(Request $request, string $id)
    {
        $subject = LibrarySubject::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $subject->update($validated);

        return response()->json(['success' => true, 'message' => 'Subject updated successfully', 'data' => $subject]);
    }

    public function destroySubject(string $id)
    {
        $subject = LibrarySubject::findOrFail($id);
        $subject->books()->detach();
        $subject->delete();

        return response()->json(['success' => true, 'message' => 'Subject deleted successfully']);
    }

    private function formatMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
