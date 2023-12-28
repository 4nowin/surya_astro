@extends('admin.layouts.admin')

@section('content')
    <div class="bg-light p-4 rounded">
        <h2>Add new banner</h2>
        <div class="lead">
            Add new banner.
        </div>

        <div class="container mt-4">

            <form method="post" action="{{ route('banner.store') }}">
                @csrf


                <x-image-chooser class="border border-grey mt-5" height="150px" width="150px" :value="null"
                name="image" />

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
                    <label for="description" class="form-label">Description</label>
                    <input value="{{ old('description') }}" 
                        type="text" 
                        class="form-control" 
                        name="description" 
                        placeholder="Description" required>

                    @if ($errors->has('description'))
                        <span class="text-danger text-left">{{ $errors->first('description') }}</span>
                    @endif
                </div>

                <div class="mb-3">
                    <label for="position" class="form-label">Description</label>
                    <input value="{{ old('position') }}" 
                        type="text" 
                        class="form-control" 
                        name="position" 
                        placeholder="Position" required>

                    @if ($errors->has('position'))
                        <span class="text-danger text-left">{{ $errors->first('position') }}</span>
                    @endif
                </div>

                <div class="mb-3">
                    <label for="url" class="form-label">Description</label>
                    <input value="{{ old('url') }}" 
                        type="text" 
                        class="form-control" 
                        name="url" 
                        placeholder="Url" required>

                    @if ($errors->has('url'))
                        <span class="text-danger text-left">{{ $errors->first('description') }}</span>
                    @endif
                </div>
                <button type="submit" class="btn btn-primary">Save Banner</button>
                <a href="{{ route('banner.index') }}" class="btn btn-default">Back</a>
            </form>
        </div>

    </div>
@endsection