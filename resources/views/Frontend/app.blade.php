<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="images/header/favicon.png" type="image/x-icon" />   
       
    <title>@yield('title')</title>

    <link rel="stylesheet" href="{{ asset('build/assets/app-v1.0.0.css') }}">
    <script type="module" src="{{ asset('build/assets/app-v1.0.0.js') }}"></script>
       
    
</head>
<body>
    
    @include('Frontend.header')
        @yield('content')
    @include('Frontend.footer')
    

</body>
</html>