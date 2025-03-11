@extends('admin.layouts.admin')

@section('content')

<h4>{{ __('product.new_author') }}</h4>
<p class="text-muted">{{ __('product.add_product') }}</p>

<div class="mt-4">
    <form method="POST" action="{{ route('product.store') }}" enctype="multipart/form-data">
        @csrf

        <input type="text" class="form-control" name="language" value="{{ Config::get('app.locale') }}" hidden>
        <x-image-chooser class="border border-grey p-4 mb-3" height="200px" width="100%" :value="null" name="image" id="product-image"></x-image-chooser>

        <div class="mb-3">
            <label for="title" class="form-label">{{ __('product.title') }}</label>
            <input type="text" class="form-control" name="title" placeholder="{{ __('product.title') }}" value="{{ old('title') }}" required>
            @if ($errors->has('title'))
            <span class="text-danger text-left">{{ $errors->first('title') }}</span>
            @endif
        </div>




        <div class="row">
            <div class="col-md-4">
                <div class="row mb-3">
                    <label for="tag" class="col-md-4 col-form-label text-md-end">{{ __('product.tag') }}</label>
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="tag" placeholder="{{ __('product.tag') }}" value="{{ old('tag') }}" required>
                    </div>
                    @if ($errors->has('tag'))
                    <span class="text-danger text-left">{{ $errors->first('tag') }}</span>
                    @endif
                </div>
            </div>

            <div class="col-md-4">
                <div class="row mb-3">
                    <label for="price" class="col-md-4 col-form-label text-md-end">{{ __('product.price') }}</label>
                    <div class="col-md-6">
                        <input id="price" type="number" class="form-control" name="price" value="{{ old('price') }}" required>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="row mb-3">
                    <label for="original_price" class="col-md-4 col-form-label text-md-end">{{ __('product.original_price') }}</label>
                    <div class="col-md-6">
                        <input id="original_price" type="number" class="form-control" name="original_price" value="{{ old('original_price') }}" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="excerpt">{{ __('product.excerpt') }}</label>
            <div>
                <x-rich-text-editor name="excerpt" placeholder="{{ __('product.excerpt') }}">{!! old('excerpt') !!}</x-rich-text-editor>
                @if ($errors->has('excerpt'))
                <span class="text-danger">{{ $errors->first('excerpt') }}</span>
                @endif
            </div>
        </div>

        <div class="mb-3">
            <label for="description">{{ __('product.description') }}</label>
            <div>
                <x-rich-text-editor name="description" placeholder="{{ __('product.description') }}">{!! old('description') !!}</x-rich-text-editor>
                @if ($errors->has('description'))
                <span class="text-danger">{{ $errors->first('description') }}</span>
                @endif
            </div>
        </div>

        <button type="submit" class="btn btn-info">{{ __('product.save_product') }}</button>
        <a href="{{ route('product.index') }}" class="btn btn-dark">{{ __('product.back') }}</a>
    </form>
</div>

@endsection