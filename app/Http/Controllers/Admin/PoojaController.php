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
        $homePriority = $request->input('home_priority');

        // Handle image
        $image = "";
        if ($request->file('image')) {
            $file = $request->file('image');
            $filename = date('YmdHi') . '.' . $file->extension();
            $file->move(public_path('storage/uploads'), $filename);
            $image = $filename;
        }

        // Shift other poojas if home_priority is set (1 to 4)
        if ($homePriority && in_array($homePriority, [1, 2, 3, 4])) {
            $this->shiftHomePriorities($homePriority);
        }

        // Create the pooja
        Pooja::create(array_merge(
            $request->only(
                'title',
                'tag',
                'language',
                'excerpt',
                'description',
                'start_date',
                'end_date',
                'price',
                'original_price'
            ),
            [
                'image' => $image,
                'home_priority' => $homePriority ?? null
            ]
        ));

        return redirect()->route('pooja.index')->withSuccess(__('Pooja created successfully.'));
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
        $homePriority = $request->input('home_priority');

        // If home_priority is changed, shift others
        if ($homePriority != $pooja->home_priority && in_array($homePriority, [1, 2, 3, 4])) {
            $this->shiftHomePriorities($homePriority, $pooja->id);
        }


        $data = $request->only(
            'title',
            'tag',
            'language',
            'excerpt',
            'description',
            'start_date',
            'end_date',
            'price',
            'original_price'
        );

        if ($request->file('image')) {
            $file = $request->file('image');
            $filename = date('YmdHi') . '.' . $file->extension();
            $file->move(public_path('storage/uploads'), $filename);
            $data['image'] = $filename;
        }

        $data['home_priority'] = $homePriority ?? null;

        $pooja->update($data);

        return redirect()->route('pooja.index')->withSuccess(__('Pooja updated successfully.'));
    }


    protected function shiftHomePriorities($startFrom, $excludeId = null)
    {
        $conflictingPoojas = Pooja::whereNotNull('home_priority')
            ->where('home_priority', '>=', $startFrom)
            ->where('home_priority', '<=', 4)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('home_priority', 'desc') // important: move from bottom up
            ->get();

        foreach ($conflictingPoojas as $pooja) {
            $newPriority = $pooja->home_priority + 1;
            if ($newPriority > 4) {
                $pooja->update(['home_priority' => null]);
            } else {
                $pooja->update(['home_priority' => $newPriority]);
            }
        }
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
