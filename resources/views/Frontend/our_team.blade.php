@extends('Frontend.app')

@section('title', 'Our Team')

@section('content')
    
    <!--Breadcrumb start-->
<div class="ast_pagetitle">
<div class="ast_img_overlay"></div>
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="page_title">
                    <h2>our team</h2>
                </div>
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12">
                <ul class="breadcrumb">
                    <li><a href="/">home</a></li>
                    <li>//</li>
                    <li><a href="/team">our team</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!--Breadcrumb end-->
<!--Team Start-->
<div class="ast_team_wrapper ast_toppadder70 ast_bottompadder50">
    <div class="container">
        <div class="row">
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="ast_team_box">  
                    <img src="images/page/rinku150.jpg" alt="team">
                    <h4><a href="/team_single">Ravinder Sharma</a></h4>
                    <p>Astrologer</p>
                    <ul>
                        <li><a href="#"><i class="fa fa-facebook" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-linkedin" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-twitter" aria-hidden="true"></i></a></li>
                    </ul>
                </div>
            </div>
            <!-- <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="ast_team_box">  
                    <img src="http://via.placeholder.com/150x150" alt="team">
                    <h4><a href="team_single.html">Marie J. Vela</a></h4>
                    <p>tarot reader</p>
                    <ul>
                        <li><a href="#"><i class="fa fa-facebook" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-linkedin" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-twitter" aria-hidden="true"></i></a></li>
                    </ul>
                </div>
            </div> -->
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="ast_team_box">  
                    <img src="images/page/vinod150.jpg" alt="team">
                    <h4><a href="/team_single">Vinod Sharma</a></h4>
                    <p>Birth journalist</p>
                    <ul>
                        <li><a href="#"><i class="fa fa-facebook" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-linkedin" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-twitter" aria-hidden="true"></i></a></li>
                    </ul>
                </div>
            </div>
            <!-- <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="ast_team_box">  
                    <img src="http://via.placeholder.com/150x150" alt="team">
                    <h4><a href="team_single.html">Judith Travis</a></h4>
                    <p>astrologist</p>
                    <ul>
                        <li><a href="#"><i class="fa fa-facebook" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-linkedin" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-twitter" aria-hidden="true"></i></a></li>
                    </ul>
                </div>
            </div> -->
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="ast_team_box">  
                    <img src="images/page/yash150.jpg" alt="team">
                    <h4><a href="/team_single">Yash Sharma</a></h4>
                    <p>Gemstonist</p>
                    <ul>
                        <li><a href="#"><i class="fa fa-facebook" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-linkedin" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-twitter" aria-hidden="true"></i></a></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="ast_team_box">  
                    <img src="images/page/pankaj150.jpg" alt="team">
                    <h4><a href="/team_single">Pankaj Sharma</a></h4>
                    <p>Yoga Instructor</p>
                    <ul>
                        <li><a href="#"><i class="fa fa-facebook" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-linkedin" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-twitter" aria-hidden="true"></i></a></li>
                    </ul>
                </div>
            </div>
            <!-- <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="ast_team_box">  
                    <img src="http://via.placeholder.com/150x150" alt="team">
                    <h4><a href="team_single.html">Doris Tierney</a></h4>
                    <p>horoscoper</p>
                    <ul>
                        <li><a href="#"><i class="fa fa-facebook" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-linkedin" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-twitter" aria-hidden="true"></i></a></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="ast_team_box">  
                    <img src="http://via.placeholder.com/150x150" alt="team">
                    <h4><a href="team_single.html">Lawrence Soto</a></h4>
                    <p>psychologist</p>
                    <ul>
                        <li><a href="#"><i class="fa fa-facebook" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-linkedin" aria-hidden="true"></i></a></li>
                        <li><a href="#"><i class="fa fa-twitter" aria-hidden="true"></i></a></li>
                    </ul>
                </div>
            </div> -->
        </div>
    </div>
</div>
<!--Team end-->
@include('Frontend.Home.wrapper')



@endsection

