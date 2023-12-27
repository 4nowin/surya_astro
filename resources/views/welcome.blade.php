@extends('Frontend.app')

@section('title', 'Navgrah')

@section('content')

        @include('Frontend.Home.slider')
        @include('Frontend.Home.about')
        @include('Frontend.Home.whywe')
        @include('Frontend.Home.services')
        @include('Frontend.Home.wedo')
        @include('Frontend.Home.timer')
        @include('Frontend.Home.testimonials')
        @include('Frontend.Home.overview')
        @include('Frontend.Home.wrapper')
        
        
@endsection()