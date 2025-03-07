@extends('admin.layouts.admin')

@section('content')

<h4>New Accommodation</h4>
<p class="text-muted">Add new post.</p>

<div class="mt-4">

    <form method="POST" action="{{ route('accommodation.update', $accommodation->id) }}" enctype="multipart/form-data">
        @csrf
        @method('patch')
        <x-image-chooser class="border border-grey p-4 mb-3" height="auto" width="100%" :value="$accommodation->cover" name="cover">
                <x-image-chooser class="border border-grey mt-5" height="150px" width="150px" :value="$accommodation->image"
                    name="image" />
        </x-image-chooser>

        <div class="mb-3">
            <label for="name" class="form-label">Title</label>
            <input type="text" class="form-control" name="name" placeholder="Name" value="{{$accommodation->name}}" required>

            @if ($errors->has('name'))
            <span class="text-danger text-left">{{ $errors->first('name') }}</span>
            @endif
        </div>

        <div class="mb-3">
            <label for="excerpt" class="form-label">excerpt</label>
            <input value="{{$accommodation->excerpt}}" type="text" class="form-control" name="excerpt" placeholder="excerpt" id="excerpt" required>

            @if ($errors->has('excerpt'))
            <span class="text-danger text-left">{{ $errors->first('excerpt') }}</span>
            @endif
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">description</label>
            <input value="{{$accommodation->description}}" type="text" class="form-control" name="description" placeholder="description" id="description" required>

            @if ($errors->has('description'))
            <span class="text-danger text-left">{{ $errors->first('description') }}</span>
            @endif
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="amenities" class="form-label">Amenities</label>
                    <x-selectize :options="$amenities" :multiple="true" name="amenities[]" :selected="$accommodation->amenities"></x-selectize>
                </div>
            </div>

            <div class="col-md-6">
                <div class="mb-3">
                    <label for="tags" class="form-label">tags</label>
                    <input value="{{$accommodation->tags}}" type="text" class="form-control" name="tags" placeholder="tags" id="tags" required>

                    @if ($errors->has('tags'))
                    <span class="text-danger text-left">{{ $errors->first('tags') }}</span>
                    @endif
                </div>
            </div>
        </div>

       


        <button type="submit" class="btn btn-primary">Save Accommodation</button>
        <a href="{{ route('accommodation.index') }}" class="btn btn-default">Back</a>
    </form>
</div>


@endsection