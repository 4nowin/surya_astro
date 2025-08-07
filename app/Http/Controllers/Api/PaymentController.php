<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Horoscope;
use Illuminate\Http\Request;
use Razorpay\Api\Api;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Models\Pooja;
use App\Models\User;
use App\Models\Order;
use App\Models\Astrologer;
use App\Services\FirebaseService;

class PaymentController extends Controller
{
    private function getUserByEmail($email)
    {
        return User::where('email', $email)->first();
    }

    private function createRazorpayOrder($amount)
    {
        $api = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));
        return $api->order->create([
            'receipt' => 'receipt_' . uniqid(),
            'amount' => (int) ($amount * 100),
            'currency' => 'INR',
        ]);
    }

    public function createPoojaOrder(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'bookingOrderId' => 'required|integer'
        ]);

        $pooja = Pooja::find($validated['bookingOrderId']);

        if (!$pooja) {
            return response()->json(['error' => 'Pooja not found'], 404);
        }

        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        try {
            $api = new Api(
                config('services.razorpay.key'),
                config('services.razorpay.secret')
            );

            // Create Razorpay order first
            $rzp_order = $api->order->create([
                'receipt' => 'receipt_' . uniqid(),
                'amount' => (int) ($pooja['price'] * 100),
                'currency' => 'INR',
            ]);

            // Save payment
            $payment = Payment::create([
                'rzp_order_id' => $rzp_order->id,
                'payment_method' => 'Razorpay',
                'order_id' => 0, // Will be updated after order creation
                'user_id' => $user->id,
                'payment_type' => 'Pooja',
                'payment_for' => $pooja->title,
                'amount' => $pooja['price'],
                'status' => 'CREATED',
            ]);

            // Save order
            $order = Order::create([
                'inquiry_id' => $pooja->id,
                'name' => $pooja->title,
                'status' => 'PENDING',
                'total_price' => $pooja['price'],
                'payment_id' => $payment->id,
            ]);

            // Optional: Update order_id in payment now that order exists
            $payment->order_id = $order->id;
            $payment->save();

            return response()->json([
                'key' => config('services.razorpay.key'),
                'order_id' => $rzp_order->id,
                'amount' => $rzp_order->amount,
                'currency' => $rzp_order->currency
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Payment initiation failed', 'message' => $e->getMessage()], 500);
        }
    }

    public function createDonationOrder(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'bookingOrderId' => 'required|integer'
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        try {
            $api = new Api(
                config('services.razorpay.key'),
                config('services.razorpay.secret')
            );

            // Create Razorpay order first
            $rzp_order = $api->order->create([
                'receipt' => 'receipt_' . uniqid(),
                'amount' => (int) ($request->amount * 100),
                'currency' => 'INR',
            ]);

            // Save payment
            Payment::create([
                'rzp_order_id' => $rzp_order->id,
                'payment_method' => 'Razorpay',
                'order_id' => 0, // Will be updated after order creation
                'user_id' => $user->id,
                'payment_type' => 'Donation',
                'payment_for' => $request->title,
                'amount' => $request->amount,
                'status' => 'CREATED',
            ]);

            return response()->json([
                'key' => config('services.razorpay.key'),
                'order_id' => $rzp_order->id,
                'amount' => $rzp_order->amount,
                'currency' => $rzp_order->currency
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Payment initiation failed', 'message' => $e->getMessage()], 500);
        }
    }

    public function addWalletMoney(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'bookingOrderId' => 'required|integer'
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        try {
            $api = new Api(
                config('services.razorpay.key'),
                config('services.razorpay.secret')
            );

            // Create Razorpay order first
            $rzp_order = $api->order->create([
                'receipt' => 'receipt_' . uniqid(),
                'amount' => (int) ($request->amount * 100),
                'currency' => 'INR',
            ]);

            // Save payment
            Payment::create([
                'rzp_order_id' => $rzp_order->id,
                'payment_method' => 'Razorpay',
                'order_id' => 0, // Will be updated after order creation
                'user_id' => $user->id,
                'payment_type' => 'Wallet',
                'payment_for' => $request->title,
                'amount' => $request->amount,
                'status' => 'CREATED',
            ]);

            return response()->json([
                'key' => config('services.razorpay.key'),
                'order_id' => $rzp_order->id,
                'amount' => $rzp_order->amount,
                'currency' => $rzp_order->currency
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Payment initiation failed', 'message' => $e->getMessage()], 500);
        }
    }

    public function createPremiumUser(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'bookingOrderId' => 'required|integer'
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        try {
            $api = new Api(
                config('services.razorpay.key'),
                config('services.razorpay.secret')
            );

            // Create Razorpay order first
            $rzp_order = $api->order->create([
                'receipt' => 'receipt_' . uniqid(),
                'amount' => (int) ($request->amount * 100),
                'currency' => 'INR',
            ]);

            // Save payment
            Payment::create([
                'rzp_order_id' => $rzp_order->id,
                'payment_method' => 'Razorpay',
                'order_id' => 0, // Will be updated after order creation
                'user_id' => $user->id,
                'payment_type' => 'Premium',
                'payment_for' => $request->title,
                'amount' => $request->amount,
                'status' => 'CREATED',
            ]);

            return response()->json([
                'key' => config('services.razorpay.key'),
                'order_id' => $rzp_order->id,
                'amount' => $rzp_order->amount,
                'currency' => $rzp_order->currency
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Payment initiation failed', 'message' => $e->getMessage()], 500);
        }
    }

    public function verifySignature(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $generated_signature = hash_hmac(
            'sha256',
            $request->razorpay_order_id . "|" . $request->razorpay_payment_id,
            config('services.razorpay.secret')
        );

        if ($generated_signature !== $request->razorpay_signature) {
            // Signature Mismatch
            return response()->json([
                'success' => false,
                'message' => 'Signature verification failed.'
            ], 400);
        }

        // Find existing payment using order ID
        $payment = Payment::where('rzp_order_id', $request->razorpay_order_id)->first();
        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment record not found.'
            ], 404);
        }

        if ($payment->payment_type === 'Wallet') {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'error' => 'User not found'], 404);
            }
            $user = $payment->user;
            $user->wallet_balance += $payment->amount;
            $user->save();
        }

        if ($payment->payment_type === 'Premium') {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'error' => 'User not found'], 404);
            }
            $user = $payment->user;
            $user->role = 'premium';
            $user->premium_started_at = now();
            $user->save();
        }

        if ($payment->payment_type === 'Pooja') {
            // Ensure order is loaded
            $order = $payment->order;

            if ($order) {
                $pooja = $order->pooja;

                if ($pooja && $pooja->admin && $pooja->admin->fcm_token) {
                    $title = "Pooja Booked";
                    $body = $pooja->title;

                    $data = [
                        'type' => 'pooja_booking',
                        'pooja_id' => (string) $pooja->id,
                        'order_id' => (string) $order->id,
                        'user_id' => (string) $payment->user_id,
                    ];

                    \Log::info('🔔 [LARAVEL] Sending pooja booking notification', [
                        'admin_id' => $pooja->admin->id,
                        'fcm_token' => $pooja->admin->fcm_token,
                        'data' => $data,
                    ]);

                    $firebase = new FirebaseService();
                    $firebase->sendToToken(
                        $pooja->admin->fcm_token,
                        $title,
                        $body,
                        $data
                    );
                } else {
                    \Log::warning('❌ [LARAVEL] Cannot send pooja booking notification — missing pooja/admin/token', [
                        'pooja_found' => $pooja ? true : false,
                        'admin_found' => $pooja && $pooja->admin ? true : false,
                        'fcm_token_exists' => $pooja && $pooja->admin && $pooja->admin->fcm_token ? true : false,
                    ]);
                }
            }
        }



        // Update payment status
        $payment->rzp_payment_id = $request->razorpay_payment_id;
        $payment->rzp_signature = $request->razorpay_signature;
        $payment->status = 'CONFIRMED';
        $payment->save();


        if ($payment->order_id > 0) {
            $order = Order::where('id', $payment->order_id)->first();
            if ($order) {
                $order->status = 'CONFIRMED';
                $order->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment verified successfully.',
            'payment' => $payment,
        ]);
    }

    public function markPaymentFailed(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required|string',
            'error_code' => 'nullable|string',
            'error_description' => 'nullable|string',
        ]);

        $payment = Payment::where('rzp_order_id', $request->razorpay_order_id)->first();
        if (!$payment) {
            return response()->json(['error' => 'Payment not found.'], 404);
        }

        $payment->status = 'FAILED';
        $payment->error_code = $request->error_code;
        $payment->error_description = $request->error_description;
        $payment->save();

        if ($payment->order_id > 0) {
            $order = Order::where('id', $payment->order_id)->first();
            if ($order) {
                $order->status = 'FAILED';
                $order->save();
            }
        }

        return response()->json(['message' => 'Payment marked as failed.']);
    }


    public function markPaymentCancelled(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required|string',
            'cancel_reason' => 'nullable|string',
        ]);

        $payment = Payment::where('rzp_order_id', $request->razorpay_order_id)->first();
        if (!$payment) {
            return response()->json(['error' => 'Payment not found.'], 404);
        }

        $payment->status = 'CANCELLED';
        $payment->cancel_reason = $request->cancel_reason ?? 'Cancelled by user';
        $payment->save();

        if ($payment->order_id > 0) {
            $order = Order::where('id', $payment->order_id)->first();
            if ($order) {
                $order->status = 'CANCELLED';
                $order->save();
            }
        }

        return response()->json(['message' => 'Payment marked as cancelled.']);
    }


    public function saveWallet(Request $request)
    {
        $request->validate([
            'rzp_order_id' => 'required|string',
            'wallet' => 'required|string',
        ]);

        $payment = Payment::where('rzp_order_id', $request->rzp_order_id)->first();

        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        $payment->wallet = $request->wallet;
        $payment->save();

        return response()->json(['message' => 'Wallet saved successfully.']);
    }

    public function payForPremium(Request $request)
    {
        $user = $request->user();
        $amount = $request->input('amount', 51);

        if ($user->wallet_balance < $amount) {
            return response()->json(['error' => 'Insufficient balance'], 400);
        }

        // Deduct amount and mark user as premium
        $user->wallet_balance -= $amount;
        $user->role = 'premium';
        $user->premium_started_at = now();
        $user->save();

        return response()->json([
            'message' => 'Payment successful',
            'wallet_balance' => $user->wallet_balance,
        ]);
    }
}
