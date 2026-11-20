<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pooja;
use Illuminate\Http\Request;

class AstrologerPoojaApiController extends Controller
{
    /**
     * List authenticated astrologer's own Poojas.
     */
    public function index(Request $request)
    {
        $admin = $request->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $query = Pooja::where('admin_id', $admin->id);

        // Language filter (en, hi)
        if ($lang = $request->query('language') ?: $request->query('lang')) {
            if ($lang !== 'all') {
                $query->where('language', $lang);
            }
        }

        // Active status filter
        if ($request->has('is_active')) {
            $isActive = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('active', $isActive ? 1 : 0);
            }
        } elseif ($request->has('active')) {
            $isActive = filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('active', $isActive ? 1 : 0);
            }
        }

        // Search query
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('tag', 'like', "%{$search}%");
            });
        }

        $poojas = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $poojas,
            'meta' => [
                'total' => $poojas->count(),
            ],
        ]);
    }

    /**
     * Store a newly created Pooja for the authenticated astrologer.
     */
    public function store(Request $request)
    {
        $admin = $request->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'tag' => 'nullable|string|max:100',
            'language' => 'nullable|string|in:en,hi',
            'excerpt' => 'nullable|string',
            'description' => 'nullable|string',
            'start_date' => 'nullable|string',
            'end_date' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'active' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $image = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = 'pooja_' . $admin->id . '_' . date('YmdHis') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('storage/uploads'), $filename);
            $image = $filename;
        }

        $pooja = Pooja::create([
            'admin_id' => $admin->id,
            'title' => $validated['title'],
            'tag' => $validated['tag'] ?? 'Pooja',
            'language' => $validated['language'] ?? 'en',
            'excerpt' => $validated['excerpt'] ?? null,
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'price' => (int) $validated['price'],
            'original_price' => isset($validated['original_price']) ? (int) $validated['original_price'] : (int) $validated['price'],
            'active' => $request->boolean('active', true),
            'image' => $image,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pooja created successfully.',
            'data' => $pooja->fresh(),
        ], 201);
    }

    /**
     * Show a single Pooja owned by the authenticated astrologer.
     */
    public function show(Request $request, $id)
    {
        $admin = $request->user();
        $pooja = Pooja::find($id);

        if (!$pooja) {
            return response()->json(['success' => false, 'message' => 'Pooja not found.'], 404);
        }

        if ((int) $pooja->admin_id !== (int) $admin->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action. You do not own this Pooja.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $pooja,
        ]);
    }

    /**
     * Update an existing Pooja owned by the authenticated astrologer.
     */
    public function update(Request $request, $id)
    {
        $admin = $request->user();
        $pooja = Pooja::find($id);

        if (!$pooja) {
            return response()->json(['success' => false, 'message' => 'Pooja not found.'], 404);
        }

        if ((int) $pooja->admin_id !== (int) $admin->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action. You do not own this Pooja.'], 403);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'tag' => 'nullable|string|max:100',
            'language' => 'nullable|string|in:en,hi',
            'excerpt' => 'nullable|string',
            'description' => 'nullable|string',
            'start_date' => 'nullable|string',
            'end_date' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'active' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'remove_image' => 'nullable|boolean',
        ]);

        $data = [];
        foreach (['title', 'tag', 'language', 'excerpt', 'description', 'start_date', 'end_date'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->input($field);
            }
        }

        if ($request->has('price')) {
            $data['price'] = (int) $request->input('price');
        }
        if ($request->has('original_price')) {
            $data['original_price'] = (int) $request->input('original_price');
        }
        if ($request->has('active')) {
            $data['active'] = $request->boolean('active');
        }

        // Image handling: preserve existing image unless replaced or explicitly removed
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = 'pooja_' . $admin->id . '_' . date('YmdHis') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('storage/uploads'), $filename);

            $this->safeDeleteUpload($pooja->image);

            $data['image'] = $filename;
        } elseif ($request->boolean('remove_image')) {
            $this->safeDeleteUpload($pooja->image);
            $data['image'] = null;
        }

        $pooja->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Pooja updated successfully.',
            'data' => $pooja->fresh(),
        ]);
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleActive(Request $request, $id)
    {
        $admin = $request->user();
        $pooja = Pooja::find($id);

        if (!$pooja) {
            return response()->json(['success' => false, 'message' => 'Pooja not found.'], 404);
        }

        if ((int) $pooja->admin_id !== (int) $admin->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action. You do not own this Pooja.'], 403);
        }

        $pooja->active = !$pooja->active;
        $pooja->save();

        return response()->json([
            'success' => true,
            'message' => $pooja->active ? 'Pooja activated.' : 'Pooja deactivated.',
            'data' => $pooja,
        ]);
    }

    /**
     * Upload / replace Pooja image.
     */
    public function uploadImage(Request $request, $id)
    {
        $admin = $request->user();
        $pooja = Pooja::find($id);

        if (!$pooja) {
            return response()->json(['success' => false, 'message' => 'Pooja not found.'], 404);
        }

        if ((int) $pooja->admin_id !== (int) $admin->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action. You do not own this Pooja.'], 403);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $file = $request->file('image');
        $filename = 'pooja_' . $admin->id . '_' . date('YmdHis') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('storage/uploads'), $filename);

        $this->safeDeleteUpload($pooja->image);

        $pooja->image = $filename;
        $pooja->save();

        return response()->json([
            'success' => true,
            'message' => 'Pooja image uploaded successfully.',
            'data' => $pooja->fresh(),
        ]);
    }

    /**
     * Remove Pooja image.
     */
    public function removeImage(Request $request, $id)
    {
        $admin = $request->user();
        $pooja = Pooja::find($id);

        if (!$pooja) {
            return response()->json(['success' => false, 'message' => 'Pooja not found.'], 404);
        }

        if ((int) $pooja->admin_id !== (int) $admin->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action. You do not own this Pooja.'], 403);
        }

        $this->safeDeleteUpload($pooja->image);

        $pooja->image = null;
        $pooja->save();

        return response()->json([
            'success' => true,
            'message' => 'Pooja image removed.',
            'data' => $pooja->fresh(),
        ]);
    }

    /**
     * Delete Pooja.
     */
    public function destroy(Request $request, $id)
    {
        $admin = $request->user();
        $pooja = Pooja::find($id);

        if (!$pooja) {
            return response()->json(['success' => false, 'message' => 'Pooja not found.'], 404);
        }

        if ((int) $pooja->admin_id !== (int) $admin->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action. You do not own this Pooja.'], 403);
        }

        $this->safeDeleteUpload($pooja->image);
        $pooja->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pooja deleted successfully.',
        ]);
    }

    /**
     * Delete upload if local user-created upload.
     */
    protected function safeDeleteUpload(?string $filename): void
    {
        if (empty($filename)) {
            return;
        }

        $basename = basename($filename);
        if (str_starts_with($basename, 'pooja_')) {
            $path = public_path('storage/uploads/' . $basename);
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }
}
