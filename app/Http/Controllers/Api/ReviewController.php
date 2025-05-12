<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Review;

class ReviewController extends Controller
{
    public function store(Request $request)
{
    $request->validate([
        'astrologer_id' => 'required|exists:astrologers,id',
        'rating' => 'required|integer|min:1|max:5',
        'comment' => 'nullable|string',
    ]);

    $review = Review::create([
        'astrologer_id' => $request->astrologer_id,
        'user_id' => auth()->id(),
        'rating' => $request->rating,
        'comment' => $request->comment,
    ]);

    return response()->json($review, 201);
}

public function index($astrologerId)
{
    $reviews = Review::with('user')
        ->where('astrologer_id', $astrologerId)
        ->latest()
        ->get();

    return response()->json($reviews);
}
}
