@extends('Frontend.app')

@section('title', 'Payment Processed')

@section('content')

<!--Breadcrumb start-->
<div class="payment_pagetitle">
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="page_title">
                    
                </div>
            </div>
        </div>
    </div>
</div>
<!--Breadcrumb end-->
<div class="ast_about_wrapper">
    <div class="container">
        <div class="row">
            <div class="container my-5 py-5">
                <article class="text-center m-auto" style="max-width: 600px;">
                    <h1 class="display-1">
                        @if($status === "FAILED")
                        <lottie-player src="https://assets1.lottiefiles.com/packages/lf20_yw3nyrsv.json" background="transparent" speed="1" style="width: 150px; height: 150px; margin:auto" autoplay></lottie-player>
                        @elseif($status === "SUCCESS" )
                        <lottie-player src="https://assets6.lottiefiles.com/packages/lf20_s2lryxtd.json" background="transparent" speed="1" style="width: 150px; height: 150px; margin:auto" autoplay></lottie-player>
                        @elseif($status === "PENDING")
                        <lottie-player src="https://assets6.lottiefiles.com/packages/lf20_qbuxqwzg.json" background="transparent" speed="1" style="width: 150px; height: 150px; margin:auto" autoplay></lottie-player>
                        @endif
                    </h1>
                    <h3 style="margin: 10px 0;">
                        @if($status === "FAILED")
                        Your {{$order->inquiry->for}} payment is failed.
                        @elseif($status === "SUCCESS")
                        Your {{$order->inquiry->for}} payment is received.
                        @elseif($status === "PENDING")
                        Your {{$order->inquiry->for}} payment is processing.
                        @endif
                    </h3>
                    <p>
                        @if($status === "FAILED")
                        Your payment has been failed
                        @elseif($status === "SUCCESS")
                        Your {{$order->inquiry->for}} payment has been received, the {{$order->inquiry->for}} will be delivered to <br /><span> Email : <b>{{ $order->email }}</b></span> <br /><span> Phone : <b>{{ $order->mobile }}</b></span>
                        @elseif($status === "PENDING")
                        Your {{$order->inquiry->for}} payment has been handled. Once payment has been received, the {{$order->inquiry->for}} will be delivered to <br /><span> Email : <b>{{ $order->email }}</b></span><br /> <span> Phone : <b>{{ $order->mobile }}</b></span>
                        @endif

                    </p>
                    <div>
                        <kbd class="bg-danger text-light" style="font-size: 20px;">
                            Product Id: #{{ $order->id }}
                            </kdb>
                    </div>
                </article>
            </div>
        </div>
    </div>
</div>
<script src="{{url('js/plugins/lottie-player.min.js')}}"></script>
@endsection