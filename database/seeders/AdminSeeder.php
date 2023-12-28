<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Admin;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = Admin::create([
            'name' => 'Navgrah Admin', 
            'email' => 'webmaster@navgarah.com',
            'password' => bcrypt('12345678')
        ]);
    

        $role = Role::create(['name' => 'Admin', "guard_name"=>"admin"]);
     
        $permissions = Permission::pluck('id','id')->all();
   
        $role->syncPermissions($permissions);

        $admin->assignRole([$role->id]);
    }
}
