<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // dd(app()->getLocale());
        $products = Product::where('language', app()->getLocale())->latest()->paginate(10);
        return view('admin.product.index', compact('products'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $products = Product::get();
        return view('admin.product.create', compact('products'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $image = "";
        if ($request->file('image')) {
            $file = $request->file('image');
            $filename = date('YmdHi') . '.'.$file->extension();
            $file->move(public_path('storage/uploads'), $filename);
            $image = $filename;
        }
        Product::create(array_merge($request->only(
            'title', 
            'image',
            'tag', 
            'language',
            'excerpt', 
            'description', 
            'price', 
            'original_price', 
        )));

        return redirect()->route('product.index')
            ->withSuccess(__('Product created successfully.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return view('admin.product.show', [
            'product' => $product
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        return view('admin.product.edit', [
            'product' => $product
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $product->update($request->only(
            'title', 
            'tag', 
            'language',
            'excerpt', 
            'description', 
            'price', 
            'original_price', 
            'image',
        ));

        return redirect()->route('product.index')
            ->withSuccess(__('Product updated successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function active(Request $request, $product_id)
    {
        $product = Product::findOrFail($product_id);
        $product->update(['active' => $request->active_product]);
    
        return redirect()->route('product.index')->withSuccess(__('Product updated successfully.'));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('product.index')
            ->withSuccess(__('Product deleted successfully.'));
    }
}
