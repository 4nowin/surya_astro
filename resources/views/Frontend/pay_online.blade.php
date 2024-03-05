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
                    <h2>Pay Online Here</h2>
                    <form method="POST" action="/inquiry">
                        @csrf
                        <input type="text" name="for" value="Paid Online" hidden>
                        <div class="container">
                            <div class='row'>
                                <div class="col-12">
                                    <input type="text" name="name" class="form-control" placeholder="Name*" aria-label="Name" required>
                                </div>
                            </div>
                            <div class='row'>
                                <div class="col-12">
                                    <input type="number" name="phone" class="form-control" placeholder="Phone*" aria-label="Phone" required>
                                </div>
                                <div class='col-12'>
                                    <input type="email" name="email" class="form-control" placeholder="Email" aria-label="Email">
                                </div>
                            </div>
                            <div class="col-12">
                                <input type="number" name="amount" class="form-control" placeholder="Amount*" aria-label="Amount" required>
                            </div>
                            <div class='row text-start'>
                                <div class='col-12'>
                                    <label for='message' style="color:black">Message</label>
                                    <div class="ques_container">
                                        <textarea name='message' rows="4"></textarea>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="ast_btn">Pay Now</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!--Slider End-->


@include('Frontend.Home.wrapper')


@endsection