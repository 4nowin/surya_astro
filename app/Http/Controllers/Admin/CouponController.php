<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;

class CouponController extends Controller
{

    public function index()
    {
        $coupons = Coupon::latest()->paginate(10);
        return view('admin.coupon.index', compact('coupons'));
    }

    public function create()
    {
        return view('admin.coupon.create');
    }

    public function store(Request $request)
    {
        $image = "";
        if ($request->file('image')) {
            $file = $request->file('image');
            $filename = date('YmdHi') . '.' . $file->extension();
            $file->move(public_path('storage/uploads'), $filename);
            $image = $filename;
        }

        $cover = "";
        if ($request->file('cover')) {
            $file = $request->file('cover');
            $filename = date('YmdHi') . '.' . $file->extension();
            $file->move(public_path('storage/uploads'), $filename);
            $cover = $filename;
        }
        Coupon::create(array_merge($request->only(
            'type',
            'code',
            'discount',
            'remaining_count',
        )));

        return redirect()->route('coupon.index')
            ->withSuccess(__('Coupon created successfully.'));
    }

    public function show(Coupon $coupon)
    {
        return view('admin.coupon.show', [
            'coupon' => $coupon
        ]);
    }

    public function edit(Coupon $coupon)
    {
        return view('admin.coupon.edit', [
            'coupon' => $coupon
        ]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $coupon->update($request->only(
            'type',
            'code',
            'discount',
            'remaining_count',
        ));

        return redirect()->route('coupon.index')
            ->withSuccess(__('Coupon updated successfully.'));
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return redirect()->route('coupon.index')
            ->withSuccess(__('Coupon deleted successfully.'));
    }
}
