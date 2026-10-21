<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')
            ->orderBy('sort_order')
            ->get();

        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        $nextSortOrder = ((int) Category::max('sort_order')) + 1;
        return view('categories.create', compact('nextSortOrder'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:100|unique:categories,name',
            'slug'       => 'required|string|max:100|unique:categories,slug',
            'icon'       => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|unique:categories,sort_order',
            'is_active'  => 'boolean',
        ]);

        $nextSortOrder = ((int) Category::max('sort_order')) + 1;

        Category::create([
            'name'       => $request->name,
            'slug'       => $request->slug,
            'icon'       => $request->icon ?: 'shirt',
            'sort_order' => $request->filled('sort_order') ? (int)$request->sort_order : $nextSortOrder,
            'is_active'  => $request->boolean('is_active'),
        ]);

        return redirect()->route('categories.index')
            ->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $category)
    {
        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name'       => 'required|string|max:100|unique:categories,name,' . $category->id,
            'slug'       => 'required|string|max:100|unique:categories,slug,' . $category->id,
            'icon'       => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|unique:categories,sort_order,' . $category->id,
            'is_active'  => 'boolean',
        ]);

        $category->update([
            'name'       => $request->name,
            'slug'       => $request->slug,
            'icon'       => $request->icon ?: ($category->icon ?: 'shirt'),
            'sort_order' => $request->sort_order ?? $category->sort_order,
            'is_active'  => $request->boolean('is_active'),
        ]);

        return redirect()->route('categories.index')
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category)
    {
        // Check for products including soft-deleted ones
        if ($category->products()->withTrashed()->count() > 0) {
            return back()->with('error', 'Kategori tidak dapat dihapus karena masih memiliki produk (termasuk yang sudah dihapus/soft-deleted). Hapus atau pulihkan produk terlebih dahulu.');
        }

        $category->delete(); // Soft delete instead of forceDelete

        return redirect()->route('categories.index')
            ->with('success', 'Kategori berhasil dihapus (diarsipkan).');
    }
}
