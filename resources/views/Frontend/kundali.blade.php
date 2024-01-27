@extends('Frontend.app')

@section('title', 'Horoscope')

@section('content')


<!--Slider Start-->
<div class="ast_slider_wrapper style_2 index_horoscope">
    <div class="ast_banner_text">
        <div class="container">
            <div class="ast_bannertext_wrapper">

                <!--Form Start-->
                <div class="ast_search_box mobile-form">
                    @if (session()->has('message'))
                    <div x-data="{ show: true }" x-show.transition.duration.1000ms="show" x-init="setTimeout(() => show = false, 5000)" class=" fixed top-0 right-0 shadow overflow-hidden border-b border-green-500 rounded-l-lg bg-success text-white py-2 px-5 my-4">
                        {{ session('message') }}
                    </div>
                    @endif
                    <h2>Make Your Kundali Chart</h2>
                    <form method="POST" action="/inquiry">
                        @csrf
                        <input type="text" name="for" value="Kundli" hidden>
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
                                <div class='col-6'>
                                    <label for="country" style="color:black">Country:</label>
                                    <select name="country" id="country" name="country" class="form-select" aria-label="Default select example">
                                        <option value="india" selected>India</option>
                                        <option value="britain">Great Britain</option>
                                        <option value="sri_lanka">Sri Lanka</option>
                                        <option value="nepal">Nepal</option>
                                    </select>
                                </div>
                                <div class='col-6'>
                                    <label for='pob' style="color:black">Birth Place:</label>
                                    <input type="text" class="form-control" name="place_of_birth" placeholder="Birth Place" aria-label="place_of_birth" required>
                                </div>
                            </div>
                            <div class='row text-start mb-2'>
                                <div class='col-xs-12 col-sm-12 col-md-6 col-lg-6'>
                                    <label for='dob' style="color:black">Date of Birth:</label>
                                    <input id='birth' class="form-control" name="date_of_birth" placeholder="DD/MM/YY" type='datetime-local' required>
                                </div>
                                <div class='col-xs-12 col-sm-12 col-md-6 col-lg-6'>
                                    <label for='gender' style="color:black">Gender: </label>
                                    <div class='radio-container-form'>
                                        <input checked='' id='female' name='gender' type='radio' value='female'>
                                        <label for='female'>Female</label>
                                        <input id='male' name='gender' type='radio' value='male'>
                                        <label for='male'>Male</label>
                                    </div>
                                </div>
                            </div>
                            <div class='row text-start'>
                                <div class='col-12'>
                                    <label for='message' style="color:black">Question</label>
                                    <div class="ques_container">
                                        <textarea name='message' rows="4"></textarea>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="ast_btn">Get now</button>
                    </form>
                </div>
                <!--Form End-->
                <!-- <h1>There shall be signs in the Sun, the Moon, and the Stars</h1>
                <ul class="ast_toppadder40 ast_bottompadder50">
                    <li>horoscopes</li>
                    <li>gemstones</li>
                    <li>numerology</li>
                </ul>
                <a class="ast_btn" data-bs-toggle="modal" data-bs-target="#kundali-chart-form">make it now</a> -->
            </div>
        </div>
    </div>
</div>
<!--Slider End-->
<!--About Us Start-->
<div class="ast_about_wrapper ast_toppadder100 ast_bottompadder70">
    <div class="container">
        <div class="row">
            <div class="col-lg-5 col-md-5 col-sm-12 col-xs-12 col-lg-push-7 col-md-push-7 col-sm-push-0 col-xs-push-0">
                <div class="ast_about_info_img">
                    <div class="about_slider">
                        <div class="card c" id="first"><img src="images/page/f350.jpg" alt="" class="img-responsive"></div>
                        <div class="card a" id="second"><img src="images/page/b350.jpg" alt="" class="img-responsive"></div>

                        <div class="btn-wrap">
                            <a href="javascript:void(0);" class="btn focus" id="one"></a>
                            <a href="javascript:void(0);" class="btn" id="two"></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7 col-md-7 col-sm-12 col-xs-12 col-lg-pull-5 col-md-pull-5 col-sm-pull-0 col-xs-pull-0">
                <div class="ast_about_info">
                    <h4>know your kundali</h4>
                    <p>A horoscope is an astrological chart or diagram representing the positions of the Sun, Moon, planets, astrological aspects and sensitive angles at the time of an event, such as the moment of a person's birth. The word horoscope is derived from the Greek words ōra and scopos meaning "time" and "observer" (horoskopos, pl. horoskopoi, or "marker(s) of the hour"). Other commonly used names for the horoscope in English include natal chart, astrological chart, astro-chart, celestial map, sky-map, star-chart, cosmogram, vitasphere, radical chart, radix, chart wheel or simply chart. It is used as a method of divination regarding events relating to the point in time it represents, and it forms the basis of the horoscopic traditions of astrology.</p>
                    <p>In Hindu astrology, birth charts are called kuṇḍali which are very significant and are based on movement of stars and moon. Auspicious events and rituals are started after checking the kuṇḍali of a person including the marriage in which the birth charts of the boy and girl are matched.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<!--About Us End-->


<!--Services Start-->
<div class="ast_service_wrapper ast_toppadder70 ast_bottompadder50">
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 col-lg-offset-2 col-md-offset-2 col-sm-offset-1 col-xs-offset-0">
                <div class="ast_heading">
                    <h1>our <span>services</span></h1>
                    <p>We provide a wide variety of services and some of them are given as below.</p>
                </div>
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                <div class="ast_service_slider">
                    <div class="owl-carousel owl-theme">
                        <div class="item">
                            <div class="ast_service_box">
                                <img src="images/content/sv5.png" alt="Service">
                                <h4>Birth Journal</h4>
                                <p>Birth Journal or Kundli in astrology is only a pictorial portrayal of the wonderful bodies like planets and stars in the sky at a specific date and time.</p>
                                <div class="clearfix"></div>
                                <a href="#" class="ast_btn" data-bs-toggle="modal" data-bs-target="#birth-chart-form">read more</a>
                            </div>
                        </div>
                        <div class="item">
                            <div class="ast_service_box">
                                <img src="images/content/sv6.png" alt="Service">
                                <h4>vastu shastra</h4>
                                <p>Vastu Shastra is a developed branch of Indian Astrology for science of dwelling/architecture. They are the only part those are closely related to each other.</p>
                                <div class="clearfix"></div>
                                <a href="#" class="ast_btn" data-bs-toggle="modal" data-bs-target="#vastu-chart-form">read more</a>
                            </div>
                        </div>
                        <div class="item">
                            <div class="ast_service_box">
                                <img src="images/content/sv4.png" alt="Service">
                                <h4>face reading</h4>
                                <p>Face reading or Physiognomy. Face reading gives you insights into your own and others' character and destiny through an understanding of what their facial features reveal.</p>
                                <div class="clearfix"></div>
                                <a href="#" class="ast_btn" data-bs-toggle="modal" data-bs-target="#face-chart-form">read more</a>
                            </div>
                        </div>
                        <div class="item">
                            <div class="ast_service_box">
                                <img src="images/content/sv3.png" alt="Service">
                                <h4>Philosophy</h4>
                                <p>Philosophy will help to clear your doubts regarding to your Dharma, Karma philosophy of life, like drops of nector and anything regarding to sanatana (hindu dharma).</p>
                                <div class="clearfix"></div>
                                <a href="#" class="ast_btn" data-bs-toggle="modal" data-bs-target="#phil-chart-form">read more</a>
                            </div>
                        </div>
                        <div class="item">
                            <div class="ast_service_box">
                                <img src="images/content/sv2.png" alt="Service">
                                <h4>Palmistry</h4>
                                <p>Palmistry is to judge and analyze the overall development and direction of life according to the length, thickness of fingers, the shape, color and pattern of palm.</p>
                                <div class="clearfix"></div>
                                <a href="#" class="ast_btn" data-bs-toggle="modal" data-bs-target="#palm-chart-form">read more</a>
                            </div>
                        </div>
                        <div class="item">
                            <div class="ast_service_box">
                                <img src="images/content/sv1.png" alt="Service">
                                <h4>kundli dosh</h4>
                                <p>A Dosha in Vedic astrology means a combination of malefic planets that would not let the positive things in the horoscope to fructify. It is like a blemish on a horoscope.</p>
                                <div class="clearfix"></div>
                                <a href="#" class="ast_btn" data-bs-toggle="modal" data-bs-target="#dosh-chart-form">read more</a>
                            </div>
                        </div>
                        <div class="item">
                            <div class="ast_service_box">
                                <img src="images/content/sv7.png" alt="Service">
                                <h4>year analysis</h4>
                                <p>Get to know your year analysis report for the current year which may give you a detailed report for the year and how can you make it more.</p>
                                <div class="clearfix"></div>
                                <a href="#" class="ast_btn" data-bs-toggle="modal" data-bs-target="#year-chart-form">read more</a>
                            </div>
                        </div>
                        <div class="item">
                            <div class="ast_service_box">
                                <img src="images/content/sv8.png" alt="Service">
                                <h4>matrimony</h4>
                                <p>Learn the compatibility between zodiac signs and all about relationships for Sun signs in love with The AstroTwins' love matcher horoscopes.</p>
                                <div class="clearfix"></div>
                                <a href="#" class="ast_btn" data-bs-toggle="modal" data-bs-target="#matri-chart-form">read more</a>
                            </div>
                        </div>
                        <div class="item">
                            <div class="ast_service_box">
                                <img src="images/content/sv9.png" alt="Service">
                                <h4>paid services</h4>
                                <p>Ask any specific question with respect to your need and your vision that may be affecting your life in any manner which can be a guide to your life.</p>
                                <div class="clearfix"></div>
                                <a href="#" class="ast_btn" data-bs-toggle="modal" data-bs-target="#paid-chart-form">read more</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--Services End-->
@include('Frontend.Home.wrapper')


@endsection