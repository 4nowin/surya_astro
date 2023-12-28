@extends('admin.layouts.admin')

@section('content')

<div class="d-flex">
    <div class="flex-grow-1">
        <h2>Roles</h2>
        <p class="text-muted">Manage your promoters here.</p>
    </div>
    <div>
        <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm float-right">Add role</a>
    </div>
</div>
<div class="mt-2">
    @include('admin.layouts.partials.messages')
</div>

<table class="table table-bordered">
    <tr>
        <th width="1%">No</th>
        <th>Name</th>
        <th width="3%" colspan="3">Action</th>
    </tr>
    @foreach ($roles as $key => $role)
    <tr>
        <td>{{ $role->id }}</td>
        <td>{{ $role->name }}</td>
        <td>
            <a class="btn btn-info btn-sm" href="{{ route('roles.show', $role->id) }}">Show</a>
        </td>
        <td>
            <a class="btn btn-primary btn-sm" href="{{ route('roles.edit', $role->id) }}">Edit</a>
        </td>
        <td>
            {!! Form::open(['method' => 'DELETE','route' => ['roles.destroy', $role->id],'style'=>'display:inline']) !!}
            {!! Form::submit('Delete', ['class' => 'btn btn-danger btn-sm']) !!}
            {!! Form::close() !!}
        </td>
    </tr>
    @endforeach
</table>

<div class="d-flex">
    {!! $roles->links() !!}
</div>

</div>
@endsection