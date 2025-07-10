@extends('admin.layouts.admin')

@section('content')
<style>
.color-option .rounded-circle {
    display: inline-block;
}
</style>

<h4>{{ __('horoscope.new_author') }}</h4>
<p class="text-muted">{{ __('horoscope.add_horoscope') }}</p>

<div class="mt-4">
    <form method="POST" action="{{ route('horoscope.store') }}" enctype="multipart/form-data">
        @csrf

        <input type="text" class="form-control" name="language" value="{{ Config::get('app.locale') }}" hidden>
        <x-image-chooser class="border border-grey p-4 mb-3 hidden-content" height="200px" width="100%" :value="null" name="image" id="horoscope-image"></x-image-chooser>


        <div class="row">
            <div class="col-md-6">

                <div class="row mb-3">
                    <label for="zodiac" class="col-md-1 col-form-label text-md-end">{{ __('horoscope.zodiac') }}</label>

                    <div class="col-md-11">
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
            </div>


            <div class="col-md-6">
                <div class="row mb-3">
                    <label for="zodiac" class="col-md-1 col-form-label text-md-end">{{ __('horoscope.mood') }}</label>

                    <div class="col-md-11">
                        <select id="mood" type="text" class="form-select" name="mood" required>
                            <option value="happy">{{ __('horoscope.happy') }}</option>
                            <option value="excited">{{ __('horoscope.excited') }}</option>
                            <option value="calm">{{ __('horoscope.calm') }}</option>
                            <option value="sad">{{ __('horoscope.sad') }}</option>
                            <option value="angry">{{ __('horoscope.angry') }}</option>
                            <option value="anxious">{{ __('horoscope.anxious') }}</option>
                            <option value="confused">{{ __('horoscope.confused') }}</option>
                            <option value="loving">{{ __('horoscope.loving') }}</option>
                            <option value="friendly">{{ __('horoscope.friendly') }}</option>
                            <option value="energetic">{{ __('horoscope.energetic') }}</option>
                            <option value="relaxed">{{ __('horoscope.relaxed') }}</option>
                            <option value="peaceful">{{ __('horoscope.peaceful') }}</option>
                        </select>
                    </div>
                </div>

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
                    <label class="col-md-4 col-form-label text-md-end">
                        {{ __('horoscope.lucky_color') }}
                    </label>
                    <div class="col-md-6">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="colorDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <span id="selectedColorLabel">{{ __('horoscope.select_color') }}</span>
                            </button>
                            <ul class="dropdown-menu w-100" aria-labelledby="colorDropdown">
                                @php
                                $colors = [
                                'red' => '#FF0000',
                                'blue' => '#0000FF',
                                'green' => '#008000',
                                'yellow' => '#FFFF00',
                                'orange' => '#FFA500',
                                'purple' => '#800080',
                                'pink' => '#FFC0CB',
                                'black' => '#000000',
                                'white' => '#FFFFFF',
                                'gray' => '#808080',
                                'brown' => '#A52A2A'
                                ];
                                @endphp

                                @foreach ($colors as $colorName => $colorCode)
                                <li>
                                    <a class="dropdown-item d-flex align-items-center color-option" href="#" data-value="{{ $colorName }}" data-label="{{ __('horoscope.' . $colorName) }}">
                                        <span class="rounded-circle me-2" style="width:16px;height:16px;background-color:{{ $colorCode }};border:1px solid #ccc;"></span>
                                        {{ __('horoscope.' . $colorName) }}
                                    </a>
                                </li>
                                @endforeach
                            </ul>
                            <!-- Hidden input to store selected value -->
                            <input type="hidden" name="lucky_color" id="luckyColorInput" value="{{ old('lucky_color') }}">
                        </div>
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

<script>
document.querySelectorAll('.color-option').forEach(function(item) {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        const label = this.getAttribute('data-label');
        const value = this.getAttribute('data-value');

        document.getElementById('selectedColorLabel').textContent = label;
        document.getElementById('luckyColorInput').value = value;
    });
});
</script>

@endsection