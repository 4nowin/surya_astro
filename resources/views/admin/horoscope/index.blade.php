@extends('admin.layouts.admin')

@section('title', '| ' . __('horoscope.all_horoscope'))

@section('content')

<div class="d-flex">
    <div class="flex-grow-1">
        <h2>{{ __('horoscope.all_horoscope') }}</h2>
        <p class="text-muted">{{ __('horoscope.manage_horoscope') }}</p>
    </div>
    <div>
        <a href="{{ route('horoscope.create') }}" class="btn btn-primary btn-sm float-right">{{ __('horoscope.add_horoscope') }}</a>
    </div>
</div>
<div class="mt-2">
    @include('admin.layouts.partials.messages')
</div>

<div class="col-md-12">
<form method="GET" action="{{ route('horoscope.index') }}" class="mb-3 row g-2">
    <div class="col-auto">
        <label for="date" class="form-label">{{ __('horoscope.date') }}</label>
        <input type="date" name="date" id="date" value="{{ $selectedDate ?? request('date') }}" class="form-control form-control-sm">
    </div>

    <div class="col-auto">
        <label for="horoscope_type" class="form-label">{{ __('horoscope.type') }}</label>
        <select name="horoscope_type" id="horoscope_type" class="form-select form-select-sm">
            <option value="">{{ __('horoscope.all') }}</option>
            <option value="daily" {{ (request('horoscope_type') == 'daily' || !request()->has('horoscope_type')) ? 'selected' : '' }}>{{ __('horoscope.daily') }}</option>
            <option value="weekly" {{ request('horoscope_type') == 'weekly' ? 'selected' : '' }}>{{ __('horoscope.weekly') }}</option>
            <option value="monthly" {{ request('horoscope_type') == 'monthly' ? 'selected' : '' }}>{{ __('horoscope.monthly') }}</option>
            <option value="yearly" {{ request('horoscope_type') == 'yearly' ? 'selected' : '' }}>{{ __('horoscope.yearly') }}</option>
        </select>
    </div>

    <div class="col-auto">
        <label for="language" class="form-label">{{ __('Language') }}</label>
        <select name="language" id="language" class="form-select form-select-sm">
            <option value="">{{ __('All Languages') }}</option>
            <option value="en" {{ request('language') == 'en' ? 'selected' : '' }}>English</option>
            <option value="hi" {{ request('language') == 'hi' ? 'selected' : '' }}>Hindi (हिंदी)</option>
        </select>
    </div>

    <div class="col-auto align-self-end">
        <button type="submit" class="btn btn-sm btn-primary">{{ __('horoscope.filter') }}</button>
        <a href="{{ route('horoscope.index') }}" class="btn btn-sm btn-secondary">{{ __('horoscope.reset') }}</a>
    </div>
</form>
</div>

{{-- 12-Zodiac Daily Publication Status Matrix --}}
@if (isset($signsMatrix) && count($signsMatrix) > 0)
<div class="card mb-4 border-primary">
    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
        <strong>Daily Horoscope Coverage for {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</strong>
        <span class="badge {{ $publishedCount === 12 ? 'bg-success' : 'bg-warning text-dark' }}">
            {{ $publishedCount }}/12 Fully Published (EN & HI)
        </span>
    </div>
    <div class="card-body p-2">
        <div class="row g-2">
            @foreach ($signsMatrix as $s)
            <div class="col-md-3 col-sm-4 col-6">
                <div class="border rounded p-2 text-center {{ $s['is_complete'] ? 'bg-light' : 'border-warning' }}">
                    <div class="fw-bold small">{{ $s['name_en'] }} / {{ $s['name_hi'] }}</div>
                    <div class="mt-1 d-flex justify-content-center gap-1">
                        @if ($s['has_en'])
                            <a href="{{ route('horoscope.edit', $s['en_id']) }}" class="badge bg-success text-decoration-none" title="Edit English Horoscope">EN ✓</a>
                        @else
                            <a href="{{ route('horoscope.create', ['zodiac_sign' => $s['enum'], 'language' => 'en', 'start_date' => $selectedDate]) }}" class="badge bg-warning text-dark text-decoration-none" title="Add English Horoscope">+ EN</a>
                        @endif

                        @if ($s['has_hi'])
                            <a href="{{ route('horoscope.edit', $s['hi_id']) }}" class="badge bg-success text-decoration-none" title="Edit Hindi Horoscope">HI ✓</a>
                        @else
                            <a href="{{ route('horoscope.create', ['zodiac_sign' => $s['enum'], 'language' => 'hi', 'start_date' => $selectedDate]) }}" class="badge bg-warning text-dark text-decoration-none" title="Add Hindi Horoscope">+ HI</a>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<table class="table">
    <thead>
        <tr>
            <th width="5%">#</th>
            <th width="10%">{{ __('horoscope.date') }}</th>
            <th>{{ __('horoscope.tag') }}</th>
            <th>{{ __('horoscope.zodiac') }}</th>
            <th width="3%" colspan="3">{{ __('horoscope.action') }}</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($horoscopes as $horoscope)
        <tr>
            <th>{{ $horoscope->id }}</th>
            <td>{{ \Carbon\Carbon::parse($horoscope->start_date)->format('d M Y')}}</td>
            <td>{{ __('horoscope.' . $horoscope->tag) }}</td>
            <td>{{ __('horoscope.' . $horoscope->zodiac) }}</td>
            <td>
                <a class="btn btn-info btn-sm" href="{{ route('horoscope.show', $horoscope->id) }}">{{ __('horoscope.show') }}</a>
            </td>
            <td>
                <a class="btn btn-primary btn-sm" href="{{ route('horoscope.edit', $horoscope->id) }}">{{ __('horoscope.edit') }}</a>
            </td>
            <td>
                {!! Form::open(['method' => 'DELETE','route' => ['horoscope.destroy', $horoscope->id],'style'=>'display:inline']) !!}
                @csrf
                {!! Form::submit(__('horoscope.delete'), ['class' => 'btn btn-danger btn-sm']) !!}
                {!! Form::close() !!}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="text-center mt-4">
    {{ $horoscopes->links('admin.common.pagination') }}
</div>

@endsection
