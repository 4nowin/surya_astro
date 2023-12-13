@extends('Frontend.app')

@section('title', 'Appointment')

@section('content')
    
    <!--Breadcrumb start-->
<div class="ast_pagetitle">
<div class="ast_img_overlay"></div>
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="page_title">
                    <h2>appointment</h2>
                </div>
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12">
                <ul class="breadcrumb">
                    <li><a href="/">home</a></li>
                    <li>//</li>
                    <li><a href="/appointment">appointment</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!--Breadcrumb end-->
<!--Journal Start-->
<div class="ast_journal_wrapper ast_toppadder70 ast_bottompadder70">
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                <div class="ast_journal_info">
                    <h3>make your appointment to discuss any problem.</h3>
                    <p>Want to anything about your life or past life or why the things are not going as they are planned what are the obstacles and how to overcome those obstacles and to live a fullfilling life in the lifetime.</p>
                    <p>If you want to know about your career, relationship, finance, business, education, health, or love life you can make an appointment and may ask any question regarding your problem and find out there solutions.</p>
                </div>
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                <div class="ast_journal_box_wrapper">
                    <div style="text-align: center; display: none;" class="con_success">
                        <form>
                        <h4><kbd style="background-color: green;">Success, Message has been sent!</kbd></h4>
                        </form>
                    </div>
                    <form action="/appointment" class="form-horizontal" method="POST" enctype="multipart/form-data" id="appointment_form">
                        {{ csrf_field() }}
                        <h3>appointment form</h3>
                        <div class="row">
                            <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                                <label>name</label>
                                <span class="text-danger"><strong id="ename"></strong></span>
                                <input type="text" placeholder="Name" name="name" required>
                            </div>
                            <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                                <label>email</label>
                                <span class="text-danger"><strong id="eemail"></strong></span>
                                <input type="email" placeholder="Email" name="email" required>
                            </div>
                            <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                                <label>mobile nmber</label>
                                <input type="tel" placeholder="Mobile Number" name="phone">
                            </div>
                            <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                                <label>gender</label>
                                <select name="sex">
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                                <label>time of day</label>
                                <select name="day">
                                    <option value="morning"> Morning </option>
                                    <option value="afternoon">Afternoon</option>
                                    <option value="evening">Evening </option>
                                </select>
                            </div>
                            <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                                <label>way to reach</label>
                                <select name="way">
                                    <option value="phone">Phone </option>
                                    <option value="email">Email</option>
                                </select>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                                <label>Preferred  Date</label>
                                <div class="row">
                                    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                                <span class="text-danger"><strong id="date-error"></strong></span>
                                        <input type="date" placeholder="Date" name="date" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                                <label>Preferred Time</label>
                                <div class="row">
                                    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                                <span class="text-danger"><strong id="time-error"></strong></span>
                                        <input type="time" placeholder="Hrs" name="time" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                                <label>address</label>
                                <textarea placeholder="Address" rows="4" name="address"></textarea>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                                <label>Reason for appointment</label>
                                <textarea placeholder="Message" rows="4" name="message"></textarea>
                            </div>
                        </div>
                        <div class="row">
                            <a class="ast_btn pull-right app_btn">make an appointment</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!--Journal End-->

    @include('Frontend.Home.wrapper')


@endsection

