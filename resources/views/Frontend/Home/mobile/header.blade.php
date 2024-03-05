<!-- Header section Start -->
<div id="nav-header" class="nav-top">
    <div id="mySidenav" class="sidenav">
        <img src="images/header/fb.png" alt="logo">
        <a href="javascript:void(0)" class="closebtn text-darkorange" style="color: darkorange; position:absolute; top:-5px;" id="closebtn">&times;</a>
        <a class="{{ Request::is('/') ? 'active' : '' }}" href="/"><i class="far fa-snowflake"></i> &nbsp;Home</a>
        <a class="{{ Request::is('know-kundali') ? 'active' : '' }}" href="/know-kundali"><i class="far fa-calendar-alt"></i> &nbsp;Kundali</a>
        <a class="{{ Request::is('know-horoscope') ? 'active' : '' }}" href="/know-horoscope"><i class="fas fa-lightbulb"></i> &nbsp;Horoscope</a>
        <!-- <a href="/own-online-classes">Online Classes</a> -->
        <!-- <a class="{{ Request::is('own-schedule') ? 'active' : '' }}" href="/own-schedule"><i class="far fa-calendar-alt"></i> &nbsp;Schedule</a> -->
        <a class="{{ Request::is('know-palmistry') ? 'active' : '' }}" href="/know-palmistry"><i class="fa-solid fa-hand-sparkles"></i> &nbsp;Palmistry</a>
        <a class="{{ Request::is('know-vastu') ? 'active' : '' }}" href="/know-vastu"><i class="fa-solid fa-couch"></i> &nbsp;Vastu Shastra</a>
        <br />
        <span>MORE</span> 
        <a class="{{ Request::is('know-events') ? 'active' : '' }}" href="/pay_online"><i class="fa-solid fa-hand-holding-dollar"></i> &nbsp;Pay Online</a>
        <a class="{{ Request::is('know-events') ? 'active' : '' }}" href="/know-events"><i class="fas fa-swatchbook"></i> &nbsp;Events</a>
        <a class="{{ Request::is('know-services') ? 'active' : '' }}" href="/know-services"><i class="fa-solid fa-tachograph-digital"></i> &nbsp;Services</a>
        <!-- <a class="{{ Request::is('know-portfolio') ? 'active' : '' }}" href="/know-portfolio"><i class="fa-brands fa-megaport"></i> &nbsp;Portfolio</a> -->
        <a class="{{ Request::is('know-appointment') ? 'active' : '' }}" href="/know-appointment"><i class="fa-regular fa-calendar-check"></i> &nbsp;Appointment</a>
        <br />
        <hr />
        <a class="{{ Request::is('know-about') ? 'active' : '' }}" href="/know-about"><i class="fa-solid fa-eject"></i> &nbsp;About Us</a>
        <a class="{{ Request::is('know-contact') ? 'active' : '' }}" href="/know-contact"><i class="fas fa-mobile-alt"></i> &nbsp;Contact Us</a>
        <!-- <a href="/own-blog-lists">Blogs</a> -->
        <!-- <a href="/own-contact">Contact</a> -->
        <div class="d-flex justify-content-center social-ico">
            <a href="https://www.facebook.com/navgarah"><i class="fa-brands fa-facebook hsvg" aria-hidden="true"></i></a>
            <a href="https://www.instagram.com/navgarah"><i class="fa-brands fa-instagram hsvg" aria-hidden="true"></i></a>
            <a href="https://www.youtube.com/@suryaastro2170"><i class="fa-brands fa-youtube hsvg" aria-hidden="true"></i></a>
        </div>
    </div>
    <!-- Nav section Start -->
    <nav id="navbar">
        <!-- container Start-->
        <div class="container">
            <!--Row Start-->
            <div class="">
                <div class="mobile-logo d-flex justify-content-between align-items-center">
                    <a href="{{ url('/') }}"><img src="images/header/fb.png" alt="logo"></a>
                    <div class="social-icons square">
                        <!-- Page Content -->
                        <div id="page-content-wrapper">
                            <span id="openbtn" class="slide-menu" style="font-size:130px"><i class="fa fa-bars hsvg" aria-hidden="true"></i></span>
                        </div>
                    </div>
                </div>
                <!--<div class="align-self-center float-right">
                     <div class="social-icons another">
                        <a href="https://www.facebook.com/myownyog"><i class="fa-brands fa-facebook hsvg" aria-hidden="true" ></i></a>
                        <a href="https://www.instagram.com/own_yog/"><i class="fa-brands fa-instagram hsvg" aria-hidden="true"></i></a>
                        <a href="https://www.youtube.com/@ownyog4173"><i class="fa-brands fa-youtube hsvg" aria-hidden="true"></i></a>
                    </div> 
                </div>-->
            </div>
            <!--Row Ended-->
        </div>
    </nav>
</div>