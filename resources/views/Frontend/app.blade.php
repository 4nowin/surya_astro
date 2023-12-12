<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>

    @vite([
        'resources/css/Frontend/style.css',
        'resources/css/bootstrap.css',
        'resources/css/owl.carousel.css',
        
        'resources/js/jquery.js',
        'resources/js/owl.carousel.js',
        'resources/js/Frontend/custom.js',
        ])
    
</head>
<body>
    
    @include('Frontend.header')
        @yield('content')
    @include('Frontend.footer')
    

</body>
</html>