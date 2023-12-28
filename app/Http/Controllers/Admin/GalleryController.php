<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GalleryImage;
use Illuminate\Support\Str;

class GalleryController extends Controller
{

    public function index()
    {
        $gallery_images = GalleryImage::latest()->paginate(10);

        return view('admin.gallery.index', compact('gallery_images'));
    }


    public function create()
    {
        return view('admin.gallery.create');
    }

    public function store(Request $request)
    {
        $gallery_image = new GalleryImage();
        $gallery_image->title= $request->title;

        if ($request->file('image')) {
            $file = $request->file('image');
            $filename = date('YmdHi') . Str::slug($file->getClientOriginalName());
            $file->move(public_path('storage/uploads'), $filename);
            $gallery_image->path = $filename;
        }

        $gallery_image->save();

        return redirect()->route('gallery.index')
            ->withSuccess(__('Gallery image created successfully.'));
    }


    public function show($id)
    {    $gallery_image = GalleryImage::find($id)->first();
        return view('admin.gallery.show', [
            'gallery_image' => $gallery_image
        ]);
    }
  
    public function destroy( $id)
    {
        GalleryImage::destroy($id);

        return redirect()->route('gallery.index')
            ->withSuccess(__('GalleryImage deleted successfully.'));
    }
}
