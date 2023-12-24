<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>

    <link rel="stylesheet" href="{{ asset('build/assets/app-v1.0.0.css') }}">
    <script type="module" src="{{ asset('build/assets/app-v1.0.0.js') }}"></script>
    
</head>
<body>
    
    @include('Frontend.header')
        @yield('content')
    @include('Frontend.footer')
    

</body>
</html>