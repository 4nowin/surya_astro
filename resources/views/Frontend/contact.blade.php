@extends('Frontend.app')

@section('title', 'Contact')

@section('content')

<!--Breadcrumb start-->
<div class="ast_pagetitle">
    <div class="ast_img_overlay"></div>
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="page_title">
                    <h2>contact Us</h2>
                </div>
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12">
                <ul class="breadcrumb">
                    <li><a href="/">home</a></li>
                    <li>//</li>
                    <li><a href="/contact">contact us</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!--Breadcrumb end-->

<!--Content Us Start-->
<div class="ast_contact_wrapper ast_toppadder70 ast_bottompadder50">
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 col-lg-offset-2 col-md-offset-2 col-sm-offset-1 col-xs-offset-0">
                <div class="ast_heading">
                    <h1>get in <span>touch</span></h1>
                    <p>Want to find out more about who we are or how we work feel free to contact us anytime.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">
                <div class="ast_contact_info">
                    <span><i class="fa fa-phone" aria-hidden="true"></i></span>
                    <h4>phone</h4>
                    <p><a href="tel:+91-7018565737">+91-7018565737</a><br><a href="tel:+91-8894876075">+91-8894876075</a></p>
                </div>
            </div>
            <div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">
                <div class="ast_contact_info">
                    <span><i class="fa fa-envelope-open-o" aria-hidden="true"></i></span>
                    <h4>email</h4>
                    <p><a href="mailto:asto@ownyog.com">astro@ownyog.com</a></p>
                </div>
            </div>
            <div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">
                <div class="ast_contact_info">
                    <span><i class="fa fa-map-marker" aria-hidden="true"></i></span>
                    <h4>address</h4>
                    <p>Rishikesh, India</p>
                </div>
            </div>
        </div>
    </div>
</div>
<!--Content Us End-->

<!--Content Us Start-->
<div class="ast_mapnform_wrapper ast_toppadder70">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 col-md-8 col-sm-10 col-xs-12 col-lg-offset-2 col-md-offset-2 col-sm-offset-1 col-xs-offset-0">
                <div class="ast_heading">
                    <h1>find & message <span>here</span></h1>
                    <p>You can also drop us a message if you wish.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="ast_contact_map">
        <div class="col-lg-6 col-md-8 col-sm-8 col-xs-12 col-lg-offset-3 col-md-offset-2 col-sm-offset-2 col-xs-offset-0">
            <div class="ast_contact_form">
                <div style="text-align: center; display: none;" class="con_success">
                    <form>
                      <h4><kbd style="background-color: green;">Success, Message has been sent!</kbd></h4>
                    </form>
                </div>
                <form action="/con_app" class="form-horizontal" method="POST" enctype="multipart/form-data" id="con_form">
                    {{ csrf_field() }}
                    <div class="row">
                        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                            <label>first name</label>
                                <span class="text-danger"><strong id="fname"></strong></span>
                            <input type="text" name="first_name" class="require">
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                            <label>last name</label>
                            <input type="text" name="last_name" class="require">
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                            <label>email</label>
                                <span class="text-danger"><strong id="femail"></strong></span>
                            <input type="text" name="email" class="require" data-valid="email" data-error="Email should be valid.">
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                            <label>subject</label>
                            <input type="text" name="subject" class="require">
                        </div>
                        <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                            <label>message</label>
                            <textarea rows="5" name="message" class="require"></textarea>
                        </div>
                    </div>
                    <div class="response"></div>
                    <div class="row">
                        <a class="ast_btn pull-right con_btn">send</a>
                    </div>
                </form>
            </div>
        </div>
        <iframe src="https://maps.google.com/maps?q=rishikesh%20india&t=&z=13&ie=UTF8&iwloc=&output=embed" allowfullscreen></iframe>
    </div>
</div>
<!--Content Us End-->
@include('Frontend.Home.wrapper')



@endsection

