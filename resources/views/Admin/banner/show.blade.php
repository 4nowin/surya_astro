@extends('admin.layouts.admin')

@section('content')
    <div class="bg-light p-4 rounded">
        <h2>Show banner</h2>
        <div class="lead">

        </div>

        <div class="container mt-4">
            <div>
                Title: {{ $banner->title }}
            </div>
            <div>
                Description: {{ $banner->description }}
            </div>
            <div>
                Body: {{ $banner->body }}
            </div>
        </div>

    </div>
    <div class="mt-4">
        <a href="{{ route('banner.edit', $banner->id) }}" class="btn btn-info">Edit</a>
        <a href="{{ route('banner.index') }}" class="btn btn-default">Back</a>
    </div>
@endsection