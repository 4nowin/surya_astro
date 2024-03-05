<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WebController extends Controller
{
    public function index($id)
    {

        if ($id == 'about') {
            return view('Frontend/about_us', compact('id'));
        };

        if ($id == 'services') {
            return view('Frontend/our_services', compact('id'));
        };

        if ($id == 'service_single') {
            return view('Frontend/service_single', compact('id'));
        };

        if ($id == 'events') {
            return view('Frontend/events', compact('id'));
        };

        if ($id == 'portfolio') {
            return view('Frontend/portfolio', compact('id'));
        };

        if ($id == 'appointment') {
            return view('Frontend/appointment', compact('id'));
        };

        if ($id == 'contact') {
            return view('Frontend/contact', compact('id'));
        };

        if ($id == 'faq') {
            return view('Frontend/faq', compact('id'));
        };

        if ($id == 'privacy') {
            return view('Frontend/privacy', compact('id'));
        };

        if ($id == 'cancellation') {
            return view('Frontend/cancellation', compact('id'));
        };

        if ($id == 'shipping') {
            return view('Frontend/shipping', compact('id'));
        };

        if ($id == 'terms') {
            return view('Frontend/terms', compact('id'));
        };

        if ($id == 'our_team') {
            return view('Frontend/our_team', compact('id'));
        };

        if ($id == 'team_single') {
            return view('Frontend/team_single', compact('id'));
        };

        if ($id == 'kundali') {
            return view('Frontend/kundali', compact('id'));
        };

        if ($id == 'horoscope') {
            return view('Frontend/horoscopes', compact('id'));
        };

        if ($id == 'horoscopes_single') {
            return view('Frontend/horoscopes_single', compact('id'));
        };

        if ($id == 'palmistry') {
            return view('Frontend/palmistry', compact('id'));
        };

        if ($id == 'palmistry_single') {
            return view('Frontend/palmistry_single', compact('id'));
        };

        if ($id == 'vastu') {
            return view('Frontend/vastu', compact('id'));
        };

        if ($id == 'vastu_single') {
            return view('Frontend/vastu_single', compact('id'));
        };

        if ($id == 'numerology_single') {
            return view('Frontend/numerology_single', compact('id'));
        };

        if ($id == 'numerology') {
            return view('Frontend/numerology', compact('id'));
        };


        if ($id == 'gemstone') {
            return view('Frontend/gemstone', compact('id'));
        };

        if ($id == 'gemstones_single') {
            return view('Frontend/gemstones_single', compact('id'));
        };


        if ($id == 'blog') {
            return view('Frontend/blog_single', compact('id'));
        };

        return abort(404);
    }
}
