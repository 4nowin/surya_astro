<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Redirect, Response;
use Jenssegers\Agent\Agent;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use App\Models\Payment;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Http\Controllers\Controller;
use App\Mail\TicketMail;
use App\Models\Coupon;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\OrderDetailTicket;
use App\Models\Promoter;
use App\Models\Venue;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\Inquiry;

class RazorpayController extends Controller
{
    function checkout(Request $request)
    {
      //$user = Auth::guard("web")->user();
      $key = $_ENV["RAZORPAY_API_KEY"];
      $api = new Api($_ENV["RAZORPAY_API_KEY"], $_ENV["RAZORPAY_API_SECRET"]);
      $event_id = $request->event_id;
      $promoter_id = $request->promoter_id;
      $event = Event::where(["id" => $event_id])->first();
      $promoter = Promoter::where(["id" => $promoter_id])->first();
      $items = $request->items;
      $name = $request->name;
      $mobile = $request->mobile;
      $email = $request->email;
  
      $order_details = [];
      $total_price = 0;
      foreach ($items as $item) {
        if (!isset($item["quantity"]) || $item["quantity"] > 0 == false) continue;
        $event_ticket = EventTicket::where(["id" => $item["event_ticket_id"]])->first();
        $amount = $event_ticket->price * $item["quantity"];
        $total_price += $amount;
  
        $order_detail = new OrderDetail();
        $order_detail->event_ticket_id = $item["event_ticket_id"];
        $order_detail->quantity = $item["quantity"];
        $order_detail->price = $amount;
        $order_detail->rate = $event_ticket->price;
  
        $order_details[] = $order_detail;
      }
  
      if($total_price === 0){
        return $this->freeCheckout($request);
      }
  
      //coupon
      $coupon_code = $request->coupon;
      $coupon = Coupon::where("code", $coupon_code)
        ->where("type", "%")
        ->where("remaining_count", ">", 0)->first();
      $discounted_amount = $total_price;
      $discount = 0;
      if($coupon){
        $coupon->remaining_count = $coupon->remaining_count - 1;
  
        $discount = ($coupon->discount/100)*$total_price;
        $discounted_amount = $total_price - $discount;
  
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
      $payment->amount = $total_price;
      $payment->status = "CREATED";
      if($coupon){
        $payment->discount = $discount;
        $payment->coupon = $coupon->code;
      }
      $payment->save();
  
      //create order
      $order = new Order();
      $order->event_id = $request->event_id;
      $order->promoter_id = $promoter ? $promoter->id : null;
      $order->name = $name;
      $order->email = $email;
      $order->mobile = $mobile;
      $order->status = "PENDING";
      $order->total_price = $total_price;
      $order->payment_id = $payment->id;
      //$order->user_id = $user->id;
      if($coupon){
        $order->discount = $discount;
      }
      $order->save();
  
      //update order
      $order->payment_id = $payment->id;
      $order->save();
  
      //create razorpay payment
      $orderData = [
        'receipt'         => $order->id,
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
      foreach ($order_details as $order_detail) {
        $order_detail->order_id = $order->id;
        $order_detail->save();
      }
  
      return view("front.payment.razorpay.checkout",  [
        "order_details" => $razorpay_order,
        "key" => $key,
        "customer_details" => [
          "name" => $name,
          "email" => $email,
          "mobile" => $mobile
        ],
        "event" => $event
      ]);
    }
  
    function freeCheckout(Request $request){
      $promoter_id = $request->promoter_id;
      $promoter = Promoter::where(["id" => $promoter_id])->first();
      $items = $request->items;
      $name = $request->name;
      $mobile = $request->mobile;
      $email = $request->email;
  
      $order_details = [];
      $total_price = 0;
      foreach ($items as $item) {
        if (!isset($item["quantity"]) || $item["quantity"] > 0 == false) continue;
        $event_ticket = EventTicket::where(["id" => $item["event_ticket_id"]])->first();
        $amount = $event_ticket->price * $item["quantity"];
        $total_price += $amount;
  
        $order_detail = new OrderDetail();
        $order_detail->event_ticket_id = $item["event_ticket_id"];
        $order_detail->quantity = $item["quantity"];
        $order_detail->price = $amount;
        $order_detail->rate = $event_ticket->price;
  
        $order_details[] = $order_detail;
      }
  
  
      //create order
      $order = new Order();
      $order->event_id = $request->event_id;
      $order->promoter_id = $promoter ? $promoter->id : null;
      $order->name = $name;
      $order->email = $email;
      $order->mobile = $mobile;
      $order->status = "SUCCESS";
      $order->total_price = $total_price;
      //$order->user_id = $user->id;
    
      $order->save();
  
      //update order
      $order->save();
  
      //save order details
      foreach ($order_details as $order_detail) {
        $order_detail->order_id = $order->id;
        $order_detail->save();
      }
      $status = "SUCCESS";
  
      try {
        Mail::to($order->email)->send(new TicketMail($order->id));
      } catch (Exception $err) {
        dd($err);
      }
  
      return view("front.payment.complete",  [
        "status" => $status,
        "order" => $order
      ]);
  
    }
  
    function complete(Request $request)
    {
      if ($request->exists('razorpay_payment_id') === false) {
        abort(404);
      }
      $payment = Payment::where(["rzp_order_id" => $request->razorpay_order_id])->first();
      if (!$payment) {
        abort(404, "Broken Link");
      }
      $order = Order::where(["payment_id" => $payment->id])->first();
      $inquiry = Inquiry::where(["id" => $order->inquiry_id])->first();

      $status = "FAILED";
      try {
        $api = new Api($_ENV["RAZORPAY_API_KEY"], $_ENV["RAZORPAY_API_SECRET"]);
        $attributes = array(
          'razorpay_order_id' => $payment->rzp_order_id,
          'razorpay_payment_id' => $_POST['razorpay_payment_id'],
          'razorpay_signature' => $_POST['razorpay_signature']
        );
        $api->utility->verifyPaymentSignature($attributes);
        $order->status = "SUCCESS";
        $payment->status = "SUCCESS";
        $inquiry->payment_status = "SUCCESS";
        $status = "SUCCESS";
      } catch (SignatureVerificationError $e) {
        $error = 'Razorpay Error : ' . $e->getMessage();
        $order->status = "FAILED";
        $payment->status = "FAILED";
        $inquiry->payment_status = "FAILED";
        $status = "FAILED";
      }
      $payment->save();
      $order->save();
      $inquiry->save();
      return view("Frontend.payment.complete",  [
        "status" => $status,
        "order" => $order
      ]);
    }
  
    public function webhook(Request $request)
    {
      Log::channel('payment-notification')->info(json_encode($request->all(), JSON_PRETTY_PRINT));
      //handle payment captured
      if ($request->event === "payment.captured") {
        $success = true;
        $rzp_payment_id = $request->payload["payment"]["entity"]["id"];
        $rzp_order_id = $request->payload["payment"]["entity"]["order_id"];
        $payment = Payment::where(["rzp_order_id" => $rzp_order_id])->first();
        if (!$payment) {
          abort(404);
        }
        if ($payment->status == "SUCCESS") {
          abort(404);
        }
  
        $order = Order::where(["payment_id" => $payment->id])->first();
        $payment->rzp_payment_id = $rzp_payment_id;
        if ($success === true) {
          $payment->status = "SUCCESS";
          $order->status = "SUCCESS";
          //send email
          try {
            Mail::to($order->email)->send(new TicketMail($order->id));
          } catch (Exception $err) {
          }
        } else {
          $payment->status = "FAILED";
          $order->status = "FAILED";
        }
        $payment->save();
        $order->save();
      }
    }
  
    public function checkUserDiscount(Request $request)
    {
      $code = $request->coupon;
      $coupon = Coupon::where("code", $code)
        ->where("type", "%")
        ->where("remaining_count", ">", 0)->first();
      if (!$coupon) {
        return abort(400, "Invalid code");
      }
      return response()->json(["status" => 200, "message" => "code verified", "discount" => $coupon->discount]);
    }
}
