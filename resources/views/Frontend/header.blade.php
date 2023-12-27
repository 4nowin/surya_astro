<!-- Header Start -->
<div class="ast_top_header">
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                <div class="ast_contact_details">
                    <ul>
                        <li><i class="fa fa-phone" aria-hidden="true"></i><a href="tel:+91-7018565737">+91-7018565737</a> </li>
                        <li><a href="mailto:astro@ownyog.com"><i class="fa fa-envelope-o" aria-hidden="true"></i> astro@ownyog.com</a></li>
                    </ul>
                </div>
                <div class="ast_autho_wrapper">
                    <ul>
                        @if (Auth::guard('web')->check()) 
                           
                        <li class="dropdown profile">
                            <a href="#" class="dropdown-toggle text-right" data-toggle="dropdown" role="button" aria-expanded="false"><span class="caret"></span>Welcome {{ Auth::user()->name }}</a>                                
                            <div class="dropdown-menu dropdown-menu-animated" style="width: 280px; margin-left: -100px;">
                                <div class="container">
                                    <div style="text-align: center;"><br>
                                        <img src="images/page/user_icon.png" class="profile-img rounded" style="max-height: 30px; max-width: 30px;" alt="user"><hr>
                                        <h6>Welcome {{ Auth::user()->name }}</h6><hr>
                                        <div style="margin-left: 75px;">
                                            <li>
                                                <a href="{{ url('/userLogout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="popup-with-zoom-anim" data-toggle="tooltip" data-original-title="Logout" data-placement="bottom">
                                                    <i class="fa fa-power-off" aria-hidden="true"></i> Logout</a> 
                                                    <form id="logout-form" action="{{ url('/userLogout') }}" method="POST" style="display: none;">
                                                        {{ csrf_field() }}
                                                    </form>
                                            </li>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>

                        @else

                        <li><a class="login" id="login"><i class="fa fa-sign-in" aria-hidden="true"></i> Log In</a></li>
                        <li><a class="signup" id="signup"><i class="fa fa-user-plus" aria-hidden="true"></i> Sign Up</a></li>

                        
                        @endif
                    </ul>
                    <div id="login-dialog" class="">
                        <h1>Login With</h1>
                        <form id="login_form">  
                        <div class="container">
                            {{ csrf_field() }}
                        <div class="row">
                            <div class="col-md-6 col-sm-6 col-xs-6">
                            <a href="#" class="btn btn-block btn-social btn-facebook">
                                <span class="fa fa-facebook"></span> Facebook
                            </a>
                            </div>
                            <div class="col-md-6 col-sm-6 col-xs-6">
                            <a  href="#" class="btn btn-block btn-danger btn-social btn-google">
                                <span class="fa fa-google"></span> Google
                            </a>
                        </div>

                        <div class="row" style="margin-left: 30px;">
                            <div class="col-md-4 col-sm-4 col-xs-4" style="margin-top: 20px; margin-bottom: 20px;">
                                <hr>
                            </div>
                            <div class="col-md-2 col-sm-2 col-xs-2" style="margin-top: 20px; margin-bottom: 20px;" >
                                or
                            </div>
                            <div class="col-md-4 col-sm-4 col-xs-4" style="margin-top: 20px; margin-bottom: 20px;">
                                <hr>
                            </div>
                        </div>

                        <div class="row">
                            <span class="text-danger"><strong id="lemail-error"></strong></span>
                            <input type="email" name="email" placeholder="Email" required>
                        </div>

                        <div class="row">
                            <span class="text-danger"><strong id="lpassword-error"></strong></span>
                            <input type="password" name="password" placeholder="Password" required>
                        </div>
                            <div class="ast_login_data">
                                <label class="tp_custom_check" for="remember_me">Remember me <input type="checkbox" name="ast_remember_me" value="yes" id="ast_remember_me"><span class="checkmark"></span>
                                </label>
                                <a href="#">Forgot password ?</a>
                            </div>
                            <a class="btn btn-warning log_btn" style="max-width:25em">Login</a>
                            <p>Create An Account ? <a class="popup-with-zoom-anim" href="#signup-dialog">SignUp</a></p>
                        </div>
                        </div>
                        </form>
                    </div>


                    <div id="signup-dialog" class="">
                        <h1>signup With</h1>
                        <form id="signup_form" action="/createUser" enctype="multipart/form-data" method="POST">
                        <div class="container">
                            {{ csrf_field() }}

                        <div class="row">
                            <div class="col-md-6 col-sm-6 col-xs-6">
                            <a href="#" class="btn btn-block btn-social btn-facebook">
                                <span class="fa fa-facebook"></span> Facebook
                            </a>
                            </div>
                            <div class="col-md-6 col-sm-6 col-xs-6">
                            <a  href="#" class="btn btn-block btn-danger btn-social btn-google">
                                <span class="fa fa-google"></span> Google
                            </a>
                        </div>
                        <div class="row" style="margin-left: 30px;">
                            <div class="col-md-4 col-sm-4 col-xs-4" style="margin-top: 20px; margin-bottom: 20px;">
                                <hr>
                            </div>
                            <div class="col-md-2 col-sm-2 col-xs-2" style="margin-top: 20px; margin-bottom: 20px;" >
                                or
                            </div>
                            <div class="col-md-4 col-sm-4 col-xs-4" style="margin-top: 20px; margin-bottom: 20px;">
                                <hr>
                            </div>
                        </div>
                        <div class="row">
                            <span class="text-danger"><strong id="name-error"></strong></span>
                            <input type="text" name="name" placeholder="Name" required>
                        </div>
                        <div class="row">
                            <span class="text-danger"><strong id="email-error"></strong></span>
                            <input type="email" name="email" placeholder="Email" required>
                        </div>
                        <div class="row">
                            <span class="text-danger"><strong id="password-error"></strong></span>
                            <input type="password" name="password" placeholder="Password" required>
                        </div>
                        <div class="row">
                            <a class="btn btn-warning sign_btn" style="max-width:25em">submit</a>
                            <p>Have An Account ? <a class="popup-with-zoom-anim" href="#login-dialog">Login</a></p>
                        </div>
                        </div>
                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="ast_header_bottom">
    <div class="container">
        <div class="row">
            <div class="col-lg-3 col-md-3 col-sm-3 col-xs-12">
                <div class="ast_logo">
                    <a href="/"><img src="images/header/logo.png" alt="Logo" title="Logo"></a>
                    <button class="ast_menu_btn"><i class="fa fa-bars" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="col-lg-9 col-md-9 col-sm-9 col-xs-12">
                <div class="ast_main_menu_wrapper">
                    <div class="ast_menu">
                        <ul>
                            <li><a href="/">home</a>
                                <ul class="submenu">
                                    <li><a href="/">home</a></li>
                                    <li><a href="/horoscope">horoscope</a></li>
                                    <li><a href="/palmistry">palmistry</a></li>
                                    <li><a href="/vastu">vastu shastra</a></li>
                                    <!-- <li><a href="/gemstone">gemstone</a></li>
                                    <li><a href="/numerology">numerology</a></li> -->
                                </ul>
                            </li>
                            <li><a href="/about">about</a></li>
                            <li><a href="/services">services</a></li>
                            <li><a href="/events">events</a></li>
                            <li><a href="/portfolio">portfolio</a></li>
                            <li><a href="/appointment">appointment</a></li>                           
                            <li><a href="#">pages</a>
                                <ul class="submenu">
                                    <!-- <li><a href="/our_team">Our Team</a></li> -->
                                    <li><a href="/privacy">privacy policy</a></li>
                                    <li><a href="/faq">FAQ</a></li>
                                </ul>
                            </li>
                            <li><a href="/contact">contact</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    
</script>
<!-- Header End -->