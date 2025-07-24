@extends('admin.layouts.admin')

@section('content')

<h4>{{ __('pooja.edit_pooja') }}</h4>
<p class="text-muted">{{ __('pooja.add_pooja') }}</p>

<div class="mt-4">
    <form method="POST" action="{{ route('pooja.update', $pooja->id) }}" enctype="multipart/form-data">
        @csrf
        @method('patch')

        <input type="text" class="form-control" name="language" value="{{ Config::get('app.locale') }}" hidden>
        <x-image-chooser class="border border-grey p-4 mb-3" height="250px" width="100%" :value="$pooja->image" name="image">
        </x-image-chooser>

        <div class="mb-3">
            <label for="title" class="form-label">{{ __('pooja.title') }}</label>
            <input type="text" class="form-control" name="title" placeholder="{{ __('pooja.title') }}" value="{{ $pooja->title }}" required>
        </div>

        <div class="mb-3">
            <label for="home_priority" class="form-label">{{ __('pooja.home_priority') }}</label>
            <select name="home_priority" class="form-control">
                <option value="" {{ $pooja->home_priority === null ? 'selected' : '' }}>{{ __('pooja.not_shown') }}</option>
                <option value="1" {{ $pooja->home_priority == 1 ? 'selected' : '' }}>{{ __('pooja.position') }} 1</option>
                <option value="2" {{ $pooja->home_priority == 2 ? 'selected' : '' }}>{{ __('pooja.position') }} 2</option>
                <option value="3" {{ $pooja->home_priority == 3 ? 'selected' : '' }}>{{ __('pooja.position') }} 3</option>
                <option value="4" {{ $pooja->home_priority == 4 ? 'selected' : '' }}>{{ __('pooja.position') }} 4</option>
            </select>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="tag" class="form-label">{{ __('pooja.tag') }}</label>
                    <input type="text" class="form-control" name="tag" placeholder="{{ __('pooja.tag') }}" value="{{ $pooja->tag }}" required>
                </div>
            </div>

            <div class="col-md-4">
                <div class="mb-3">
                    <label for="start_date" class="form-label">{{ __('pooja.start_date') }}</label>
                    <input type="date" class="form-control" name="start_date" value="{{$pooja->start_date}}">
                </div>
            </div>

            <div class="col-md-4">
                <div class="mb-3">
                    <label for="end_date" class="form-label">{{ __('pooja.end_date') }}</label>
                    <input type="date" class="form-control" name="end_date" value="{{$pooja->end_date}}">
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="price">{{ __('pooja.price') }}</label>
            <input type="number" class="form-control" name="price" value="{{$pooja->price}}" required>
        </div>

        <div class="mb-3">
            <label for="original_price">{{ __('pooja.original_price') }}</label>
            <input type="number" class="form-control" name="original_price" value="{{$pooja->original_price}}" required>
        </div>

        <div class="mb-3">
            <label for="excerpt">{{ __('pooja.excerpt') }}</label>
            <x-rich-text-editor name="excerpt">{!! $pooja->excerpt !!}</x-rich-text-editor>
        </div>

        <div class="mb-3">
            <label for="description">{{ __('pooja.description') }}</label>
            <x-rich-text-editor name="description">{!! $pooja->description !!}</x-rich-text-editor>
        </div>

        <button type="submit" class="btn btn-primary">{{ __('pooja.save_pooja') }}</button>
        <a href="{{ route('pooja.index') }}" class="btn btn-default">{{ __('pooja.back') }}</a>
    </form>
</div>

@endsection