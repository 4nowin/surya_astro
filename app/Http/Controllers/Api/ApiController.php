<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inquiry;
use App\Models\Payment;
use App\Models\Promoter;
use App\Models\Coupon;
use App\Models\OrderDetail;
use App\Models\Order;
use Razorpay\Api\Api;

class ApiController extends Controller
{
  
  public function index(Request $request)
  {
    $inquiries = Inquiry::orderBy('id', 'DESC')->paginate(10);
    // return $inquiries;
    return Inquiry::all();
  }


  public function create()
  {
    $inquiries = Inquiry::get();
    return view('admin.inquiry.create', compact('inquiries'));
  }


  public function store(Request $request)
  {
    $this->validate($request, [
      'name' => 'required|unique:roles,name',
      'permission' => 'required',
    ]);

    $role = Inquiry::create(['name' => $request->get('name')]);
    $role->syncPermissions($request->get('permission'));

    return redirect()->route('enquiry.index')
      ->with('success', 'Role created successfully');
  }

  public function show($id)
  {
    $inquiry = Inquiry::where('id', $id)->first();
    return view('admin.inquiry.show', compact('inquiry'));
  }

  public function edit(Inquiry $inquiries)
  {
    return view('admin.inquiry.edit', compact('inquiries'));
  }


  public function update($id)
  {
    Inquiry::where('id', $id)->update(array('status' => 1));

    return redirect()->route('enquiry.index')
      ->with('success', 'Enquiry updated successfully');
  }

  public function completed(Request $request)
  {
    $inquiries = Inquiry::orderBy('name', 'DESC')->paginate(5);
    return view('admin.inquiry.completed', compact('inquiries'))
      ->with('i', ($request->input('page', 1) - 1) * 5);
  }

  public function destroy($id)
  {
    Inquiry::where('id', $id)->update(array('status' => 2));

    return redirect()->route('enquiry.index')
      ->with('success', 'Enquiry deleted successfully');
  }
}
