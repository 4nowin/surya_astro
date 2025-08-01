@extends('admin.layouts.admin')

@section('title', '| ' . __('pooja.all_pooja'))

@section('content')

<style>
    .modal-backdrop.show {
        z-index: 1040;
    }
    .modal.show {
        z-index: 1050;
    }
</style>

<div class="d-flex">
    <div class="flex-grow-1">
        <h2>{{ __('pooja.all_pooja') }}</h2>
        <p class="text-muted">{{ __('pooja.manage_pooja') }}</p>
    </div>
    <div>
        <a href="{{ route('pooja.create') }}" class="btn btn-primary btn-sm float-right">{{ __('pooja.add_pooja') }}</a>
    </div>
</div>
<div class="mt-2">
    @include('admin.layouts.partials.messages')
</div>

<table class="table">
    <thead>
        <tr>
            <th width="5%">#</th>
            <th width="5%">{{ __('pooja.image') }}</th>
            <th>{{ __('pooja.name') }}</th>
            <th>{{ __('pooja.home_priority') }}</th>
            <th>{{ __('pooja.notification') }}</th>
            <th>{{ __('pooja.active') }}</th>
            <th width="3%" colspan="3">{{ __('pooja.action') }}</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($pooja as $poo)
        <tr>
            <th>{{ $poo->id }}</th>
            <th><img src="{{ $poo->image }}" width="50px" height="auto"></th>
            <td>{{ $poo->title }}</td>
            <td>
                @if($poo->home_priority)
                <span class="badge bg-success">{{ __('pooja.position') }} {{ $poo->home_priority }}</span>
                @else
                <span class="text-muted">{{ __('pooja.not_shown') }}</span>
                @endif
            </td>
            <td>
                <div class="form-check form-switch">
                    {!! Form::open(['method' => 'POST', 'route' => ['pooja.active', $poo->id], 'style' => 'display:inline']) !!}
                    @csrf
                    <input type="hidden" name="active_pooja" value="0">
                    <input class="form-check-input" type="checkbox" name="active_pooja" value="1" role="switch" onChange='this.form.submit();' @if($poo->active) checked @endif>
                    {!! Form::close() !!}
                </div>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#poojaNotifyModal"
                    data-title="{{ $poo->title }}"
                    data-excerpt="{{ $poo->excerpt }}">
                    <i class="fas fa-bell"></i> Notify
                </button>
            </td>
            <td>
                <a class="btn btn-info btn-sm" href="{{ route('pooja.show', $poo->id) }}">{{ __('pooja.show') }}</a>
            </td>
            <td>
                <a class="btn btn-primary btn-sm" href="{{ route('pooja.edit', $poo->id) }}">{{ __('pooja.edit') }}</a>
            </td>
            <td>
                {!! Form::open(['method' => 'DELETE','route' => ['pooja.destroy', $poo->id],'style'=>'display:inline']) !!}
                @csrf
                {!! Form::submit(__('pooja.delete'), ['class' => 'btn btn-danger btn-sm']) !!}
                {!! Form::close() !!}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="text-center mt-4">
    {{ $pooja->links('admin.common.pagination') }}
</div>




@endsection