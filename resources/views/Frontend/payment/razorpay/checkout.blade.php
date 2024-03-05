<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    var options = {
        "key": "{{$key}}", // Enter the Key ID generated from the Dashboard
        "amount": "{{$order_details->amount}}", // Amount is in currency subunits. Default currency is INR. Hence, 50000 refers to 50000 paise
        "currency": "{{$order_details->currency}}",
        "name": "Navgarah",
        "description": "{{$inquiry->name}}",
        "image": "{{url('images/header/fb.jpg')}}",
        "order_id": "{{$order_details->id}}", //This is a sample Order ID. Pass the `id` obtained in the response of Step 1
        "redirect": true,
        "callback_url": "{{url('payment/razorpay/complete')}}",
        "prefill": {
            "name": "{{$customer_details['name']}}",
            "email": "{{$customer_details['email']}}",
            "contact": "{{$customer_details['mobile']}}"
        },
        "notes": {

        },
        "theme": {
            "color": "#3399cc"
        }
    };
    var rzp1 = new Razorpay(options);
    rzp1.on('payment.failed', function(response) {
        //alert(response.error.code);
        alert(response.error.description);
        //alert(response.error.source);
        //alert(response.error.step);
        //alert(response.error.reason);
        //alert(response.error.metadata.order_id);
        //alert(response.error.metadata.payment_id);
        window.location.href = "{{url('/')}}"
    });
    rzp1.open();
</script>