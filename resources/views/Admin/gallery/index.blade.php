@extends('admin.layouts.admin')

@section('content')
    <div class="bg-light p-4 rounded">
        <h2>Gallery</h2>
        <div class="lead">
            Manage your images here.
            <a href="{{ route('gallery.create') }}" class="btn btn-primary btn-sm float-right">Add post</a>
        </div>

        <div class="mt-2">
            @include('admin.layouts.partials.messages')
        </div>

        <table class="table table-bordered">
          <tr>
             <th width="1%">No</th>
             <td width="1%"></td>
             <th>Title</th>
             <th width="3%" colspan="2">Action</th>
          </tr>
            @foreach ($gallery_images as $key => $gallery_image)
            <tr>
                <td>{{ $gallery_image->id }}</td>
                <td>
                    <img src="{{url('/storage/uploads/'.$gallery_image->path)}}" width="60px" height="60px" style="object-fit:cover; background-size:cover; background-position:cover"/>
                </td>
                <td>{{ $gallery_image->title }}</td>
                <td>
                    <a class="btn btn-info btn-sm" href="{{ route('gallery.show', $gallery_image->id) }}">Show</a>
                </td>
                <td>
                    {!! Form::open(['method' => 'DELETE','route' => ['gallery.destroy', $gallery_image->id],'style'=>'display:inline']) !!}
                    {!! Form::submit('Delete', ['class' => 'btn btn-danger btn-sm']) !!}
                    {!! Form::close() !!}
                </td>
            </tr>
            @endforeach
        </table>

        <div class="d-flex">
            {!! $gallery_images->links() !!}
        </div>

    </div>
@endsection