@extends('admin.layouts.admin')

@section('content')
    <h4>New Cancellation</h4>
    <p class="text-muted">Add new post.</p>

    <div class="mt-4">

        <form method="POST" action="{{ route('cancellation.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">Title</label>
                <input value="{{ old('name') }}" type="text" class="form-control" name="name" placeholder="Name"
                    required>

                @if ($errors->has('name'))
                    <span class="text-danger text-left">{{ $errors->first('name') }}</span>
                @endif
            </div>

            <div class="mb-3">
                <label for="description">Description</label>
                <div>
                    <x-rich-text-editor name="description" required="required" placeholder="Enter retreat description" required></x-rich-text-editor>
                </div>
            </div>


            <button type="submit" class="btn btn-info">Save Cancellation</button>
            <a href="{{ route('cancellation.index') }}" class="btn btn-dark">Back</a>
        </form>
    </div>
@endsection
