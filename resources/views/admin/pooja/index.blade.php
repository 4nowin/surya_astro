@extends('admin.layouts.admin')

@section('title', '| All Pooja')

@section('content')

<div class="d-flex">
    <div class="flex-grow-1">
        <h2>Pooja</h2>
        <p class="text-muted">Manage your pooja here.</p>
    </div>
    <div>
        <a href="{{ route('pooja.create') }}" class="btn btn-primary btn-sm float-right">Add Pooja</a>
    </div>
</div>
<div class="mt-2">
    @include('admin.layouts.partials.messages')
</div>

<table class="table">
    <thead>
        <tr>
            <th width="5%">#</th>
            <th width="5%">Image</th>
            <th>Name</th>
            <th>Active</th>
            <th width="3%" colspan="3">Action</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($pooja as $poo)
        <tr>
            <th>{{ $poo->id }}</th>
            <th><img src="{{ $poo->image }}" width="50px" height="auto"></th>
            <td>{{ $poo->title }}</td>
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
                <a class="btn btn-info btn-sm" href="{{ route('pooja.show', $poo->id) }}">Show</a>
            </td>
            <td>
                <a class="btn btn-primary btn-sm" href="{{ route('pooja.edit', $poo->id) }}">Edit</a>
            </td>
            <td>
                {!! Form::open(['method' => 'DELETE','route' => ['pooja.destroy', $poo->id],'style'=>'display:inline']) !!}
                @csrf
                {!! Form::submit('Delete', ['class' => 'btn btn-danger btn-sm']) !!}
                {!! Form::close() !!}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>


@endsection