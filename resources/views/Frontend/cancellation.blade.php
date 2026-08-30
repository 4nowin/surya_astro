@extends('Frontend.app')

@section('title', 'Cancellations & Refund Policy')

@section('content')

<!--Breadcrumb start-->
<div class="ast_pagetitle">
    <div class="ast_img_overlay"></div>
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="page_title">
                    <h2>Cancellation & Refund Policy</h2>
                </div>
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12">
                <ul class="breadcrumb">
                    <li><a href="/">home</a></li>
                    <li>//</li>
                    <li><a href="#">Cancellations & Refund Policy</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!--Breadcrumb end-->
<!-- policy section start -->
<div class="ast_pp_wrapper ast_toppadder70 ast_bottompadder70">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 col-md-12 col-sm-12 col-xs-12 col-lg-offset-1">
                <div class="ast_pp_section">
                    <h2>Cancellation and Refund Policy</h2>

                    <h3>Cancellation</h3>
                    <p>Customers can cancel their reservation within [number] days of booking for a full refund. After [number] days, the following cancellation fees apply:</p>
                    <ul>
                        <li>20% of the total amount will be charged if cancelled 15 days prior to the event.</li>
                        <li>50% of the total amount will be charged if cancelled 7 days prior to the event.</li>
                        <li>No refund will be provided for cancellations made less than 3 days prior to the event.</li>
                    </ul>

                    <h3>Refund</h3>
                    <ul>
                        <li>Refunds will be processed within 5-7 days after the cancellation request is approved.</li>
                        <li>The refund amount will be credited back to the original payment method used during the booking process.</li>
                        <li>Please note that refunds may take 7 days to reflect in your account depending on your bank or credit card provider.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>



@endsection