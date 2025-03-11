@extends('admin.layouts.admin')

@section('content')
<div class="bg-light p-4 rounded">
    <h2>{{ $horoscope->zodiac }} {{ __('horoscope.of_horoscope') }}</h2>

    <div class="container mt-4">
        <div>
            <img src="{{ $horoscope->image }}" height="250px" width="auto"/>
        </div>
        <div class="mt-2">
            <strong>{{ __('horoscope.date') }}:</strong> {{ \Carbon\Carbon::parse($horoscope->start_date)->format('d M Y')}}
        </div>
        <div class="mt-2">
            <strong>{{ __('horoscope.title') }}:</strong> {{ $horoscope->zodiac }}
        </div>
        <div class="mt-2">
            <strong>{{ __('horoscope.tag') }}:</strong> {{ $horoscope->tag }}
        </div>
        <div>
            <strong>{{ __('horoscope.context') }}:</strong> {!! $horoscope->context !!}
        </div>
        <div>
            <strong>{{ __('horoscope.love') }}:</strong> {!! $horoscope->love !!}
        </div>
        <div>
            <strong>{{ __('horoscope.money') }}:</strong> {!! $horoscope->money !!}
        </div>
        <div>
            <strong>{{ __('horoscope.health') }}:</strong> {!! $horoscope->health !!}
        </div>
        <div>
            <strong>{{ __('horoscope.career') }}:</strong> {!! $horoscope->career !!}
        </div>
        <div>
            <strong>{{ __('horoscope.travel') }}:</strong> {!! $horoscope->travel !!}
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="{{ route('horoscope.edit', $horoscope->id) }}" class="btn btn-info">{{ __('horoscope.edit') }}</a>
    <a href="{{ route('horoscope.index') }}" class="btn btn-default">{{ __('horoscope.back') }}</a>
</div>
@endsection
