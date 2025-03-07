<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FileManagerController extends Controller
{
    public function index(Request $request)
    {
        $dir = $request->query("dir");
        $disk = Storage::disk('public');
        $path = $disk->path('');
        $files = $disk->files($dir);
        $directories = $disk->directories($dir);
        $icon = File::get(public_path("images/file-type-icons/file.svg"));

        $links = [];
        if ($dir != "" && $dir != "/") {
            $concat = "";
            foreach (explode("/", ltrim($dir, "/")) as $dt) {
                $concat .= "/" . $dt;
                $links[] = [
                    "name" => $dt,
                    "path" => $concat
                ];
            }
        }
        $results = [];
        foreach ($directories as $file) {
            $icon_url = url("images/file-type-icons/folder.png");
            $results[] = [
                "name" => File::basename($path . $file),
                "size" => File::size($path . $file),
                "type" => File::type($path . $file),
                "mim-type" => File::mimeType($path . $file),
                "icon" => $icon_url,
                "path" => $file
            ];
        }
        foreach ($files as $file) {
            if (substr($file, 0, 1) === ".") {
                continue;
            }
            if (substr(File::mimeType($path . $file), 0, 5) == 'image') {
                $icon_url = url("storage/" . $file);
            } else {
                $icon = str_replace("{{type}}", strtoupper(File::extension($path . $file)), $icon);
                $icon_path = public_path("images/file-type-icons/file-" . File::extension($path . $file) . ".svg");
                if (!File::exists($icon_path)) {
                    File::put($icon_path, $icon);
                }
                $icon_url = url("images/file-type-icons/file-" . File::extension($path . $file) . ".svg");
            }
            $results[] = [
                "name" => File::basename($path . $file),
                "size" => File::size($path . $file),
                "type" => File::type($path . $file),
                "mim-type" => File::mimeType($path . $file),
                "icon" => $icon_url,
                "path" => $dir . $file
            ];
        }

        return view("admin.file-manager.index",    [
            "disk" => $disk,
            "prefix" => $dir,
            "results" => $results,
            "links" => $links
        ]);
    }

    public function newFolder(Request $request)
    {
        $dir = $request->dir;
        $folder = $request->folder;
        $disk = Storage::disk('public');
        $disk->makeDirectory($dir . "/" . $folder);
        return redirect()->route('admin.files', ["dir" => $dir])
            ->withSuccess(__('Folder created successfully.'));
    }
    public function newFile(Request $request)
    {
        $dir = $request->dir;
        $folder = $request->folder;
        $disk = Storage::disk('public');
        $path = $disk->path('');
        if ($request->file('file')) {
            $file = $request->file('file');
            $filename = date('YmdHi') . Str::slug($file->getClientOriginalName()).".".$file->extension();
            $file->move($path . $dir, $filename);
        }

        return redirect()->route('admin.files', ["dir" => $dir])
            ->withSuccess(__('File Uploaded successfully.'));
    }

    public function ckUpload(Request $request)
    {
        $dir = "/uploads";
        $disk = Storage::disk('public');
        $path = $disk->path('');
        $filename = date('YmdHi') . ".jpeg";
        if ($request->file('upload')) {
            $file = $request->file('upload');
            $file->move($path . $dir, $filename);
        }

        return response()->json([
            'default'=> url("/storage/uploads/".$filename),
            '160'=> url("/storage/uploads/".$filename),
            '500'=> url("/storage/uploads/".$filename),
            '1000'=> url("/storage/uploads/".$filename),
            '1052'=> url("/storage/uploads/".$filename)
        ]);
    }

    public function uploader(Request $request)
    {
        $dir = "/uploads";
        $disk = Storage::disk('public');
        $path = $disk->path('');
        $filename = date('YmdHi') . ".jpeg";
        if ($request->file('upload')) {
            $file = $request->file('upload');
            $file->move($path . $dir, $filename);
        }

        return response()->json([
            "path"=>"/storage/uploads/".$filename,
            "url"=>("/storage/uploads/".$filename),
        ]);
    }
}
