@php
$active = Route::currentRouteName();
@endphp
<div class="sub-nav">
    <div class="container-lg px-0">
        <ul class="nav">
            @if(Auth::user()->can('retreat:dashboard'))
            <li class="nav-item">
                <a class="nav-link text-light {{$active == "admin:retreat:dashboard"? "active":""}}" href="{{url('/admin/retreat/'.$retreat->id."/dashboard")}}">Dashboard</a>
            </li>
            @endif
            @if(Auth::user()->can('retreat:bookings'))
            <li class="nav-item">
                <a class="nav-link text-light {{$active == "admin:retreat:bookings"? "active":""}}" href="{{url('/admin/retreat/'.$retreat->id."/bookings")}}">Bookings</a>
            </li>
            @endif
            @if(Auth::user()->can('retreat:orders'))
            <li class="nav-item">
                <a class="nav-link text-light {{$active == "admin:retreat:orders"? "active":""}}" href="{{url('/admin/retreat/'.$retreat->id."/orders")}}">Orders</a>
            </li>
            @endif
            @if(Auth::user()->can('retreat:customize'))
            <li class="nav-item">
                <a class="nav-link text-light {{$active == "admin:retreat:customize"? "active":""}}" href="{{url('/admin/retreat/'.$retreat->id."/customize")}}">Customize</a>
            </li>
            @endif
            @if(Auth::user()->can('retreat:check-in'))
            <li class="nav-item">
                <a class="nav-link text-light {{$active == "admin:retreat:check-in"? "active":""}}" href="{{url('/admin/retreat/'.$retreat->id."/check-in")}}">Check-In</a>
            </li>
            @endif
        </ul>
    </div>
</div>