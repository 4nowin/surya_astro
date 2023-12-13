@extends('Frontend.app')

@section('content')
    

    <!--Breadcrumb start-->
<div class="ast_pagetitle">
    <div class="ast_img_overlay"></div>
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="page_title">
                    <h2>Portfolio</h2>
                </div>
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12">
                <ul class="breadcrumb">
                    <li><a href="/">home</a></li>
                    <li>//</li>
                    <li><a href="/contact">portfolio</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!--Breadcrumb end-->


<div class="vid-main-wrapper">

  		    <!-- THE YOUTUBE PLAYER -->
    <div class="vid-container">
        <iframe id="vid_frame" src="https://www.youtube.com/embed/cOSEOYi9JS4?rel=0&showinfo=0&autohide=1" frameborder="0" width="560" height="315"></iframe>
    </div>

    <!-- THE PLAYLIST -->
    <div class="vid-list-container">
        <ol id="vid-list">
            <li>
            <a href="javascript:void();" onClick="document.getElementById('vid_frame').src='https://youtube.com/embed/cOSEOYi9JS4?autoplay=1&rel=0&showinfo=0&autohide=1'">
                <span class="vid-thumb"><img width=72 src="https://img.youtube.com/vi/cOSEOYi9JS4/default.jpg" /></span>
                <div class="desc">WeatherBeater™ Product Video</div>
            </a>
            </li>
            <li>
            <a href="javascript:void();" onClick="document.getElementById('vid_frame').src='https://youtube.com/embed/9P7mEf4bilg?autoplay=1&rel=0&showinfo=0&autohide=1'">
                <span class="vid-thumb"><img width=72 src="https://img.youtube.com/vi/9P7mEf4bilg/default.jpg" /></span>
                <div class="desc">X-act Contour® Product Video</div>
            </a>
            </li>
            <li>
            <a href="javascript:void();" onClick="document.getElementById('vid_frame').src='https://youtube.com/embed/KHxNpXovl58?autoplay=1&rel=0&showinfo=0&autohide=1'">
                <span class="vid-thumb"><img width=72 src="https://img.youtube.com/vi/KHxNpXovl58/default.jpg" /></span>
                <div class="desc">GearBox® Product Video</div>
            </a>
            </li>
            <li>
            <a href="javascript:void();" onClick="document.getElementById('vid_frame').src='https://youtube.com/embed/D_a2UBGsePQ?autoplay=1&rel=0&showinfo=0&autohide=1'">
                <span class="vid-thumb"><img width=72 src="https://img.youtube.com/vi/D_a2UBGsePQ/default.jpg" /></span>
                <div class="desc">Mud Guards Product Video</div>
            </a>
            </li>
            <li>
            <a href="javascript:void();" onClick="document.getElementById('vid_frame').src='https://youtube.com/embed/vYoh2IgoBXg?autoplay=1&rel=0&showinfo=0&autohide=1'">
                <span class="vid-thumb"><img width=72 src="https://img.youtube.com/vi/vYoh2IgoBXg/default.jpg" /></span>
                <div class="desc">Wheel Well Guards Product Video</div>
            </a>
            </li>
            <li>
            <a href="javascript:void();" onClick="document.getElementById('vid_frame').src='https://youtube.com/embed/RTHI_uGyfTM?autoplay=1&rel=0&showinfo=0&autohide=1'">
                <span class="vid-thumb"><img width=72 src="https://img.youtube.com/vi/RTHI_uGyfTM/default.jpg" /></span>
                <div class="desc">Cargo Liner Product Video</div>
            </a>
            </li>
            <li>
            <a href="javascript:void();" onClick="document.getElementById('vid_frame').src='https://youtube.com/embed/EvTjAjLNphE?autoplay=1&rel=0&showinfo=0&autohide=1'">
                <span class="vid-thumb"><img width=72 src="https://img.youtube.com/vi/EvTjAjLNphE/default.jpg" /></span>
                <div class="desc">Husky Liners Products</div>
            </a>
            </li>
            <li>
            <a href="javascript:void();" onClick="document.getElementById('vid_frame').src='https://youtube.com/embed/-Qpc79oaJQg?autoplay=1&rel=0&showinfo=0&autohide=1'">
                <span class="vid-thumb"><img width=72 src="https://img.youtube.com/vi/-Qpc79oaJQg/default.jpg" /></span>
                <div class="desc">Custom Molded Mud Guards</div>
            </a>
            </li>
            
        </ul>
    </div>
</div>


@include('Frontend.Home.team')
@include('Frontend.Home.wrapper')

<script>
    /*JS FOR SCROLLING THE ROW OF THUMBNAILS*/ 
$(document).ready(function () {
  $('.vid-item').each(function(index){
    $(this).on('click', function(){
      var current_index = index+1;
      $('.vid-item .thumb').removeClass('active');
      $('.vid-item:nth-child('+current_index+') .thumb').addClass('active');
    });
  });
});
</script>


@endsection

