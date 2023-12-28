@extends('admin.layouts.admin')

@section('content')

<h4>New Teacher</h4>
<p class="text-muted">Add new post.</p>

<div class="mt-4">

    <form method="POST" action="{{ route('teacher.store') }}" enctype="multipart/form-data">
        @csrf

        <x-image-chooser class="border border-grey p-4 mb-3" height="auto" width="100%" :value="null" name="cover">
                <x-image-chooser class="border border-grey mt-5" height="150px" width="150px" :value="null"
                    name="image" />
        </x-image-chooser>

        <div class="mb-3">
            <label for="name" class="form-label">Title</label>
            <input value="{{ old('name') }}" type="text" class="form-control" name="name" placeholder="Name" required>

            @if ($errors->has('name'))
            <span class="text-danger text-left">{{ $errors->first('name') }}</span>
            @endif
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">phone</label>
            <input value="{{ old('phone') }}" type="text" class="form-control" name="phone" placeholder="phone" id="phone" required>

            @if ($errors->has('phone'))
            <span class="text-danger text-left">{{ $errors->first('phone') }}</span>
            @endif
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">email</label>
            <input value="{{ old('email') }}" type="text" class="form-control" name="email" placeholder="email" id="email" required>

            @if ($errors->has('email'))
            <span class="text-danger text-left">{{ $errors->first('email') }}</span>
            @endif
        </div>

        <div class="mb-3">
            <label for="excerpt" class="form-label">excerpt</label>
            <input value="{{ old('excerpt') }}" type="text" class="form-control" name="excerpt" placeholder="excerpt" id="excerpt" required>

            @if ($errors->has('excerpt'))
            <span class="text-danger text-left">{{ $errors->first('excerpt') }}</span>
            @endif
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">description</label>
            <input value="{{ old('description') }}" type="text" class="form-control" name="description" placeholder="description" id="description" required>

            @if ($errors->has('description'))
            <span class="text-danger text-left">{{ $errors->first('description') }}</span>
            @endif
        </div>

        <div class="mb-3">
            <label for="tags" class="form-label">tags</label>
            <input value="{{ old('tags') }}" type="text" class="form-control" name="tags" placeholder="tags" id="tags" required>

            @if ($errors->has('tags'))
            <span class="text-danger text-left">{{ $errors->first('tags') }}</span>
            @endif
        </div>


        <button type="submit" class="btn btn-info">Save Teacher</button>
        <a href="{{ route('teacher.index') }}" class="btn btn-dark">Back</a>
    </form>
</div>


@endsection