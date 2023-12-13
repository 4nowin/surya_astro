@extends('Frontend.app')

@section('title', 'Surya Astrologers')

@section('content')
    
    <!--Breadcrumb start-->
<div class="ast_pagetitle">
<div class="ast_img_overlay"></div>
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="page_title">
                    <h2>blog single</h2>
                </div>
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12">
                <ul class="breadcrumb">
                    <li><a href="index.html">home</a></li>
                    <li>//</li>
                    <li><a href="/blog">blog</a></li>
                    <li>//</li>
                    <li><a href="#">blog single</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!--Breadcrumb end-->
<!--Blog section start-->
<div class="ast_blog_wrapper ast_toppadder80 ast_bottompadder80">
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                <div class="ast_blog_box">
                    <div class="ast_blog_img">
                        <span class="ast_date_tag">{{$blog->slug}}</span>
                        <img src="{{ filter_var($blog->image, FILTER_VALIDATE_URL) ? $blog->image : Voyager::image( $blog->image ) }}" alt="Blog" title="Blog" style="min-width: 1140px;">
                    </div>
                    <div class="ast_blog_info">
                        <ul class="ast_blog_info_text">
                            <li><a href="#"><i class="fa fa-comments-o" aria-hidden="true"></i> By </a></li>
                            <li><a href="#"><i class="fa fa-user" aria-hidden="true"></i> Aacharya Pt. Ravinder Sharma</a></li>
                        </ul>
                        <h3 class="ast_blog_info_heading">{!!$blog->title!!}</h3>
                        <p class="ast_blog_info_details">{!!$blog->body!!}
                    </div>
                </div>

 @foreach($posts as $post)
            @if($post->status == "PUBLISHED")
          <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                <div class="ast_blog_box">
                    <div class="ast_blog_img">
                        <span class="ast_date_tag">{{$post->slug}}</span>
                        <a href="blog_single.html"><img src="{{ filter_var($post->image, FILTER_VALIDATE_URL) ? $post->image : Voyager::image( $post->image ) }}" alt="Blog" title="Blog"></a>
                    </div>
                    <div class="ast_blog_info">
                        <ul class="ast_blog_info_text">
                            <li><a href="#"><i class="fa fa-comments-o" aria-hidden="true"></i> By </a></li>
                            <li><a href="#"><i class="fa fa-user" aria-hidden="true"></i> Aacharya Pt. Ravinder Sharma</a></li>
                        </ul>
                        <h3 class="ast_blog_info_heading"><a href="blog_single.html">{{$post->title}}</a></h3>
                        <p class="ast_blog_info_details">{{$post->excerpt}}</p>
                        <a class="ast_btn" href="#">read more</a>
                    </div>
                </div>
            </div>
            @endif
          @endforeach

            </div>
        </div>
    </div>
</div>
<!--Blog section end-->

@include('Frontend.Home.wrapper')



@endsection

