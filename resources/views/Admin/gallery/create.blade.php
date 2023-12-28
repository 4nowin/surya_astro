@extends('admin.layouts.admin')

@section('content')
    <div class="bg-light p-4 rounded">
        <h2>Add new gallery image</h2>
        <div class="lead">
            Add new gallery image.
        </div>

        <div class="container mt-4">

            <form method="POST" action="{{ route('gallery.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="title" class="form-label">Title</label>
                    <input value="{{ old('title') }}" 
                        type="text" 
                        class="form-control" 
                        name="title" 
                        placeholder="Title" required>

                    @if ($errors->has('title'))
                        <span class="text-danger text-left">{{ $errors->first('title') }}</span>
                    @endif
                </div>

                <div class="mb-3">
                    <label for="image" class="form-label">Image</label>
                    <input
                        type="file" 
                        class="form-control" 
                        name="image"
                        required>

                    @if ($errors->has('image'))
                        <span class="text-danger text-left">{{ $errors->first('image') }}</span>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary">Save Gallery</button>
                <a href="{{ route('gallery.index') }}" class="btn btn-default">Back</a>
            </form>
        </div>

    </div>
@endsection