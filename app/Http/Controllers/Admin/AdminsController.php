<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin;
use Spatie\Permission\Models\Role;
use DB;
use Hash;
use Illuminate\Support\Arr;

class AdminsController extends Controller
{

    public function index(Request $request)
    {
        $data = Admin::orderBy('id', 'DESC')->paginate(5);
        return view('admin.users.index', compact('data'))
            ->with('i', ($request->input('page', 1) - 1) * 5);
    }

    public function create()
    {
        $roles = Role::pluck('name', 'id')->all();
        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|same:confirm-password',
            'roles' => 'required'
        ]);

        $input = $request->all();
        $input['password'] = Hash::make($input['password']);
        unset($input['roles']);

        $user = Admin::create($input);

        // Get role names and assign them
        $roles = [];
        foreach ($request->input('roles') as $roleId) {
            $role = Role::find($roleId);
            if ($role) {
                $roles[] = $role->name;
            }
        }
        $user->assignRole($roles);

        return redirect()->route('admins.index')
            ->with('success', 'Admin created successfully');
    }

    public function show($id)
    {
        $user = Admin::find($id);
        return view('admin.users.show', compact('user'));
    }


    public function edit($id)
    {
        $user = Admin::find($id);
        $roles = Role::pluck('name', 'id')->toArray();
        $userRole = $user->roles->pluck('id')->toArray();

        return view('admin.users.edit', compact('user', 'roles', 'userRole'));
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:admins,email,' . $id,
            'password' => 'same:confirm-password',
            'roles' => 'required'
        ]);

        // Debug: Check what roles are being received
        \Log::info('Roles received:', ['roles' => $request->input('roles')]);

        $input = $request->all();
        if (!empty($input['password'])) {
            $input['password'] = Hash::make($input['password']);
        } else {
            $input = Arr::except($input, array('password'));
        }

        unset($input['roles']);

        $user = Admin::find($id);
        $user->update($input);

        // Remove existing roles
        DB::table('model_has_roles')->where('model_id', $id)->delete();

        // Handle roles
        $roles = [];
        foreach ($request->input('roles') as $roleId) {
            $role = Role::find($roleId);
            if ($role) {
                $roles[] = $role->name;
            }
        }

        // Debug: Check what roles are being assigned
        \Log::info('Roles to assign:', ['roles' => $roles]);

        $user->assignRole($roles);

        return redirect()->route('admins.index')
            ->with('success', 'User updated successfully');
    }

    public function destroy($id)
    {
        Admin::find($id)->delete();
        return redirect()->route('users.index')
            ->with('success', 'Admin deleted successfully');
    }
}
