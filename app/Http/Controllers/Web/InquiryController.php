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
            "course" => $request->course,
            "month" => $request->month,
            "accommodation" => $request->accommodation,
            "from" => $request->from,
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

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $inquiries = Inquiry::get();
        return view('admin.inquiry.create', compact('inquiries'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
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

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $inquiry = Inquiry::where('id', $id)->first();
        return view('admin.inquiry.show', compact('inquiry'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Inquiry $inquiries)
    {
        return view('admin.inquiry.edit', compact('inquiries'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
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

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Inquiry::where('id', $id)->update(array('isDeleted' => 1));

        return redirect()->route('enquiry.index')
                        ->with('success','Enquiry deleted successfully');
    }
}
