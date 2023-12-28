<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Location;

class LocationsController extends Controller
{
    public function index(Request $request)
    {
        $where = [];
        $locations = Location::select(["*"]);
        if(isset($request->query()["keyword"]) && $request->query()["keyword"]!=""){
            $locations->where(function ($query) {
                global $request;
                $query->orWhere("locations.name","like", "%{$request->query()["keyword"]}%");
            });
        }
        $locations = $locations->latest()->paginate(10)->appends($request->query());

        return view('admin.locations.index', compact('locations'));
    }

    public function create()
    {
        return view('admin.locations.create');
    }

    public function store(Request $request)
    {
        Location::create(array_merge($request->only('name', 'country'),[
            'user_id' => auth()->id()
        ]));

        return redirect()->route('locations.index')
            ->withSuccess(__('Location created successfully.'));
    }

    public function edit(Location $location)
    {
        return view('admin.locations.edit', [
            'location' => $location
        ]);
    }


    public function update(Request $request, Location $location)
    {
        $location->update($request->only('name', 'country'));

        return redirect()->route('locations.index')
            ->withSuccess(__('Location updated successfully.'));
    }


    public function destroy(Location $location)
    {
        $location->delete();

        return redirect()->route('locations.index')
            ->withSuccess(__('Location deleted successfully.'));
    }
}
