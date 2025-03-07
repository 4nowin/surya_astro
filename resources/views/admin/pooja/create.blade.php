@extends('admin.layouts.admin')

@section('content')

<h4>New Author</h4>
<p class="text-muted">Add new Pooja.</p>

<div class="mt-4">

    <form method="POST" action="{{ route('pooja.store') }}" enctype="multipart/form-data">
        @csrf

        <x-image-chooser class="border border-grey p-4 mb-3" height="200px" width="100%" :value="null" name="image" id="pooja-image"> </x-image-chooser>


        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input type="text" class="form-control" name="title" placeholder="Title" value="{{ old('title') }}" required>

            @if ($errors->has('title'))
            <span class="text-danger text-left">{{ $errors->first('title') }}</span>
            @endif
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="row mb-3">
                    <label for="tag" class="col-md-4 col-form-label text-md-end">Tag</label>
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="tag" placeholder="Tag" value="{{ old('tag') }}" required>
                    </div>

                    @if ($errors->has('tag'))
                    <span class="text-danger text-left">{{ $errors->first('tag') }}</span>
                    @endif
                </div>

            </div>

            <div class="col-md-4">
                <div class="row mb-3">
                    <label for="start_date" class="col-md-4 col-form-label text-md-end">Start Date</label>

                    <div class="col-md-6">
                        <input id="start_date" type="date" class="form-control" placeholder='Enter Start Date here' name="start_date" value="{{ old('start_date') }}" required="">
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="row mb-3">
                    <label for="end_date" class="col-md-4 col-form-label text-md-end">End Date</label>

                    <div class="col-md-6">
                        <input id="end_date" type="date" class="form-control" placeholder='Enter End Date here' name="end_date" value="{{ old('end_date') }}" required="">
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="row mb-3">
                    <label for="price" class="col-md-4 col-form-label text-md-end">Price</label>

                    <div class="col-md-6">
                        <input id="price" type="number" class="form-control" placeholder='Enter Start Date here' name="price" value="{{ old('price') }}" required="">
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="row mb-3">
                    <label for="original_price" class="col-md-4 col-form-label text-md-end">Original Price</label>

                    <div class="col-md-6">
                        <input id="original_price" type="number" class="form-control" placeholder='Enter End Date here' name="original_price" value="{{ old('original_price') }}" required="">
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="excerpt">Excerpt</label>
            <div>
                <x-rich-text-editor name="excerpt" placeholder="Enter news excerpt">{!! old('excerpt') !!}
                </x-rich-text-editor>
                @if ($errors->has('excerpt'))
                <span class="text-danger">{{ $errors->first('excerpt') }}</span>
                @endif
            </div>
        </div>

        <div class="mb-3">
            <label for="description">Description</label>
            <div>
                <x-rich-text-editor name="description" placeholder="Enter Description here">{!! old('description') !!}
                </x-rich-text-editor>
                @if ($errors->has('description'))
                <span class="text-danger">{{ $errors->first('description') }}</span>
                @endif
            </div>
        </div>

        <button type="submit" class="btn btn-info">Save Pooja</button>
        <a href="{{ route('pooja.index') }}" class="btn btn-dark">Back</a>
    </form>
</div>


@endsection