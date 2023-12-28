<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Guru;

class GuruController extends Controller
{
    public function index()
    {
        $gurus = Guru::latest()->paginate(10);
        return view('admin.guru.index', compact('gurus'));
    }

    public function create()
    {
        return view('admin.guru.create');
    }

    public function store(Request $request)
    {
        $image = "";
        if ($request->file('image')) {
            $file = $request->file('image');
            $filename = date('YmdHi') . '.' . $file->extension();
            $file->move(public_path('storage/uploads'), $filename);
            $image = $filename;
        }

        $cover = "";
        if ($request->file('cover')) {
            $file = $request->file('cover');
            $filename = date('YmdHi') . '.' . $file->extension();
            $file->move(public_path('storage/uploads'), $filename);
            $cover = $filename;
        }
        Guru::create(array_merge($request->only(
            'name',
            'image',
            'email',
            'phone',
            'excerpt',
            'description',
            'tags',
            'cover',
        )));

        return redirect()->route('guru.index')
            ->withSuccess(__('Guru created successfully.'));
    }

    public function show(Guru $guru)
    {
        return view('admin.guru.show', [
            'guru' => $guru
        ]);
    }

    public function edit(Guru $guru)
    {
        return view('admin.guru.edit', [
            'guru' => $guru
        ]);
    }

    public function update(Request $request, Guru $guru)
    {
        $guru->update($request->only(
            'name',
            'image',
            'email',
            'phone',
            'excerpt',
            'description',
            'tags',
            'cover',
        ));

        return redirect()->route('guru.index')
            ->withSuccess(__('Guru updated successfully.'));
    }

    public function destroy(Guru $guru)
    {
        $guru->delete();

        return redirect()->route('guru.index')
            ->withSuccess(__('Guru deleted successfully.'));
    }
}
