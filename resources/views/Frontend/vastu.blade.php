@extends('Frontend.app')

@section('title', 'Vastu')

@section('content')

<!--Slider Start-->
<div class="ast_slider_wrapper style_2 ast_index_vastu">
    <div class="ast_banner_text">
        <div class="container">
            <div class="ast_bannertext_wrapper">

                <div class="ast_search_box mobile-form">
                    @if (session()->has('message'))
                    <div x-data="{ show: true }" x-show.transition.duration.1000ms="show" x-init="setTimeout(() => show = false, 5000)" class=" fixed top-0 right-0 shadow overflow-hidden border-b border-green-500 rounded-l-lg bg-success text-white py-2 px-5 my-4">
                        {{ session('message') }}
                    </div>
                    @endif
                    <h2>Get Vastu Details</h2>
                    <form method="POST" action="/inquiry">
                        @csrf
                        <input type="text" name="for" value="Vastu" hidden>
                        <div class="container">
                            <div class='row'>
                                <div class="col-12">
                                    <input type="text" name="name" class="form-control" placeholder="Name" aria-label="Name" required>
                                </div>
                            </div>
                            <div class='row'>
                                <div class="col-6">
                                    <input type="text" name="phone" class="form-control" placeholder="Phone" aria-label="Phone" required>
                                </div>
                                <div class='col-6'>
                                    <input type="email" name="email" class="form-control" placeholder="Email" aria-label="Email">
                                </div>
                            </div>
                            <div class='row text-start'>
                                <div class='col-xs-12 col-sm-12 col-md-6 col-lg-6'>
                                    <label class="country" for="country" style="color:black">Country:</label>
                                    <select name="country" id="country" name="country" class="form-select" aria-label="Default select example">
                                        <option value="india" selected>India</option>
                                        <option value="britain">Great Britain</option>
                                        <option value="sri_lanka">Sri Lanka</option>
                                        <option value="nepal">Nepal</option>
                                    </select>
                                </div>
                                <div class='col-xs-12 col-sm-12 col-md-6 col-lg-6'>
                                    <label class="pob" for='pob' style="color:black">House Situated At:</label>
                                    <input type="text" class="form-control" name="place_of_birth" placeholder="House Place" aria-label="place_of_birth" required>
                                </div>
                            </div>
                            <div class='row text-start'>
                                <div class='col-12'>
                                    <label class="dob" for='dob' style="color:black">House build on</label>
                                    <input id='birth' class="form-control" name="date_of_birth" placeholder="DD/MM/YY" type='date' required>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="ast_btn">Get now</button>
                    </form>
                </div>
                <!-- <h1>Vastu for the house is important than geography.</h1>
                <ul class="ast_toppadder40 ast_bottompadder50">
                    <li>vastu shastra</li>
                    <li>gemstones</li>
                    <li>tarot card</li>
                </ul>
                <a class="ast_btn" data-bs-toggle="modal" data-bs-target="#vastu-chart-form">make it now</a> -->
            </div>
        </div>
    </div>
</div>
<!--Slider End-->
<!--Vastu Start-->
<div class="ast_vastu_wrapper ast_toppadder100 ast_bottompadder70">
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                <div class="ast_vastu_info">
                    <h3>Vastu tips and solutions for successful anything</h3>
                    <p>Vastu Shastra, traditionally known as the Indian Science of Architecture, is 5000 years old. The literal translation of the term ‘Vastu Shastra’ is ‘science of construction’ and offers a detailed version of dos and don’ts for lands and constructed buildings. People often regard it as a manuscript for bringing positivity to the home environment, increasing earning potential and ensuring good health for those who live there.</p>
                    <p>Vastu Shastra is essential to building a structure, from residential to commercial properties, and with the correct placement of the five elements (namely earth, water, air, fire, and space), owners can achieve prosperity and happiness. Also, directions play a vital role in the science of architecture. The teachings suggest if we respect and idolize the God of Eight directions (North, South, East, West, North-East, South-East, South-West, North-West), we bring in success, health and prosperity.</p>
                    <div class="row">
                        <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                            <img src="images/page/v500.jpg" alt="About">
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                            <ul>
                                <li>Never keep the kitchen slab or the utensils dirty overnight. Clean them before going to sleep.</li>
                                <li>For keeping your bedroom as prosperous and as peaceful as you like, keep it clutter-free always. If required, fill cabinets, empty spaces, or storage organizers with meaningful things and not unnecessary items.</li>
                                <li>Keep the bed, especially in the master bedroom, towards the south or west direction. This ensures your legs face towards north or east direction. It helps in improving mental well-being, brings prosperity, and even improves the quality of sleep.</li>
                                <li>In case of an attached toilet, avoid the south-east or south-west corner of the room.</li>
                                <li>Toilets and bathrooms must face the north-west direction of the home and never share a wall of the kitchen or the Pooja room and they should also not be placed under the stairs.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="ast_vastu_wrapper gray_wrapper ast_toppadder70 ast_bottompadder40">
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                <div class="ast_vastu_box_wrapper">
                    <div class="row">
                        <div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">
                            <div class="ast_vastu_box">
                                <a href="/vastu_single">
                                    <img src="images/page/vh310.jpg" alt="vastu" title="Vastu">
                                    <h4>vastu for home</h4>
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">
                            <div class="ast_vastu_box">
                                <a href="/vastu_single">
                                    <img src="images/page/vb310.jpg" alt="vastu" title="Vastu">
                                    <h4>vastu for business</h4>
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">
                            <div class="ast_vastu_box">
                                <a href="/vastu_single">
                                    <img src="images/page/vs310.jpg" alt="vastu" title="Vastu">
                                    <h4>vastu for sports</h4>
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">
                            <div class="ast_vastu_box">
                                <a href="/vastu_single">
                                    <img src="images/page/vc310.jpg" alt="vastu" title="Vastu">
                                    <h4>vastu for career</h4>
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">
                            <div class="ast_vastu_box">
                                <a href="/vastu_single">
                                    <img src="images/page/vhe310.jpg" alt="vastu" title="Vastu">
                                    <h4>vastu for health</h4>
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">
                            <div class="ast_vastu_box">
                                <a href="/vastu_single">
                                    <img src="images/page/vr310.jpg" alt="vastu" title="Vastu">
                                    <h4>vastu for relationship</h4>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--Vastu End-->
@if((new Jenssegers\Agent\Agent)->isDesktop())
<!--Video Tour Start-->
<div class="ast_videotour_wrapper ast_toppadder70 ast_bottompadder70">
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 col-lg-offset-2 col-md-offset-2 col-sm-offset-1 col-xs-offset-0">
                <div class="ast_heading">
                    <h1>take a <span>video tour</span></h1>
                    <p>Want to know more about the vastu and how it affects your life and the things around you find out more in the following video.</p>
                </div>
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 col-lg-offset-1 col-md-offset-1 col-sm-offset-1 col-xs-offset-0">
                <div class="ast_videotour_img">
                    <div class="ast_img_overlay"></div>
                    <iframe width="960" height="500" src="https://www.youtube.com/embed/xjaXtnfB7wc" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    <a class="popup-youtube" href="https://www.youtube.com/embed/xjaXtnfB7wc"><i class="fa fa-play" aria-hidden="true"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>
<!--Video Tour End-->
@endif
<!-- Download wrapper start-->

@include('Frontend.Home.wrapper')
<!-- Download wrapper End-->


@endsection