<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
  
    use AuthenticatesUsers;
  
    protected $redirectTo = '/admin';
   
 
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }
   
    public function login(Request $request)
    {   
        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if(Auth::guard('admin')->attempt(['email' => $request->email, 'password' => $request->password], $request->get('remember'))){
            return redirect("/admin");
        } else {
            return redirect("admin/login")->with('error','Email-Address And Password Are Wrong.');
        }          
    }
}
