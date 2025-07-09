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
        <label for="date" class="form-label">Date</label>
        <input type="date" name="date" id="date" value="{{ request('date') }}" class="form-control form-control-sm">
    </div>

    <div class="col-auto align-self-end">
        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        <a href="{{ route('horoscope.index') }}" class="btn btn-sm btn-secondary">Reset</a>
    </div>
</form>
</div>

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
            <td>{{ $horoscope->tag }}</td>
            <td>{{ $horoscope->zodiac }}</td>
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

@endsection
