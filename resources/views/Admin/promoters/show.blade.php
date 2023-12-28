@extends('admin.layouts.admin')

@section('content')
    <div class="bg-light p-4 rounded">
        <h2>Show promoter</h2>
        <div class="lead">

        </div>

        <div class="container mt-4">
            <div>
                Title: {{ $promoter->title }}
            </div>
            <div>
                Description: {{ $promoter->description }}
            </div>
            <div>
                Body: {{ $promoter->body }}
            </div>
        </div>

    </div>
    <div class="mt-4">
        <a href="{{ route('promoters.edit', $promoter->id) }}" class="btn btn-info">Edit</a>
        <a href="{{ route('promoters.index') }}" class="btn btn-default">Back</a>
    </div>
@endsection