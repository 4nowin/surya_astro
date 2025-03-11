@extends('admin.layouts.admin')

@section('content')

<h4>{{ __('horoscope.new_author') }}</h4>
<p class="text-muted">{{ __('horoscope.add_horoscope') }}</p>

<div class="mt-4">
    <form method="POST" action="{{ route('horoscope.store') }}" enctype="multipart/form-data">
        @csrf

        <input type="text" class="form-control" name="language" value="{{ Config::get('app.locale') }}" hidden>
        <x-image-chooser class="border border-grey p-4 mb-3 hidden-content" height="200px" width="100%" :value="null" name="image" id="horoscope-image"></x-image-chooser>

        <div class="row mb-3">
            <label for="zodiac" class="col-md-1 col-form-label text-md-end">{{ __('horoscope.zodiac') }}</label>

            <div class="col-md-6">
                <select id="zodiac" type="text" class="form-select" name="zodiac_sign" required>
                    <option value="1">{{ __('horoscope.aries') }}</option>
                    <option value="2">{{ __('horoscope.taurus') }}</option>
                    <option value="3">{{ __('horoscope.gemini') }}</option>
                    <option value="4">{{ __('horoscope.cancer') }}</option>
                    <option value="5">{{ __('horoscope.leo') }}</option>
                    <option value="6">{{ __('horoscope.virgo') }}</option>
                    <option value="7">{{ __('horoscope.libra') }}</option>
                    <option value="8">{{ __('horoscope.scorpio') }}</option>
                    <option value="9">{{ __('horoscope.sagittarius') }}</option>
                    <option value="10">{{ __('horoscope.capricorn') }}</option>
                    <option value="11">{{ __('horoscope.aquarius') }}</option>
                    <option value="12">{{ __('horoscope.pisces') }}</option>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="row mb-3">
                    <label for="type" class="col-md-4 col-form-label text-md-end">{{ __('horoscope.type') }}</label>

                    <div class="col-md-6">
                        <select id="type" type="text" class="form-select horoscope-type" name="horoscope_type" required>
                            <option value="1">{{ __('horoscope.daily') }}</option>
                            <option value="2">{{ __('horoscope.weekly') }}</option>
                            <option value="3">{{ __('horoscope.monthly') }}</option>
                            <option value="4">{{ __('horoscope.yearly') }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="row mb-3">
                    <label for="start_date" class="col-md-4 col-form-label text-md-end">{{ __('horoscope.start_date') }}</label>
                    <div class="col-md-6">
                        <input id="start_date" type="date" class="form-control" name="start_date" value="{{ old('start_date') }}" required>
                    </div>
                </div>
            </div>

            <div class="col-md-4 hidden-content">
                <div class="row mb-3">
                    <label for="end_date" class="col-md-4 col-form-label text-md-end">{{ __('horoscope.end_date') }}</label>
                    <div class="col-md-6">
                        <input id="end_date" type="date" class="form-control" name="end_date" value="{{ old('end_date') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="row">

            <div class="col-md-4">
                <div class="row mb-3">
                    <label for="lucky_number" class="col-md-4 col-form-label text-md-end">{{ __('horoscope.lucky_number') }}</label>
                    <div class="col-md-6">
                        <input id="lucky_number" type="text" class="form-control" name="lucky_number" value="{{ old('lucky_number') }}" required>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="row mb-3">
                    <label for="lucky_color" class="col-md-4 col-form-label text-md-end">{{ __('horoscope.lucky_color') }}</label>
                    <div class="col-md-6">
                        <input id="lucky_color" type="text" class="form-control" name="lucky_color" value="{{ old('lucky_color') }}" required>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="row mb-3">
                    <label for="lucky_time" class="col-md-4 col-form-label text-md-end">{{ __('horoscope.lucky_time') }}</label>
                    <div class="col-md-6">
                        <input id="lucky_time" type="time" class="form-control" name="lucky_time" value="{{ old('lucky_time') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="context">{{ __('horoscope.context') }}</label>
            <div>
                <x-rich-text-editor name="context" placeholder="{{ __('horoscope.context') }}">{!! old('context') !!}</x-rich-text-editor>
                @if ($errors->has('context'))
                <span class="text-danger">{{ $errors->first('context') }}</span>
                @endif
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="love">{{ __('horoscope.love') }}</label>
                    <div>
                        <x-rich-text-editor name="love" placeholder="{{ __('horoscope.love') }}">{!! old('love') !!}</x-rich-text-editor>
                        @if ($errors->has('love'))
                        <span class="text-danger">{{ $errors->first('love') }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="mb-3">
                    <label for="career">{{ __('horoscope.career') }}</label>
                    <div>
                        <x-rich-text-editor name="career" placeholder="{{ __('horoscope.career') }}">{!! old('career') !!}</x-rich-text-editor>
                        @if ($errors->has('career'))
                        <span class="text-danger">{{ $errors->first('career') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="money">{{ __('horoscope.money') }}</label>
                    <div>
                        <x-rich-text-editor name="money" placeholder="{{ __('horoscope.money') }}">{!! old('money') !!}</x-rich-text-editor>
                        @if ($errors->has('money'))
                        <span class="text-danger">{{ $errors->first('money') }}</span>
                        @endif
                    </div>
                </div>
            </div>


            <div class="col-md-6">
                <div class="mb-3">
                    <label for="health">{{ __('horoscope.health') }}</label>
                    <div>
                        <x-rich-text-editor name="health" placeholder="{{ __('horoscope.health') }}">{!! old('health') !!}</x-rich-text-editor>
                        @if ($errors->has('health'))
                        <span class="text-danger">{{ $errors->first('health') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="travel">{{ __('horoscope.travel') }}</label>
            <div>
                <x-rich-text-editor name="travel" placeholder="{{ __('horoscope.travel') }}">{!! old('travel') !!}</x-rich-text-editor>
                @if ($errors->has('travel'))
                <span class="text-danger">{{ $errors->first('travel') }}</span>
                @endif
            </div>
        </div>


        <button type="submit" class="btn btn-info">{{ __('horoscope.save_horoscope') }}</button>
        <a href="{{ route('horoscope.index') }}" class="btn btn-dark">{{ __('horoscope.back') }}</a>
    </form>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const selectElement = document.querySelector(".horoscope-type"); // Select dropdown
        const hiddenDivs = document.querySelectorAll(".hidden-content"); // Select all divs with class "hidden-content"

        function toggleDiv() {
            if (selectElement.value === "1") {
                hiddenDivs.forEach(div => div.style.display = "none"); // Hide all matching divs
            } else {
                hiddenDivs.forEach(div => div.style.display = ""); // Reset to default (block or flex)
            }
        }

        // Run the function on page load
        toggleDiv();

        // Listen for changes in the select dropdown
        selectElement.addEventListener("change", toggleDiv);
    });
</script>

@endsection