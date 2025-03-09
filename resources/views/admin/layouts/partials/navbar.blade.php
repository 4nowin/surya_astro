    <aside id="sidebar" class="sidebar break-point-lg has-bg-image">
        <a id="btn-collapse" class="sidebar-collapser"><i class="fa-solid fa-circle-arrow-left"></i></a>
        <div class="image-wrapper">
            <img src="{{ url('images/page/350.jpg') }}" alt="sidebar background" />
        </div>
        <div class="sidebar-layout">
            <div class="sidebar-header">
                <div class="pro-sidebar-logo">

                    @guest
                    @if (Route::has('login'))
                    <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                    @endif

                    @if(Route::has('register'))
                    <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                    @endif
                    @else
                    <div>
                        <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                            {{ Auth::user()->name[0] }}
                        </a>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                {{ __('Logout') }}
                            </a>

                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </div>
                    </div>
                    <h5>{{ Auth::user()->name }}
                    </h5>
                    @endguest

                </div>

            </div>
            <div class="sidebar-content">
                <nav class="menu open-current-submenu">
                    <ul>
                        <li class="menu-header"><span> {{ trans('navbar.language_caps') }} </span></li>
                        <li>
                            <div class="text-center">
                                <a href="#" class="dropdown-toggle text-white" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ trans('navbar.language') }} {{ App::getLocale() == 'hi' ? trans('navbar.hindi') : trans('navbar.english_code') }}
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton1" id="content">
                                    <li>
                                        <a href="{{ route('lang.switch.post', 'en') }}"
                                            onclick="event.preventDefault(); document.getElementById('language-form-en').submit();">
                                            {{ trans('navbar.english') }}
                                        </a>
                                        <form id="language-form-en" action="{{ route('lang.switch.post', 'en') }}" method="POST" style="display: none;">
                                            @csrf
                                        </form>
                                    </li>
                                    <li>
                                        <a href="{{ route('lang.switch.post', 'hi') }}" onclick="event.preventDefault(); document.getElementById('language-form-hi').submit();">
                                            {{ trans('navbar.hindi') }}
                                        </a>
                                        <form id="language-form-hi" action="{{ route('lang.switch.post', 'hi') }}" method="POST" style="display: none;">
                                            @csrf
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                    <ul>
                        <li class="menu-header"><span> {{ trans('navbar.general') }} </span></li>
                        @auth
                        @role('Admin')
                        <li class="menu-item sub-menu">
                            <a href="#">
                                <span class="menu-icon">
                                    <i class="fas fa-users"></i>
                                </span>
                                <span class="menu-title">{{ trans('navbar.users') }}</span>
                                <span class="menu-suffix">
                                    <span class="badge primary">Hot</span>
                                </span>
                            </a>
                            <div class="sub-menu-list">
                                <ul>
                                    <li class="menu-item">
                                        <a href="{{ route('admins.index') }}">
                                            <span class="menu-title">{{ trans('navbar.users')}}</span>
                                        </a>
                                    </li>
                                    <li class="menu-item">
                                        <a href="{{ route('roles.index') }}">
                                            <span class="menu-title">{{ trans('navbar.roles')}}</span>
                                        </a>
                                    </li>
                                    <li class="menu-item">
                                        <a href="{{ route('permissions.index') }}">
                                            <span class="menu-title">{{ trans('navbar.permissions')}}</span>
                                        </a>
                                    </li>
                                    <li class="menu-item sub-menu">
                                        <a href="#">
                                            <span class="menu-title">{{ trans('navbar.forms')}}</span>
                                        </a>
                                        <div class="sub-menu-list">
                                            <ul>
                                                <li class="menu-item">
                                                    <a href="#">
                                                        <span class="menu-title">{{ trans('navbar.roles')}}</span>
                                                    </a>
                                                </li>
                                                <li class="menu-item">
                                                    <a href="#">
                                                        <span class="menu-title">Select</span>
                                                    </a>
                                                </li>
                                                <li class="menu-item sub-menu">
                                                    <a href="#">
                                                        <span class="menu-title">More</span>
                                                    </a>
                                                    <div class="sub-menu-list">
                                                        <ul>
                                                            <li class="menu-item">
                                                                <a href="#">
                                                                    <span class="menu-title">CheckBox</span>
                                                                </a>
                                                            </li>
                                                            <li class="menu-item">
                                                                <a href="#">
                                                                    <span class="menu-title">Radio</span>
                                                                </a>
                                                            </li>
                                                            <li class="menu-item sub-menu">
                                                                <a href="#">
                                                                    <span class="menu-title">Want more ?</span>
                                                                    <span class="menu-suffix">&#x1F914;</span>
                                                                </a>
                                                                <div class="sub-menu-list">
                                                                    <ul>
                                                                        <li class="menu-item">
                                                                            <a href="#">
                                                                                <span class="menu-prefix">&#127881;</span>
                                                                                <span class="menu-title">You made it </span>
                                                                            </a>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </li>
                                            </ul>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li class="menu-item sub-menu">
                            <a href="#">
                                <span class="menu-icon">
                                    <i class="fas fa-sitemap"></i>
                                </span>
                                <span class="menu-title">{{ trans('navbar.images')}}</span>
                            </a>
                            <div class="sub-menu-list">
                                <ul>
                                    <li class="menu-item">
                                        <a href="{{ route('banner.index') }}">
                                            <span class="menu-title">{{ trans('navbar.banners')}}</span>
                                        </a>
                                    </li>
                                    <li class="menu-item">
                                        <a href="{{ route('gallery.index') }}">
                                            <span class="menu-title">{{ trans('navbar.gallery')}}</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li class="menu-item sub-menu">
                            <a href="#">
                                <span class="menu-icon">
                                    <i class="fas fa-mobile"></i>
                                </span>
                                <span class="menu-title">{{ trans('navbar.mobile')}}</span>
                            </a>
                            <div class="sub-menu-list">
                                <ul>
                                    <li class="menu-item">
                                        <a href="{{ route('pooja.index') }}">
                                            <span class="menu-title">{{ trans('navbar.poojas')}}</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        @endrole
                        <li class="menu-item sub-menu">
                            <a href="#">
                                <span class="menu-icon">
                                    <i class="fa-solid fa-calendar-days"></i>
                                </span>
                                <span class="menu-title">{{ trans('navbar.catelog')}}</span>
                            </a>
                            <div class="sub-menu-list">
                                <ul>
                                    <li class="menu-item sub-menu">
                                        <a href="#">
                                            <span class="menu-title">{{ trans('navbar.enquiries')}}</span>
                                        </a>
                                        <div class="sub-menu-list">
                                            <ul>
                                                <li class="menu-item">
                                                    <a href="{{ route('enquiry.index') }}">
                                                        <span class="menu-title">{{ trans('navbar.new_enquiries')}}</span>
                                                    </a>
                                                </li>
                                                <li class="menu-item">
                                                    <a href="{{ route('enquiry.completed') }}">
                                                        <span class="menu-title">{{ trans('navbar.completed_enquiries')}}</span>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li>
                                    <li class="menu-item sub-menu">
                                        <a href="#">
                                            <span class="menu-title">{{ trans('navbar.retreat')}}</span>
                                        </a>
                                        <div class="sub-menu-list">
                                            <ul>
                                                @if (Auth::user()->can('retreat:list'))
                                                <li class="menu-item">
                                                    <a href="/admin/retreat">
                                                        <span class="menu-title">{{ trans('navbar.retreats')}}</span>
                                                    </a>
                                                </li>
                                                @endif

                                                <li class="menu-item">
                                                    <a href="{{ route('guru.index') }}">
                                                        <span class="menu-title">{{ trans('navbar.guru')}}</span>
                                                    </a>
                                                </li>
                                                <li class="menu-item sub-menu">
                                                    <a href="#">
                                                        <span class="menu-title">{{ trans('navbar.inputs')}}</span>
                                                    </a>
                                                    <div class="sub-menu-list">
                                                        <ul>
                                                            <li class="menu-item">
                                                                <a href="{{ route('cancellation.index') }}">
                                                                    <span class="menu-title">{{ trans('navbar.cancellation_policy')}}</span>
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </li>
                                            </ul>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li class="menu-header" style="padding-top: 20px"><span> {{ trans('navbar.extra')}} </span></li>
                        <li class="menu-item">
                            <a href="/admin/promoters">
                                <span class="menu-icon">
                                    <i class="fa-solid fa-flask"></i>
                                </span>
                                <span class="menu-title">{{ trans('navbar.promoters')}}</span>
                                <span class="menu-suffix">
                                    <span class="badge secondary">Beta</span>
                                </span>
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="/admin/gallery">
                                <span class="menu-icon">
                                    <i class="fas fa-images"></i>
                                </span>
                                <span class="menu-title">{{ trans('navbar.gallery')}}</span>
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="/admin/coupon">
                                <span class="menu-icon">
                                    <i class="fas fa-ticket-alt"></i>
                                </span>
                                <span class="menu-title">{{ trans('navbar.coupons')}}</span>
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="/admin/locations">
                                <span class="menu-icon">
                                    <i class="fas fa-map-marked-alt"></i>
                                </span>
                                <span class="menu-title">{{ trans('navbar.locations')}}</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </aside>

    <header style="display:none">
        <nav class="navbar navbar-expand-md navbar-dark bg-dark shadow-sm">
            <div class="container-lg">
                <a class="navbar-brand" href="{{ url('/admin') }}" style="letter-spacing: 2px">
                    {{-- {{ config('app.name', 'Laravel') }} --}}
                    <img height="30px" src="{{ url('images/header/logo.png') }}" />
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto flex-grow-1 justify-content-center">
                        <li class="nav-item"><a href="/admin" class="nav-link">{{ __('Home') }}</a></li>
                        @auth
                        @role('Admin')
                        <li class="nav-item dropdown">
                            <a id="usersDropdown" class="nav-link dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>{{ __('Users')}}</a>
                            <ul class="dropdown-menu" aria-labelledby="usersDropdown">
                                <li><a href="{{ route('users.index') }}" class="dropdown-item">{{ __('Users')}}</a></li>
                                <li><a href="{{ route('roles.index') }}" class="dropdown-item">{{ __('Roles')}}</a></li>
                                <li><a href="{{ route('permissions.index') }}" class="dropdown-item">{{ __('Permissions')}} </a></li>
                            </ul>
                        </li>

                        <li class="nav-item dropdown">
                            <a id="usersDropdown" class="nav-link dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>{{ __('Website')}}</a>
                            <ul class="dropdown-menu" aria-labelledby="usersDropdown">
                                <li><a href="{{ route('banner.index') }}" class="dropdown-item">{{ __('Banners')}}</a></li>
                                <li><a href="{{ route('gallery.index') }}" class="dropdown-item">{{ __('Gallery')}}</a></li>
                            </ul>
                        </li>
                        @endrole
                        <li class="nav-item dropdown">
                            <a id="usersDropdown" class="nav-link dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>{{ __('Catelog')}}</a>
                            <ul class="dropdown-menu" aria-labelledby="usersDropdown">
                                @if (Auth::user()->can('retreat:list'))
                                <li><a href="/admin/retreat" class="dropdown-item">{{ __('Retreats')}}</a></li>
                                @endif
                                <li><a href="{{ route('guru.index') }}" class="dropdown-item">{{ __('Guru')}}</a></li>
                            </ul>
                        </li>
                        <li class="nav-item"><a href="/admin/promoters" class="nav-link">{{ __('Promoters')}}</a></li>
                        <li class="nav-item"><a href="/admin/gallery" class="nav-link">{{ __('Gallery')}}</a></li>
                        <li class="nav-item"><a href="/admin/coupon" class="nav-link">{{ __('Coupons')}}</a></li>
                        <li class="nav-item"><a href="/admin/locations" class="nav-link">{{ __('Locations')}}</a></li>
                        @endauth
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                        @if (Route::has('login'))
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                        </li>
                        @endif

                        @if (Route::has('register'))
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                        </li>
                        @endif
                        @else
                        <li class="nav-item dropdown">
                            <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                {{ Auth::user()->name }}
                            </a>

                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    {{ __('Logout') }}
                                </a>

                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            </div>
                        </li>
                        @endif
                        @endguest


                    </ul>
                </div>
            </div>
        </nav>
    </header>