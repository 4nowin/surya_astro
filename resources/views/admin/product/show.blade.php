@extends('admin.layouts.admin')

@section('content')
<div class="bg-light p-4 rounded">
    <h2>{{ $product->title }} {{ __('product.of_product') }}</h2>

    <div class="container mt-4">
        <div>
            <img src="{{ $product->image }}" height="250px" width="auto"/>
        </div>
        <div class="mt-2">
            <strong>{{ __('product.title') }}:</strong> {{ $product->title }}
        </div>
        <div class="mt-2">
            <strong>{{ __('product.tag') }}:</strong> {{ $product->tag }}
        </div>
        <div class="mt-2">
            <strong>{{ __('product.excerpt') }}:</strong> {!! $product->excerpt !!}
        </div><br/>
        <div>
            <strong>{{ __('product.description') }}:</strong> {!! $product->description !!}
        </div>
        <div class="mt-2">
            <strong>{{ __('product.price') }}:</strong> ₹{{ $product->price }} &nbsp;&nbsp;&nbsp;<strike>₹{{ $product->original_price }}</strike>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="{{ route('product.edit', $product->id) }}" class="btn btn-info">{{ __('product.edit') }}</a>
    <a href="{{ route('product.index') }}" class="btn btn-default">{{ __('product.back') }}</a>
</div>
@endsection
