@extends('admin.layouts.admin')

@section('content')
<div class="bg-light p-4 rounded">
    <h2>{{ $pooja->title }} {{ __('pooja.of_pooja') }}</h2>

    <div class="container mt-4">
        <div>
            <img src="{{ $pooja->image }}" height="250px" width="auto"/>
        </div>
        <div class="mt-2">
            <strong>{{ __('pooja.date') }}:</strong> {{ $pooja->start_date }}
        </div>
        <div class="mt-2">
            <strong>{{ __('pooja.title') }}:</strong> {{ $pooja->title }}
        </div>
        <div class="mt-2">
            <strong>{{ __('pooja.tag') }}:</strong> {{ $pooja->tag }}
        </div>
        <div class="mt-2">
            <strong>{{ __('pooja.excerpt') }}:</strong> {!! $pooja->excerpt !!}
        </div><br/>
        <div>
            <strong>{{ __('pooja.description') }}:</strong> {!! $pooja->description !!}
        </div>
        <div class="mt-2">
            <strong>{{ __('pooja.price') }}:</strong> ₹{{ $pooja->price }} &nbsp;&nbsp;&nbsp;<strike>₹{{ $pooja->original_price }}</strike>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="{{ route('pooja.edit', $pooja->id) }}" class="btn btn-info">{{ __('pooja.edit') }}</a>
    <a href="{{ route('pooja.index') }}" class="btn btn-default">{{ __('pooja.back') }}</a>
</div>
@endsection
