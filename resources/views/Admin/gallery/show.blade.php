@extends('admin.layouts.admin')

@section('content')
    <div class="bg-light p-4 rounded">
        <h2>Show post</h2>
        <div class="lead">

        </div>

        <div class="container mt-4">
            <div>
                Title: {{ $gallery_image->title }}
            </div>
        
            <div>
            <img src="{{url('/storage/uploads/'.$gallery_image->path)}}" width="70px"/>
            </div>
        </div>

    </div>
    <div class="mt-4">
        <a href="{{ route('gallery.index') }}" class="btn btn-default">Back</a>
    </div>
@endsection