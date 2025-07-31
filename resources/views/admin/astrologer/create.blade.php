blade
Copy
Edit
@extends('admin.layouts.admin')

@section('content')

<h4>{{ __('astrologer.new_author') }}</h4>
<p class="text-muted">{{ __('astrologer.add_astrologer') }}</p>

<div class="mt-4">
    <form method="POST" action="{{ route('astrologer.store') }}" enctype="multipart/form-data">
        @csrf

        <input type="hidden" class="form-control" name="language" value="{{ Config::get('app.locale') }}">

        <x-image-chooser class="border border-grey p-4 mb-3" height="200px" width="100%" :value="null" name="image" id="astrologer-image"></x-image-chooser>

        @if(auth()->check() && auth()->id() === 1)
        <div class="mb-3">
            <label for="admin_id" class="form-label">Select Admin</label>
            <select name="admin_id" id="admin_id" class="form-control">
                @foreach($admins as $admin)
                <option value="{{ $admin->id }}">{{ $admin->name }} (ID: {{ $admin->id }})</option>
                @endforeach
            </select>
        </div>
        @endif

        <div class="mb-3">
            <label for="name" class="form-label">{{ __('astrologer.name') }}</label>
            <input type="text" class="form-control" name="name" placeholder="{{ __('astrologer.name') }}" value="{{ old('name') }}" required>
            @error('name') <span class="text-danger">{{ $message }}</span> @enderror
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="astrologer_language">{{ __('astrologer.language') }}</label>
                <select name="astrologer_language[]" class="form-control" multiple required>
                    <option value="हिन्दी" {{ collect(old('astrologer_language'))->contains('Hindi') ? 'selected' : '' }}>Hindi (हिन्दी)</option>
                    <option value="English" {{ collect(old('astrologer_language'))->contains('English') ? 'selected' : '' }}>English</option>
                    <option value="ਪੰਜਾਬੀ" {{ collect(old('astrologer_language'))->contains('Punjabi') ? 'selected' : '' }}>Punjabi (ਪੰਜਾਬੀ)</option>
                    <option value="राजस्थानी" {{ collect(old('astrologer_language'))->contains(__('language.rajasthani')) ? 'selected' : '' }}>Rajasthani (राजस्थानी)</option>
                    <option value="भोजपुरी" {{ collect(old('astrologer_language'))->contains(__('language.bhojpuri')) ? 'selected' : '' }}>Bhojpuri (भोजपुरी)</option>
                    <option value="हरियाणवी" {{ collect(old('astrologer_language'))->contains(__('language.haryanvi')) ? 'selected' : '' }}>Haryanvi (हरियाणवी)</option>
                    <option value="বাংলা" {{ collect(old('astrologer_language'))->contains('Bengali') ? 'selected' : '' }}>Bengali (বাংলা)</option>
                    <option value="తెలుగు" {{ collect(old('astrologer_language'))->contains('Telugu') ? 'selected' : '' }}>Telugu (తెలుగు)</option>
                    <option value="मराठी" {{ collect(old('astrologer_language'))->contains('Marathi') ? 'selected' : '' }}>Marathi (मराठी)</option>
                    <option value="தமிழ்" {{ collect(old('astrologer_language'))->contains('Tamil') ? 'selected' : '' }}>Tamil (தமிழ்)</option>
                    <option value="ગુજરાતી" {{ collect(old('astrologer_language'))->contains('Gujarati') ? 'selected' : '' }}>Gujarati (ગુજરાતી)</option>
                    <option value="اُردُو" {{ collect(old('astrologer_language'))->contains('Urdu') ? 'selected' : '' }}>Urdu (اُردُو)</option>
                    <option value="ಕನ್ನಡ" {{ collect(old('astrologer_language'))->contains('Kannada') ? 'selected' : '' }}>Kannada (ಕನ್ನಡ)</option>
                    <option value="ଓଡ଼ିଆ" {{ collect(old('astrologer_language'))->contains('Odia') ? 'selected' : '' }}>Odia (ଓଡ଼ିଆ)</option>
                    <option value="മലയാളം" {{ collect(old('astrologer_language'))->contains('Malayalam') ? 'selected' : '' }}>Malayalam (മലയാളം)</option>
                    <option value="অসমীয়া" {{ collect(old('astrologer_language'))->contains('Assamese') ? 'selected' : '' }}>Assamese (অসমীয়া)</option>
                </select>
                @error('astrologer_language') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label for="expertise">{{ __('astrologer.expertise') }}</label>
                <select class="form-control" name="expertise[]" multiple required>
                    <option value="{{ __('astrologer.vedic_astrology') }}" {{ in_array(__('astrologer.vedic_astrology'), old('expertise', [])) ? 'selected' : '' }}>{{ __('astrologer.vedic_astrology') }}</option>
                    <option value="{{ __('astrologer.numerology') }}" {{ in_array(__('astrologer.numerology'), old('expertise', [])) ? 'selected' : '' }}>{{ __('astrologer.numerology') }}</option>
                    <option value="{{ __('astrologer.vastu') }}" {{ in_array(__('astrologer.vastu'), old('expertise', [])) ? 'selected' : '' }}>{{ __('astrologer.vastu') }}</option>
                    <option value="{{ __('astrologer.face_reading') }}" {{ in_array(__('astrologer.face_reading'), old('expertise', [])) ? 'selected' : '' }}>{{ __('astrologer.face_reading') }}</option>
                    <option value="{{ __('astrologer.astrology') }}" {{ in_array(__('astrologer.astrology'), old('expertise', [])) ? 'selected' : '' }}>{{ __('astrologer.astrology') }}</option>
                    <option value="{{ __('astrologer.kundli') }}" {{ in_array(__('astrologer.horoscope'), old('expertise', [])) ? 'selected' : '' }}>{{ __('astrologer.horoscope') }}</option>
                    <option value="{{ __('astrologer.palmistry') }}" {{ in_array(__('astrologer.palmistry'), old('expertise', [])) ? 'selected' : '' }}>{{ __('astrologer.palmistry') }}Palmistry</option>
                    <option value="{{ __('astrologer.tarot_reading') }}" {{ in_array(__('astrologer.tarot_reading'), old('expertise', [])) ? 'selected' : '' }}>{{ __('astrologer.tarot_reading') }}</option>
                    <option value="{{ __('astrologer.western_astrology') }}" {{ in_array(__('astrologer.western_astrology'), old('expertise', [])) ? 'selected' : '' }}>{{ __('astrologer.western_astrology') }}</option>
                    <!-- Add more options as needed -->
                </select>
                @error('expertise') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label for="experience">{{ __('astrologer.experience') }}</label>
                <input type="text" class="form-control" name="experience" value="{{ old('experience') }}" required>
                @error('experience') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label for="chat_minutes">{{ __('astrologer.chat_minutes') }}</label>
                <input type="number" class="form-control" name="chat_minutes" value="{{ old('chat_minutes') }}" required>
                @error('chat_minutes') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label for="call_minutes">{{ __('astrologer.call_minutes') }}</label>
                <input type="number" class="form-control" name="call_minutes" value="{{ old('call_minutes') }}" required>
                @error('call_minutes') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label for="price">{{ __('astrologer.price') }}</label>
                <input type="number" class="form-control" name="price" value="{{ old('price') }}" required>
                @error('price') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label for="original_price">{{ __('astrologer.original_price') }}</label>
                <input type="number" class="form-control" name="original_price" value="{{ old('original_price') }}" required>
                @error('original_price') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="col-md-4 mb-3">
                <label for="active">{{ __('astrologer.active') }}</label>
                <select name="active" class="form-control">
                    <option value="1" {{ old('active') == 1 ? 'selected' : '' }}>Yes</option>
                    <option value="0" {{ old('active') == 0 ? 'selected' : '' }}>No</option>
                </select>
                @error('active') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="excerpt">{{ __('astrologer.excerpt') }}</label>
            <x-rich-text-editor name="excerpt" placeholder="{{ __('astrologer.excerpt') }}">{!! old('excerpt') !!}</x-rich-text-editor>
            @error('excerpt') <span class="text-danger">{{ $message }}</span> @enderror
        </div>

        <div class="mb-3">
            <label for="description">{{ __('astrologer.description') }}</label>
            <x-rich-text-editor name="description" placeholder="{{ __('astrologer.description') }}">{!! old('description') !!}</x-rich-text-editor>
            @error('description') <span class="text-danger">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn btn-info">{{ __('astrologer.save_astrologer') }}</button>
        <a href="{{ route('astrologer.index') }}" class="btn btn-dark">{{ __('astrologer.back') }}</a>
    </form>
</div>

@endsection