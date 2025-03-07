@extends('admin.layouts.admin')

@section('content')

<h4>New Cancellation</h4>
<p class="text-muted">Add new post.</p>

<div class="mt-4">

    <form method="POST" action="{{ route('cancellation.update', $cancellation->id) }}" enctype="multipart/form-data">
        @csrf
        @method('patch')

        <div class="mb-3">
            <label for="name" class="form-label">Title</label>
            <input type="text" class="form-control" name="name" placeholder="Name" value="{{$cancellation->name}}" required>

            @if ($errors->has('name'))
            <span class="text-danger text-left">{{ $errors->first('name') }}</span>
            @endif
        </div>

        <div class="mb-3">
            <label for="description">Description</label>
            <div>
                    <x-rich-text-editor name="description" required="required" placeholder="Enter retreat description" required>{!! $cancellation ? html_entity_decode($cancellation->description) : '' !!}</x-rich-text-editor>
            </div>
        </div>
       


        <button type="submit" class="btn btn-primary">Save Cancellation</button>
        <a href="{{ route('cancellation.index') }}" class="btn btn-default">Back</a>
    </form>
</div>


@endsection