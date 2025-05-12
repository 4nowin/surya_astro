@extends('admin.layouts.admin')

@section('title', '| ' . __('astrologer.all_astrologer'))

@section('content')

<div class="d-flex">
    <div class="flex-grow-1">
        <h2>{{ __('astrologer.all_astrologer') }}</h2>
        <p class="text-muted">{{ __('astrologer.manage_astrologer') }}</p>
    </div>
    <div>
        <a href="{{ route('astrologer.create') }}" class="btn btn-primary btn-sm float-right">{{ __('astrologer.add_astrologer') }}</a>
    </div>
</div>
<div class="mt-2">
    @include('admin.layouts.partials.messages')
</div>

<table class="table">
    <thead>
        <tr>
            <th width="5%">#</th>
            <th width="5%">{{ __('astrologer.image') }}</th>
            <th>{{ __('astrologer.name') }}</th>
            <th>{{ __('astrologer.active') }}</th>
            <th width="3%" colspan="3">{{ __('astrologer.action') }}</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($astrologers as $astrologer)
        <tr>
            <th>{{ $astrologer->id }}</th>
            <th><img src="{{ $astrologer->image }}" width="50px" height="auto"></th>
            <td>{{ $astrologer->name }}</td>
            <td>
                <div class="form-check form-switch">
                    {!! Form::open(['method' => 'POST', 'route' => ['astrologer.active', $astrologer->id], 'style' => 'display:inline']) !!}
                    @csrf
                    <input type="hidden" name="active_astrologer" value="0">
                    <input class="form-check-input" type="checkbox" name="active_astrologer" value="1" role="switch" onChange='this.form.submit();' @if($astrologer->active) checked @endif>
                    {!! Form::close() !!}
                </div>
            </td>
            <td>
                <a class="btn btn-info btn-sm" href="{{ route('astrologer.show', $astrologer->id) }}">{{ __('astrologer.show') }}</a>
            </td>
            <td>
                <a class="btn btn-primary btn-sm" href="{{ route('astrologer.edit', $astrologer->id) }}">{{ __('astrologer.edit') }}</a>
            </td>
            <td>
                {!! Form::open(['method' => 'DELETE','route' => ['astrologer.destroy', $astrologer->id],'style'=>'display:inline']) !!}
                @csrf
                {!! Form::submit(__('astrologer.delete'), ['class' => 'btn btn-danger btn-sm']) !!}
                {!! Form::close() !!}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

@endsection
