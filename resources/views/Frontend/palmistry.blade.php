@extends('Frontend.app')

@section('title', 'Palmistry')

@section('content')

<!--Slider Start-->
<div class="ast_slider_wrapper style_2 ast_index_palmistry">
    <div class="ast_banner_text">
        <div class="container">
            <div class="ast_bannertext_wrapper">
                <div class="ast_search_box mobile-form">
                    @if (session()->has('message'))
                    <div x-data="{ show: true }" x-show.transition.duration.1000ms="show" x-init="setTimeout(() => show = false, 5000)" class=" fixed top-0 right-0 shadow overflow-hidden border-b border-green-500 rounded-l-lg bg-success text-white py-2 px-5 my-4">
                        {{ session('message') }}
                    </div>
                    @endif
                    <h2>Get Palmistry Details</h2>
                    <form method="POST" action="/inquiry">
                        @csrf
                        <input type="text" name="for" value="Palmistry" hidden>
                        <input type="text" name="amount" value="51" hidden>
                        <div class="container">
                            <div class='row text-start'>
                                <div class="col-12">
                                    <input type="text" name="name" class="form-control" placeholder="Name" aria-label="Name" required>
                                </div>
                            </div>
                            <div class='row text-start'>
                                <div class="col-6">
                                    <input type="number" name="phone" class="form-control" placeholder="Phone" aria-label="Phone" required>
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
                                    <label for='gender' style="color:black">Gender: &nbsp;&nbsp;</label>
                                    <div class='radio-container-form'>
                                        <input checked='' id='female' name='gender' type='radio' value='female'>
                                        <label for='female'>Female</label>
                                        <input id='male' name='gender' type='radio' value='male'>
                                        <label for='male'>Male</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="ast_btn">Get now @ ₹51</button>
                    </form>
                </div>
                <!-- <h1>The hand is the visible part of the brain.</h1>
                <ul class="ast_toppadder40 ast_bottompadder50">
                    <li>palmistry</li>
                    <li>vastu shastra</li>
                    <li>tarot card</li>
                </ul>
                <a class="ast_btn" data-bs-toggle="modal" data-bs-target="#rashifal">make it now</a> -->
            </div>
        </div>
    </div>
</div>
<!--Slider End-->
<!--About Us Start-->
<div class="ast_about_wrapper gray_wrapper ast_toppadder100 ast_bottompadder70">
    <div class="container">
        <div class="row">
            <div class="col-lg-5 col-md-5 col-sm-12 col-xs-12 col-lg-push-7 col-md-push-7 col-sm-push-0 col-xs-push-0">
                <div class="ast_about_info_img">
                    <div class="about_slider">
                        <div class="card c" id="first"><img src="images/page/pf350.jpg" alt="" class="img-responsive"></div>
                        <div class="card a" id="second"><img src="images/page/pb350.jpg" alt="" class="img-responsive"></div>

                        <div class="btn-wrap">
                            <a href="javascript:void(0);" class="btn focus" id="one"></a>
                            <a href="javascript:void(0);" class="btn" id="two"></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7 col-md-7 col-sm-12 col-xs-12 col-lg-pull-5 col-md-pull-5 col-sm-pull-0 col-xs-pull-0">
                <div class="ast_about_info">
                    <h4>what is palmistry</h4>
                    <p>Palmistry, also known as palm reading, chiromancy, or chirology, is the practice of fortune-telling through the pseudoscience study of the palm. The practice is found all over the world, with numerous cultural variations. Those who practice chiromancy are generally called palmists, hand readers, hand analysts, or chirologists.</p>
                    <p>There are many—often conflicting—interpretations of various lines and palmar features across various teachings of palmistry. Palmistry is practiced by the Hindu, Brahmins, and is also indirectly referenced in the Book of Job. The contradictions between different interpretations, as well as the lack of evidence for palmistry's predictions, have caused palmistry to be viewed as a pseudoscience by academics.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<!--About Us End-->
<!--palmistry section start-->
<div class="ast_palm_wrapper ast_toppadder70 ast_bottompadder70">
    <div class="container">
        <div class="row">
            <div class="col-lg-11 col-md-11 col-sm-12 col-xs-12 col-lg-offset-1 col-md-offset-1">
                <div class="ast_palm_section">
                    <div class="ast_palm_img">
                        <img src="images/page/heart.jpg" alt="" class="img-responsive">
                    </div>
                    <div class="ast_palm_content">
                        <h4><a href="/palmistry_single">basic heart line meaning</a></h4>
                        <p>The heart line is the first of the major lines examined by a reader and represents love and attraction. It is found towards the top of the palm, under the fingers. In some traditions, the line is read as starting from the edge of the palm under the little finger and flowing across the palm towards the thumb; in others, it is seen as starting under the fingers and flowing toward the outside edge of the palm. Palmists interpret this line to represent their subject's emotional life; it is therefore believed to give an insight into how the emotional side of their mindframe will act out and be acted upon during their lifetime.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-11 col-md-11 col-sm-12 col-xs-12">
                <div class="ast_palm_section ast_palm_right">
                    <div class="ast_palm_img">
                        <img src="images/page/head.jpg" alt="" class="img-responsive">
                    </div>
                    <div class="ast_palm_content">
                        <h4><a href="/palmistry_single">Basic Head Line Meaning</a></h4>
                        <p>The next line identified by palmists is the head line. This line starts at the edge of the palm under the index finger and flows across the palm towards the outside edge. Often, the head line is joined with the life line (see below) at inception. Palmists generally interpret this line to represent their subject's mind and the way it works, including learning style, communication style, intellectualism, and thirst for knowledge. It is also believed to indicate a preference for creative or analytical approaches to information (i.e., right brain or left brain).</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-11 col-md-11 col-sm-12 col-xs-12 col-lg-offset-1 col-md-offset-1">
                <div class="ast_palm_section">
                    <div class="ast_palm_img">
                        <img src="images/page/life.jpg" alt="" class="img-responsive">
                    </div>
                    <div class="ast_palm_content">
                        <h4><a href="/palmistry_single">Life Line</a></h4>
                        <p>The life line is perhaps the most controversial line on the hand.[citation needed] This line extends from the edge of the palm above the thumb and travels in an arc towards the wrist. This line is believed to represent the person's vitality and vigor, physical health and general well-being. The life line is also believed to reflect major life changes, including cataclysmic events, physical injuries, and relocations. Modern palmists generally do not believe that the length of a person's life line is tied to the length of a person's life.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-11 col-md-11 col-sm-12 col-xs-12 ">
                <div class="ast_palm_section ast_palm_right">
                    <div class="ast_palm_img">
                        <img src="images/page/love.jpg" alt="" class="img-responsive">
                    </div>
                    <div class="ast_palm_content">
                        <h4><a href="/palmistry_single">Marriage Line</a></h4>
                        <p>The marriage line on the palm reflects the time and duration of the love relationship. The marriage line on palm is located below the base of the little finger and just above the line of the heart on the right palm. It is different for everyone. Palmistry says there is only one line talking about love. Although some people may have several. In addition, some women and men cannot find their lines for marriage at all due to its absence. Marriage lines are said to reveal many details of an individual’s romantic life. They indicate everything from the number of marriages to the number of children born from those marriages. This may seem like a strange leap of faith to some, but palm reading has been around for centuries. The lines of marriage are found on the sides of the hand below the little finger. Turn the part of the hand that you rest on while writing in the light and you will see some (it could be one, two or three) deep cut lines on the hand that run horizontally under the little finger. These are the marriage lines.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 text-center">
                <a class="ast_btn" id="ast_loadmore">load more</a>
            </div>
        </div>
    </div>
</div>
<!--palmistry section end-->
<!--Video Tour Start-->
<div class="ast_videotour_wrapper ast_video_style2 ast_toppadder70 ast_bottompadder70">
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 col-lg-offset-2 col-md-offset-2 col-sm-offset-1 col-xs-offset-0">
                <div class="ast_heading">
                    <h1>watch some <span>palmistry videos</span> for free</h1>
                    <p>There are many line in hands, get to know some of the lines .</p>
                </div>
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 col-lg-offset-1 col-md-offset-1 col-sm-offset-1 col-xs-offset-0">
                <div class="ast_videotour_img">
                    <a class="popup-youtube" href="https://www.youtube.com/embed/vJ5Jf62OcXI" width="420" height="315" frameborder="0" allowfullscreen><i class="fa fa-play" aria-hidden="true"></i></a>
                    <p>which of us ever undertakes laborious physical advantage from right </p>
                </div>
            </div>
        </div>
    </div>
</div>
<!--Video Tour End-->
<!-- Testimonials Start-->

@include('Frontend.Home.testimonials')
<!-- Testimonials End-->
<!-- Download wrapper start-->
<div class="ast_download_wrapper ast_toppadder70 ast_bottompadder70">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 col-md-8 col-sm-10 col-xs-12 col-lg-offset-2 col-md-offset-2 col-sm-offset-1 col-xs-offset-0">
                <div class="ast_heading">
                    <h1>Download our <span>Mobile App</span></h1>
                    <p>Want us on the go download our mobile app and you are ready to go take your guidance with you wherever you go.</p>
                </div>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 col-lg-offset-3 col-md-offset-3">
                <div class="ast_download_box">
                    <ul>
                        <li><a href="#"><img src="images/content/download2.png" alt="Download" title="Download"></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Download wrapper End-->


<!-- Form Starts Here -->
<div class="modal" id="rashifal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered"" role=" document">
        <div class='rashifal_form  modal-content' style="width: max-content;">
            <div class="info">
                <h2>Mission to Deep Space</h2>
                <i aria-hidden="true"><img class="icon ion-ios-ionic-outline" src='https://s3-us-west-2.amazonaws.com/s.cdpn.io/38816/image-from-rawpixel-id-542207-jpeg.png'></i>
                <p>The Future Is Here</p>
            </div>
            <div class="right_container">
                <header>
                    <h1>Know Your Rashifal</h1>
                    <div class='set'>
                        <div class='name'>
                            <label for='name'>Name</label>
                            <input id='name' placeholder="Name" type='text'>
                        </div>
                        <div class='DoB'>
                            <label for='dob'>Date of Birth</label>
                            <input id='dob' placeholder="DD/MM/YY" type='text'>
                        </div>
                    </div>
                    <div class='set'>
                        <div class='gender'>
                            <label for='gender'>Gender</label>
                            <div class='radio-container'>
                                <input checked='' id='male' name='gender' type='radio' value='female'>
                                <label for='male'>Male</label>
                                <input id='female' name='gender' type='radio' value='male'>
                                <label for='female'>Female</label>
                            </div>
                        </div>
                        <div class='PoB'>
                            <label for='pob'>Place of Birth</label>
                            <input id='birthplace' placeholder="Birth Place" type='text'>
                        </div>
                    </div>
                </header>
                <footer>
                    <div class='set'>
                        <button data-bs-dismiss="modal" id='back'>Cancel</button>
                        <button id='next' type="submit">Submit</button>
                    </div>
                </footer>
            </div>
        </div>
    </div>
</div>


<!--Form Ends Here -->

@endsection