@extends('admin.layouts.admin')

@section('content')
<div class="bg-light p-4 rounded">
    <h2>{{ $astrologer->name }} {{ __('astrologer.of_astrologer') }}</h2>

    <div class="container mt-4">
        <div>
            <img src="{{ $astrologer->image }}" height="250px" width="auto" />
        </div>
        <!-- @foreach ($reviews as $review)
        <div class="review border-b pb-4 mb-4">
            <p><strong>{{ $review->user->name }}</strong></p>
            <div class="stars text-yellow-500">
                @for ($i = 1; $i <= 5; $i++)
                    @if ($i <=$review->rating)
                    <i class="fas fa-star"></i> {{-- filled star --}}
                    @else
                    <i class="far fa-star"></i> {{-- empty star --}}
                    @endif
                    @endfor
            </div>
            <p class="mt-1 text-gray-700">{{ $review->comment }}</p>
        </div>
        @endforeach -->
        <div class="average-rating mb-4">
            <h3 class="text-lg font-semibold">{{ __('astrologer.average_rating') }}:</h3>
            <div class="stars text-yellow-500">
                @for ($i = 1; $i <= 5; $i++)
                    @if ($i <=floor($averageRating))
                   <i class="fas fa-star text-warning text-xl mr-1"></i>
                    @elseif ($i - $averageRating < 1)
                        <i class="fas fa-star-half-alt text-warning text-xl mr-1"></i>
                        @else
                        <i class="far fa-star text-warning text-xl mr-1"></i>
                        @endif
                        @endfor
                        <span class="text-gray-600 ml-2">({{ $averageRating }}/5)</span>
            </div>
        </div>
        <div class="mt-2">
            <strong>{{ __('astrologer.name') }}:</strong> {{ $astrologer->name }}
        </div>
        <div class="mt-2">
            <strong>{{ __('astrologer.language') }}:</strong> {{ $astrologer->astrologer_language }}
        </div>
        <div class="mt-2">
            <strong>{{ __('astrologer.expertise') }}:</strong> {{ $astrologer->expertise }}
        </div>
        <div class="mt-2">
            <strong>{{ __('astrologer.experience') }}:</strong> {{ $astrologer->experience }}
        </div>
        <div class="mt-2">
            <strong>{{ __('astrologer.excerpt') }}:</strong> {!! $astrologer->excerpt !!}
        </div><br />
        <div>
            <strong>{{ __('astrologer.description') }}:</strong> {!! $astrologer->description !!}
        </div>
        <div class="mt-2">
            <strong>{{ __('astrologer.price') }}:</strong> ₹{{ $astrologer->price }} &nbsp;&nbsp;&nbsp;<strike>₹{{ $astrologer->original_price }}</strike>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="{{ route('astrologer.edit', $astrologer->id) }}" class="btn btn-info">{{ __('astrologer.edit') }}</a>
    <a href="{{ route('astrologer.index') }}" class="btn btn-default">{{ __('astrologer.back') }}</a>
</div>
@endsection