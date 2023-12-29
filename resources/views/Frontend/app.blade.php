<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="images/header/favicon.png" type="image/x-icon" />   
       
    <title>@yield('title')</title>

    <!-- <link rel="stylesheet" href="@if((new Jenssegers\Agent\Agent)->isMobile()) {{ asset('build/assets/mobile_app-v1.0.5.css') }}
            @else {{ asset('build/assets/app-v1.0.5.css') }} @endif">

    <script type="module" src="@if((new Jenssegers\Agent\Agent)->isMobile()) {{ asset('build/assets/mobile_app-v1.0.5.js') }}
            @else {{ asset('build/assets/app-v1.0.5.js') }}@endif"></script> -->

    @if((new Jenssegers\Agent\Agent)->isMobile())
        @vite(['resources/css/mobile_app.scss', 'resources/js/mobile_app.js'])
    @else
        @vite(['resources/css/app.scss', 'resources/js/app.js'])
    @endif
   
</head>
<body>
    
    @if((new Jenssegers\Agent\Agent)->isMobile())
        @include('Frontend.Home.mobile.header')
    @else
        @include('Frontend.Home.desktop.header')
    @endif
        @yield('content')
    @include('Frontend.footer')
 
    @if((new Jenssegers\Agent\Agent)->isMobile())
        @include('Frontend.Models.mobile.index')
    @else
        @include('Frontend.Models.desktop.index')
    @endif

<script>
    $('#myModal').on('shown.bs.modal', function () {
  $('#myInput').trigger('focus')
})
</script>

</body>
</html>