<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inquiry;
use App\Models\Payment;
use App\Models\Promoter;
use App\Models\Coupon;
use App\Models\OrderDetail;
use App\Models\Order;
use Razorpay\Api\Api;
class InquiryController extends Controller
{
    public function saveInquiry(Request $request)
    {
      $amount = 0;
      if($request->for == "Kundli")
      {
        $amount = 101;
      }   
      else if($request->for == "Horoscope")
      {
          if($request->frequency == "daily")
          {
            $amount = 11;
          }
          else if($request->frequency == "weekly") 
          {
            $amount = 51;
          }
          else if($request->frequency == "monthly") 
          {
            $amount = 251;
          }
          else if($request->frequency == "yearly") 
          {
            $amount = 2551;
          }
          else
          {
            $amount = $request->price;
          }
      }
      else if($request->for == "Palmistry")
      {
        $amount = 51;
      }
      else if($request->for == "Vastu")
      {
        $amount = 101;
      }
      else{
        $amount = $request->amount;
      }
        
        $inquiry = Inquiry::create([
            "name" => $request->name,
            "email" => $request->email,
            "phone" => $request->phone,
            "gender" => $request->gender,
            "country" => $request->country,
            "frequency" => $request->frequency,
            "date_of_birth" => $request->date_of_birth,
            "place_of_birth" => $request->place_of_birth,
            "for" => $request->for,
            "amount" => $amount,
            "type" => $request->type,
            "payment_status" => "PENDING",
            "message" => $request->message,
        ]);
      
      $key = $_ENV["RAZORPAY_API_KEY"];
      $api = new Api($_ENV["RAZORPAY_API_KEY"], $_ENV["RAZORPAY_API_SECRET"]);
      $promoter_id = $request->promoter_id;
      $promoter = Promoter::where(["id" => $promoter_id])->first();

      $name = $request->for;
      $mobile = $request->phone;
      $email = $request->email;
  
  
      $order_detail = new OrderDetail();
      $order_detail->event_ticket_id = $inquiry->id;
      $order_detail->price = $amount;

  
      //coupon
      $coupon_code = $request->coupon;
      $coupon = Coupon::where("code", $coupon_code)
        ->where("type", "%")
        ->where("remaining_count", ">", 0)->first();

      $discounted_amount = $amount;
      $discount = 0;
      if($coupon){
        $coupon->remaining_count = $coupon->remaining_count - 1;
  
        $discount = ($coupon->discount/100)*$amount;
        $discounted_amount = $amount - $discount;
  
        $coupon->save();
      }
  
      //create payment
      $payment = new Payment();
      $payment->rzp_order_id = "";
      $payment->payment_method = "Razorpay";
      $payment->order_id = 0;
      $payment->user = $name;
      $payment->phone = $mobile;
      $payment->email = $email;
      $payment->amount = $discounted_amount;
      $payment->status = "CREATED";
      if($coupon){
        $payment->discount = $discount;
        $payment->coupon = $coupon->code;
      }
      $payment->save();
  
      //create order
      $order = new Order();
      $order->inquiry_id = $inquiry->id;
      $order->promoter_id = $promoter ? $promoter->id : null;
      $order->name = $name;
      $order->email = $email;
      $order->mobile = $mobile;
      $order->status = "PENDING";
      $order->total_price = $amount;
      $order->payment_id = $payment->id;
      //$order->user_id = $user->id;
      if($coupon){
        $order->discount = $discount;
      }
  
      //update order
      $order->payment_id = $payment->id;
      $order->save();
  
      //create razorpay payment
      $orderData = [
        'receipt'         => strval($order->id),
        'amount'          => round($discounted_amount * 100),
        'currency'        => 'INR',
        'notes'           => [
          "order_id" => $order->id
        ]
      ];
      $razorpay_order = $api->order->create($orderData);
  
      //update payment
      $payment->order_id = $order->id;
      $payment->rzp_order_id = $razorpay_order->id;
      $payment->save();
  
      //save order details
      $order_detail->order_id = $order->id;
      $order_detail->save();
  
      return view("Frontend.payment.razorpay.checkout",  [
        "order_details" => $razorpay_order,
        "key" => $key,
        "customer_details" => [
          "name" => $name,
          "email" => $email,
          "mobile" => $mobile
        ],
        "inquiry" => $inquiry
      ]);

    }

    public function index(Request $request)
    {   
        $inquiries = Inquiry::orderBy('id','DESC')->paginate(10);
        return view('admin.inquiry.index',compact('inquiries'))
            ->with('i', ($request->input('page', 1) - 1) * 10);
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

 
    public function update($id)
    {
        Inquiry::where('id', $id)->update(array('status' => 1));

        return redirect()->route('enquiry.index')
                        ->with('success','Enquiry updated successfully');
    }

    public function completed(Request $request)
    {
        $inquiries = Inquiry::orderBy('name','DESC')->paginate(5);
        return view('admin.inquiry.completed',compact('inquiries'))
            ->with('i', ($request->input('page', 1) - 1) * 5);
    }

    public function destroy($id)
    {
        Inquiry::where('id', $id)->update(array('status' => 2));

        return redirect()->route('enquiry.index')
                        ->with('success','Enquiry deleted successfully');
    }
}
