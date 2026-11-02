<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsersController extends Controller
{
    /**
     * Display a listing of registered app users.
     */
    public function index(Request $request): View
    {
        $users = User::with('astrologyProfiles')
            ->orderBy('id', 'DESC')
            ->paginate(15);

        return view('admin.users.index_users', compact('users'))
            ->with('i', ($request->input('page', 1) - 1) * 15);
    }

    /**
     * Display the specified user and their astrology profiles.
     */
    public function show(int $id): View
    {
        $user = User::with('astrologyProfiles')->findOrFail($id);

        return view('admin.users.show', compact('user'));
    }
}
