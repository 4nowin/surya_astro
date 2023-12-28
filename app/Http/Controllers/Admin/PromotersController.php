<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Promoter;

class PromotersController extends Controller
{
    public function index(Request $request)
    {
        $where = [];
        $promoters = Promoter::select(["*"]);
        if(isset($request->query()["keyword"]) && $request->query()["keyword"]!=""){
            $promoters->where(function ($query) {
                global $request;
                $query->orWhere("promoters.name","like", "%{$request->query()["keyword"]}%");
            });
        }
        $promoters = $promoters->latest()->paginate(10)->appends($request->query());

        return view('admin.promoters.index', compact('promoters'));
    }

    public function create()
    {
        return view('admin.promoters.create');
    }

    public function store(Request $request)
    {
        Promoter::create(array_merge($request->only('name', 'commission'),[
            'user_id' => auth()->id()
        ]));

        return redirect()->route('promoters.index')
            ->withSuccess(__('Promoter created successfully.'));
    }

    public function show(Promoter $promoter)
    {
        return view('admin.promoters.show', [
            'promoter' => $promoter
        ]);
    }

    public function edit(Promoter $promoter)
    {
        return view('admin.promoters.edit', [
            'promoter' => $promoter
        ]);
    }

    public function update(Request $request, Promoter $promoter)
    {
        $promoter->update($request->only('name', 'commission'));

        return redirect()->route('promoters.index')
            ->withSuccess(__('Promoter updated successfully.'));
    }

    public function destroy(Promoter $promoter)
    {
        $promoter->delete();

        return redirect()->route('promoters.index')
            ->withSuccess(__('Promoter deleted successfully.'));
    }
}
