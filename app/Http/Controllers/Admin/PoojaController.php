<?php

namespace App\Http\Controllers\Admin;

use DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Pooja;

class PoojaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // dd(app()->getLocale());
        $pooja = Pooja::where('language', app()->getLocale())->latest()->paginate(10);
        return view('admin.pooja.index', compact('pooja'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $pooja = Pooja::get();
        return view('admin.pooja.create', compact('pooja'));
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
        Pooja::create(array_merge($request->only(
            'title', 
            'image',
            'tag', 
            'language',
            'excerpt', 
            'description', 
            'start_date', 
            'end_date', 
            'price', 
            'original_price', 
        )));

        return redirect()->route('pooja.index')
            ->withSuccess(__('Pooja created successfully.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Pooja $pooja)
    {
        return view('admin.pooja.show', [
            'pooja' => $pooja
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Pooja $pooja)
    {
        return view('admin.pooja.edit', [
            'pooja' => $pooja
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Pooja $pooja)
    {
        $pooja->update($request->only(
            'title', 
            'tag', 
            'language',
            'excerpt', 
            'description', 
            'start_date', 
            'end_date', 
            'price', 
            'original_price', 
            'image',
        ));

        return redirect()->route('pooja.index')
            ->withSuccess(__('Pooja updated successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function active(Request $request, $pooja_id)
    {
        $pooja = Pooja::findOrFail($pooja_id);
        $pooja->update(['active' => $request->active_pooja]);
    
        return redirect()->route('pooja.index')->withSuccess(__('Pooja updated successfully.'));
    }

    public function destroy(Pooja $pooja)
    {
        $pooja->delete();

        return redirect()->route('pooja.index')
            ->withSuccess(__('Pooja deleted successfully.'));
    }
}
