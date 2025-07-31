@extends('admin.layouts.admin')

@section('content')

<h4>{{ __('astrologer.edit_astrologer') }}</h4>
<p class="text-muted">{{ __('astrologer.add_astrologer') }}</p>

<div class="mt-4">
    <form method="POST" action="{{ route('astrologer.update', $astrologer->id) }}" enctype="multipart/form-data">
        @csrf
        @method('patch')

        <input type="hidden" class="form-control" name="language" value="{{ Config::get('app.locale') }}">

        <x-image-chooser class="border border-grey p-4 mb-3" height="200px" width="100%" :value="$astrologer->image" name="image" id="astrologer-image" />

        @if(auth()->check() && auth()->id() === 1)
        <div class="mb-3">
            <label for="admin_id" class="form-label">Select Admin</label>
            <select name="admin_id" id="admin_id" class="form-control">
                @foreach($admins as $admin)
                <option value="{{ $admin->id }}" {{ $astrologer->admin_id == $admin->id ? 'selected' : '' }}>
                    {{ $admin->name }} (ID: {{ $admin->id }})
                </option>
                @endforeach
            </select>
        </div>
        @endif

        <div class="mb-3">
            <label for="name" class="form-label">{{ __('astrologer.name') }}</label>
            <input type="text" class="form-control" name="name" placeholder="{{ __('astrologer.name') }}" value="{{ $astrologer->name }}" required>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="astrologer_language">{{ __('astrologer.language') }}</label>
                <select name="astrologer_language[]" class="form-control" multiple required>
                    @foreach(config('languages.available') as $lang)
                    <option value="{{ $lang['code'] }}"
                        {{ in_array($lang['code'], $astrologer->astrologer_language) ? 'selected' : '' }}>
                        {{ $lang['label'] }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4 mb-3">
                <label for="expertise">{{ __('astrologer.expertise') }}</label>
                <select class="form-control" name="expertise[]" multiple required>
                    @foreach(__('astrologer.expertise_options') as $expertiseOption)
                    <option value="{{ $expertiseOption }}"
                        {{ in_array($expertiseOption, $astrologer->expertise) ? 'selected' : '' }}>
                        {{ $expertiseOption }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4 mb-3">
                <label for="experience">{{ __('astrologer.experience') }}</label>
                <input type="text" class="form-control" name="experience" value="{{ $astrologer->experience }}" required>
            </div>

            <div class="col-md-4 mb-3">
                <label for="chat_minutes">{{ __('astrologer.chat_minutes') }}</label>
                <input type="number" class="form-control" name="chat_minutes" value="{{ $astrologer->chat_minutes }}" required>
            </div>

            <div class="col-md-4 mb-3">
                <label for="call_minutes">{{ __('astrologer.call_minutes') }}</label>
                <input type="number" class="form-control" name="call_minutes" value="{{ $astrologer->call_minutes }}" required>
            </div>

            <div class="col-md-4 mb-3">
                <label for="price">{{ __('astrologer.price') }}</label>
                <input type="number" class="form-control" name="price" value="{{ $astrologer->price }}" required>
            </div>

            <div class="col-md-4 mb-3">
                <label for="original_price">{{ __('astrologer.original_price') }}</label>
                <input type="number" class="form-control" name="original_price" value="{{ $astrologer->original_price }}" required>
            </div>

            <div class="col-md-4 mb-3">
                <label for="active">{{ __('astrologer.active') }}</label>
                <select name="active" class="form-control">
                    <option value="1" {{ $astrologer->active == 1 ? 'selected' : '' }}>Yes</option>
                    <option value="0" {{ $astrologer->active == 0 ? 'selected' : '' }}>No</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label for="excerpt">{{ __('astrologer.excerpt') }}</label>
            <x-rich-text-editor name="excerpt">{!! $astrologer->excerpt !!}</x-rich-text-editor>
        </div>

        <div class="mb-3">
            <label for="description">{{ __('astrologer.description') }}</label>
            <x-rich-text-editor name="description">{!! $astrologer->description !!}</x-rich-text-editor>
        </div>

        <button type="submit" class="btn btn-info">{{ __('astrologer.save_astrologer') }}</button>
        <a href="{{ route('astrologer.index') }}" class="btn btn-dark">{{ __('astrologer.back') }}</a>
    </form>
</div>

@endsection