<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/about', function(){
    return view('Frontend/about_us');
});

Route::get('/services', function(){
    return view('Frontend/our_services');
});

Route::get('/service_single', function(){
    return view('Frontend/service_single');
});


Route::get('/events', function(){
    return view('Frontend/events');
});

Route::get('/portfolio', function(){
    return view('Frontend/portfolio');
});

Route::get('/appointment', function(){
    return view('Frontend/appointment');
});

Route::get('/contact', function(){
    return view('Frontend/contact');
});

Route::get('/faq', function(){
    return view('Frontend/faq');
});

Route::get('/privacy', function(){
    return view('Frontend/privacy');
});

Route::get('/our_team', function(){
    return view('Frontend/our_team');
});

Route::get('/team_single', function(){
    return view('Frontend/team_single');
});

Route::get('/horoscope', function(){
    return view('Frontend/horoscopes');
});

Route::get('/horoscopes_single', function(){
    return view('Frontend/horoscopes_single');
});

Route::get('/palmistry', function(){
    return view('Frontend/palmistry');
});

Route::get('/palmistry_single', function(){
    return view('Frontend/palmistry_single');
});

Route::get('/vastu', function(){
    return view('Frontend/vastu');
});

Route::get('/vastu_single', function(){
    return view('Frontend/vastu_single');
});

Route::get('/numerology_single', function(){
    return view('Frontend/numerology_single');
});

Route::get('/numerology', function(){
    return view('Frontend/numerology');
});


Route::get('/gemstone', function(){
    return view('Frontend/gemstone');
});

Route::get('/gemstones_single', function(){
    return view('Frontend/gemstones_single');
});


Route::get('/blog', function(){
    return view('Frontend/blog_single');
});