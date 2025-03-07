@extends('admin.layouts.admin')

@section('content')
    <div class="bg-light p-4 rounded">
        <h2>Update banner</h2>
        <div class="lead">
            Edit banner.
        </div>

        <div class="container mt-4">

            <form method="post" action="{{ route('banner.update', $banner->id) }}">
                @method('patch')
                @csrf

                <x-image-chooser class="border border-grey mt-5" height="150px" width="150px" :value="$banner->image"
                name="image" />

                <div class="mb-3">
                    <label for="title" class="form-label">Title</label>
                    <input value="{{ $banner->title }}" 
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
                    <input value="{{ $banner->description }}" 
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
                    <input value="{{ $banner->position }}" 
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
                    <input value="{{ $banner->url }}" 
                        type="text" 
                        class="form-control" 
                        name="url" 
                        placeholder="URL" required>

                    @if ($errors->has('url'))
                        <span class="text-danger text-left">{{ $errors->first('url') }}</span>
                    @endif
                </div>

                     <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('banner.index') }}" class="btn btn-default">Back</a>
            </form>
        </div>

    </div>
@endsection