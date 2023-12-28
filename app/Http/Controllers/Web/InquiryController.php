<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inquiry;

class InquiryController extends Controller
{
    public function saveInquiry(Request $request)
    {
        $inquiry = Inquiry::create([
            "name" => $request->name,
            "email" => $request->email,
            "phone" => $request->phone,
            "gender" => $request->gender,
            "country" => $request->country,
            "date_of_birth" => $request->date_of_birth,
            "place_of_birth" => $request->place_of_birth,
            "for" => $request->for,
            "message" => $request->message,
        ]);

        return redirect()->back()->with('inquiry-success', 'Yay, Your Query has been submitted you will be contacted soon');
    }

    public function index(Request $request)
    {   
        $inquiries = Inquiry::orderBy('name','DESC')->paginate(5);
        return view('admin.inquiry.index',compact('inquiries'))
            ->with('i', ($request->input('page', 1) - 1) * 5);
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
                        ->with('success','Role created successfully');
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

 
    public function update(Inquiry $inquiries, Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'permission' => 'required',
        ]);

        $inquiries->update($request->only('name'));

        $inquiries->syncPermissions($request->get('permission'));

        return redirect()->route('enquiry.index')
                        ->with('success','Enquiry updated successfully');
    }

    public function completed($id)
    {
        Inquiry::where('id', $id)->update(array('status' => 1));

        return redirect()->route('enquiry.index')
                        ->with('success','Enquiry Completed successfully');
    }

    public function destroy($id)
    {
        Inquiry::where('id', $id)->update(array('status' => 2));

        return redirect()->route('enquiry.index')
                        ->with('success','Enquiry deleted successfully');
    }
}
