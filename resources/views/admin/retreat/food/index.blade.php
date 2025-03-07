@extends('admin.layouts.admin')

@section('content')


<div class="bg-light p-4 rounded">
    <h4>Food</h4>
    <div class="lead">
        Manage your Food here.
        <a href="{{ route('food.create') }}" class="btn btn-primary btn-sm float-right">Add food</a>
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
        @foreach ($foods as $key => $food)
        <tr>
            <td>{{ $food->id }}</td>
            <td>{{ $food->name }}</td>
            <td width="1%">
                <a class="btn btn-primary btn-sm" href="{{ route('food.edit', $food->id) }}">Edit</a>
            </td>
            <td width="1%">
                {!! Form::open(['method' => 'DELETE','route' => ['food.destroy', $food->id],'style'=>'display:inline']) !!}
                {!! Form::submit('Delete', ['class' => 'btn btn-danger btn-sm']) !!}
                {!! Form::close() !!}
            </td>
        </tr>
        @endforeach
    </table>
    </div>


    <div class="d-flex mt-4">
        @include('admin.common.pagination', ['paginator' => $foods])
    </div>

</div>
@endsection


