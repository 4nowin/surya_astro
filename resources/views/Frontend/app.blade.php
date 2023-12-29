<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="images/header/favicon.png" type="image/x-icon" />   
       
    <title>@yield('title')</title>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="@if((new Jenssegers\Agent\Agent)->isMobile()) {{ asset('build/assets/mobile_app-v1.0.0.css') }}
            @else {{ asset('build/assets/app-v1.0.0.css') }} @endif">

    <script type="module" src="@if((new Jenssegers\Agent\Agent)->isMobile()) {{ asset('build/assets/mobile_app-v1.0.0.js') }}
            @else {{ asset('build/assets/app-v1.0.0.js') }}@endif"></script>

    
   
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