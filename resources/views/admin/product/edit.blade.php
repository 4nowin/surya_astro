@extends('admin.layouts.admin')

@section('content')

<h4>{{ __('product.edit_product') }}</h4>
<p class="text-muted">{{ __('product.add_product') }}</p>

<div class="mt-4">
    <form method="POST" action="{{ route('product.update', $product->id) }}" enctype="multipart/form-data">
        @csrf
        @method('patch')

        <input type="text" class="form-control" name="language" value="{{ Config::get('app.locale') }}" hidden>
        <x-image-chooser class="border border-grey p-4 mb-3" height="250px" width="100%" :value="$product->image" name="image">
        </x-image-chooser>

        <div class="mb-3">
            <label for="title" class="form-label">{{ __('product.title') }}</label>
            <input type="text" class="form-control" name="title" placeholder="{{ __('product.title') }}" value="{{ $product->title }}" required>
        </div>

        <div class="mb-3">
            <label for="tag" class="form-label">{{ __('product.tag') }}</label>
            <input type="text" class="form-control" name="tag" placeholder="{{ __('product.tag') }}" value="{{ $product->tag }}" required>
        </div>

        <div class="mb-3">
            <label for="price">{{ __('product.price') }}</label>
            <input type="number" class="form-control" name="price" value="{{$product->price}}" required>
        </div>

        <div class="mb-3">
            <label for="original_price">{{ __('product.original_price') }}</label>
            <input type="number" class="form-control" name="original_price" value="{{$product->original_price}}" required>
        </div>

        <div class="mb-3">
            <label for="excerpt">{{ __('product.excerpt') }}</label>
            <x-rich-text-editor name="excerpt">{!! $product->excerpt !!}</x-rich-text-editor>
        </div>

        <div class="mb-3">
            <label for="description">{{ __('product.description') }}</label>
            <x-rich-text-editor name="description">{!! $product->description !!}</x-rich-text-editor>
        </div>

        <button type="submit" class="btn btn-primary">{{ __('product.save_product') }}</button>
        <a href="{{ route('product.index') }}" class="btn btn-default">{{ __('product.back') }}</a>
    </form>
</div>

@endsection