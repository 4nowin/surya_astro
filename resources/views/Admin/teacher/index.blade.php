@extends('admin.layouts.admin')

@section('content')


<div class="bg-light p-4 rounded">
    <h4>Teacher</h4>
    <div class="lead">
        Manage your Teacher here.
        <a href="{{ route('teacher.create') }}" class="btn btn-primary btn-sm float-right">Add teacher</a>
    </div>

    <div class="mt-2">
        @include('admin.layouts.partials.messages')
    </div>

    <div class="overflow-auto card rounded mt-4">
        <table class="table table-striped bg-white mb-0">
        <tr>
            <th width="1%">No</th>
            <th>Name</th>
            <th colspan="2">Action</th>
        </tr>
        @foreach ($teachers as $key => $teacher)
        <tr>
            <td>{{ $teacher->id }}</td>
            <td>{{ $teacher->name }}</td>
            <td width="1%">
                <a class="btn btn-primary btn-sm" href="{{ route('teacher.edit', $teacher->id) }}">Edit</a>
            </td>
            <td width="1%">
                {!! Form::open(['method' => 'DELETE','route' => ['teacher.destroy', $teacher->id],'style'=>'display:inline']) !!}
                {!! Form::submit('Delete', ['class' => 'btn btn-danger btn-sm']) !!}
                {!! Form::close() !!}
            </td>
        </tr>
        @endforeach
    </table>
    </div>


    <div class="d-flex mt-4">
        @include('admin.common.pagination', ['paginator' => $teachers])
    </div>

</div>
@endsection


