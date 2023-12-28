@extends('admin.layouts.admin')

@section('content')

    <h1 class="mb-3">Own Yog</h1>

    <div class="bg-light p-4 rounded">
        <h2>banners</h2>
        <div class="lead">
            Manage your banners here.
            <a href="{{ route('banner.create') }}" class="btn btn-primary btn-sm float-right">Add banner</a>
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
            @foreach ($banners as $key => $banner)
            <tr>
                <td>{{ $banner->id }}</td>
                <td>{{ $banner->title }}</td>
                <td>
                    <a class="btn btn-info btn-sm" href="{{ route('banner.show', $banner->id) }}">Show</a>
                </td>
                <td>
                    <a class="btn btn-primary btn-sm" href="{{ route('banner.edit', $banner->id) }}">Edit</a>
                </td>
                <td>
                    {!! Form::open(['method' => 'DELETE','route' => ['banner.destroy', $banner->id],'style'=>'display:inline']) !!}
                    {!! Form::submit('Delete', ['class' => 'btn btn-danger btn-sm']) !!}
                    {!! Form::close() !!}
                </td>
            </tr>
            @endforeach
        </table>

        <div class="d-flex">
            {!! $banners->links() !!}
        </div>

    </div>
@endsection