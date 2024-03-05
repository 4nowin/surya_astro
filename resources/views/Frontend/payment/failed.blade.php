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
                        <lottie-player src="https://assets1.lottiefiles.com/packages/lf20_yw3nyrsv.json" background="transparent" speed="1" style="width: 150px; height: 150px; margin:auto" autoplay></lottie-player>
                    </h1>
                    <h3 style="margin: 10px 0;">
                        Your Payment failed.
                    </h3>
                    <p>
                        Payment was unsuccessful due to a temporary issue. If amount got deducted, it will be refunded within 5-7 working days
                    </p>
                    <div>
                        <a href="/">
                            <kbd class="bg-danger text-light" style="font-size: 20px;">
                                Click here to Go Home
                            </kdb>
                        </a>
                    </div>
                </article>
            </div>
        </div>
    </div>
</div>
<script src="{{url('js/plugins/lottie-player.min.js')}}"></script>
@endsection