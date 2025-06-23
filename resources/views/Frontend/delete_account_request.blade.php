@extends('Frontend.app')

@section('title', 'Request Account Deletion')

@section('content')

<!--Breadcrumb start-->
<div class="ast_pagetitle">
    <div class="ast_img_overlay"></div>
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="page_title">
                    <h2>Request Account Deletion</h2>
                </div>
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12">
                <ul class="breadcrumb">
                    <li><a href="/">home</a></li>
                    <li>//</li>
                    <li><a href="#">Request Account Deletion</a></li>
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

                    <h1>Request Account Deletion</h1>
                    <p>If you would like to delete your account and associated data from the Navgarah app, please fill out the form below.</p>

                    <form method="POST" action="{{ route('submit-delete-request') }}">
                        @csrf
                        <label>Email or Phone associated with your account:</label><br>
                        <input type="text" name="identifier" required><br><br>
                        <label>Reason for deletion (optional):</label><br>
                        <textarea name="reason"></textarea><br><br>
                        <button type="submit">Submit Deletion Request</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection