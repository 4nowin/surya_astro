<nav class="navbar navbar-expand-lg bg-body-tertiary navbar-dark border-bottom">
    <div class="container py-2">
        <a class="navbar-brand" href="{{ url('/') }}">
            <img src="{{ url('/images/logo.svg') }}" alt="Eksafar" style="height: 25px;" />
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
            aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <div class="flex-grow-1"></div>
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 justify-content-end">
                <li class="nav-item">
                    <a class="nav-link" href="/">{{ __('Home') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/gallery">{{ __('Gallery') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/contact">{{ __('Contact') }}</a>
                </li>

                {{-- <li class="nav-item d-none d-lg-inline-flex"> <a class="nav-link">|</a> </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="modal" data-bs-target="#location-modal">
                        <i class="fa-solid fa-location-crosshairs me-1"></i>
                        @if ($location)
                            {{ $location->name }}
                        @else
                            {{ __('Location') }}
                        @endif
                        <i class="fa-solid keyboard-allow-down"></i>
                    </a>
                </li> --}}

                <li class="nav-item d-none d-lg-inline-flex"> <a class="nav-link">|</a> </li>

                <!--- default Links -->

                <!-- Authentication Links -->
                @guest
                    @if (Route::has('login'))
                        <li class="nav-item">
                            
                            <a class="nav-link" href="{{ route('login') }}"> <i class="fa-solid fa-lock me-1"></i> Login</a>
                        </li>
                    @endif

                    {{-- @if (Route::has('register'))
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                        </li>
                    @endif --}}
                @else
                    <li class="nav-item dropdown">
                        <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                            <i class="fa-solid fa-user me-1"></i>
                            {{ Auth::user()->name }}
                        </a>

                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown" style="z-index: 9999">
                            <a class="dropdown-item" href="{{ route('logout') }}"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                {{ __('Logout') }}
                            </a>

                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </div>
                    </li>
                @endguest

             

            </ul>
        </div>
    </div>
</nav>

{{-- @if (!$location && Request::is('/'))
    <script>
        $(document).ready(() => {
            const locationModal = new bootstrap.Modal(document.getElementById('location-modal'), {
                backdrop: true
            });
            locationModal.show();
        })
    </script>
@endif --}}

<div class="modal fade" id="location-modal" tabindex="-1" aria-labelledby="location-modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content bg-dark">
            <div class="modal-body p-4 text-center">
                <h4>Hi there!</h4>
                <p>Please select the city to explore what happening in the city!</p>
                @foreach ($locations as $location)
                    <a class="btn btn-lg text-white d-block border-1 border-white"
                        href="?location={{ $location->id }}">{{ $location->name }}</a>
                @endforeach

            </div>
        </div>
    </div>
</div>
