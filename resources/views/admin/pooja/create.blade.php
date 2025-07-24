@extends('admin.layouts.admin')

@section('content')

<h4>{{ __('pooja.new_author') }}</h4>
<p class="text-muted">{{ __('pooja.add_pooja') }}</p>

<div class="mt-4">
    <form method="POST" action="{{ route('pooja.store') }}" enctype="multipart/form-data">
        @csrf

        <input type="text" class="form-control" name="language" value="{{ Config::get('app.locale') }}" hidden>
        <x-image-chooser class="border border-grey p-4 mb-3" height="200px" width="100%" :value="null" name="image" id="pooja-image"></x-image-chooser>

        <div class="mb-3">
            <label for="title" class="form-label">{{ __('pooja.title') }}</label>
            <input type="text" class="form-control" name="title" placeholder="{{ __('pooja.title') }}" value="{{ old('title') }}" required>
            @if ($errors->has('title'))
            <span class="text-danger text-left">{{ $errors->first('title') }}</span>
            @endif
        </div>

        <div class="mb-3">
            <label for="home_priority" class="form-label">{{ __('pooja.home_priority') }}</label>
            <select name="home_priority" class="form-control">
                <option value="" selected>{{ __('pooja.not_shown') }}</option>
                <option value="1" {{ old('home_priority') == 1 ? 'selected' : '' }}>{{ __('pooja.position') }} 1</option>
                <option value="2" {{ old('home_priority') == 2 ? 'selected' : '' }}>{{ __('pooja.position') }} 2</option>
                <option value="3" {{ old('home_priority') == 3 ? 'selected' : '' }}>{{ __('pooja.position') }} 3</option>
                <option value="4" {{ old('home_priority') == 4 ? 'selected' : '' }}>{{ __('pooja.position') }} 4</option>
            </select>
            @if ($errors->has('home_priority'))
            <span class="text-danger text-left">{{ $errors->first('home_priority') }}</span>
            @endif
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="row mb-3">
                    <label for="tag" class="col-md-4 col-form-label text-md-end">{{ __('pooja.tag') }}</label>
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="tag" placeholder="{{ __('pooja.tag') }}" value="{{ old('tag') }}" required>
                    </div>
                    @if ($errors->has('tag'))
                    <span class="text-danger text-left">{{ $errors->first('tag') }}</span>
                    @endif
                </div>
            </div>

            <div class="col-md-4">
                <div class="row mb-3">
                    <label for="start_date" class="col-md-4 col-form-label text-md-end">{{ __('pooja.start_date') }}</label>
                    <div class="col-md-6">
                        <input id="start_date" type="date" class="form-control" name="start_date" value="{{ old('start_date') }}">
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="row mb-3">
                    <label for="end_date" class="col-md-4 col-form-label text-md-end">{{ __('pooja.end_date') }}</label>
                    <div class="col-md-6">
                        <input id="end_date" type="date" class="form-control" name="end_date" value="{{ old('end_date') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="row mb-3">
                    <label for="price" class="col-md-4 col-form-label text-md-end">{{ __('pooja.price') }}</label>
                    <div class="col-md-6">
                        <input id="price" type="number" class="form-control" name="price" value="{{ old('price') }}" required>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="row mb-3">
                    <label for="original_price" class="col-md-4 col-form-label text-md-end">{{ __('pooja.original_price') }}</label>
                    <div class="col-md-6">
                        <input id="original_price" type="number" class="form-control" name="original_price" value="{{ old('original_price') }}" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="excerpt">{{ __('pooja.excerpt') }}</label>
            <div>
                <x-rich-text-editor name="excerpt" placeholder="{{ __('pooja.excerpt') }}">{!! old('excerpt') !!}</x-rich-text-editor>
                @if ($errors->has('excerpt'))
                <span class="text-danger">{{ $errors->first('excerpt') }}</span>
                @endif
            </div>
        </div>

        <div class="mb-3">
            <label for="description">{{ __('pooja.description') }}</label>
            <div>
                <x-rich-text-editor name="description" placeholder="{{ __('pooja.description') }}">{!! old('description') !!}</x-rich-text-editor>
                @if ($errors->has('description'))
                <span class="text-danger">{{ $errors->first('description') }}</span>
                @endif
            </div>
        </div>

        <button type="submit" class="btn btn-info">{{ __('pooja.save_pooja') }}</button>
        <a href="{{ route('pooja.index') }}" class="btn btn-dark">{{ __('pooja.back') }}</a>
    </form>
</div>

@endsection