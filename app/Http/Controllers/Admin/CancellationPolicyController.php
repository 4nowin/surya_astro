<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cancellation;

class CancellationPolicyController extends Controller
{
    public function index()
    {
        $cancellations = Cancellation::latest()->paginate(10);
        return view('admin.retreat.cancellation.index', compact('cancellations'));
    }

   
    public function create()
    {
        return view('admin.retreat.cancellation.create');
    }

   
    public function store(Request $request)
    {
        Cancellation::create(array_merge($request->only(
            'name', 
            'description',
        )));

        return redirect()->route('cancellation.index')
            ->withSuccess(__('Cancellation Policy added successfully.'));
    }

   
    public function show(Cancellation $cancellation)
    {
        return view('admin.retreat.cancellation.show', [
            'cancellation' => $cancellation
        ]);
    }

    
    public function edit(Cancellation $cancellation)
    {
        return view('admin.retreat.cancellation.edit', [
            'cancellation' => $cancellation
        ]);
    }


    public function update(Request $request, Cancellation $cancellation)
    {
        $cancellation->update($request->only(
            'name', 
            'description',
        ));

        return redirect()->route('cancellation.index')
            ->withSuccess(__('Cancellation policy updated successfully.'));
    }

   
    public function destroy(Cancellation $cancellation)
    {
        $cancellation->delete();

        return redirect()->route('cancellation.index')
            ->withSuccess(__('Cancellation deleted successfully.'));
    }
}
