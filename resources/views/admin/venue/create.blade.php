@extends('admin.layouts.admin')

@section('content')
    <h4>New Venue</h4>
    <p class="text-muted">Add new post.</p>

    <div class="mt-4">

        <form method="POST" action="{{ route('venue.store') }}" enctype="multipart/form-data">
            @csrf


            <!----- Image Chooser --->
            <x-image-chooser class="border border-grey p-4 mb-3" height="auto" width="100%" :value="null" name="cover">
                <x-image-chooser class="border border-grey mt-5" height="150px" width="150px" :value="null"
                    name="logo" />
            </x-image-chooser>




            <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input value="{{ old('name') }}" type="text" class="form-control" name="name" placeholder="Name"
                    required>

                @if ($errors->has('name'))
                    <span class="text-danger text-left">{{ $errors->first('name') }}</span>
                @endif
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="mobile" class="form-label">Mobile</label>
                        <input value="{{ old('mobile') }}" type="text" class="form-control" name="mobile"
                            placeholder="mobile" id="mobile" required>

                        @if ($errors->has('mobile'))
                            <span class="text-danger text-left">{{ $errors->first('mobile') }}</span>
                        @endif
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input value="{{ old('email') }}" type="text" class="form-control" name="email"
                            placeholder="email" id="email" required>

                        @if ($errors->has('email'))
                            <span class="text-danger text-left">{{ $errors->first('email') }}</span>
                        @endif
                    </div>

                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="founded_at" class="form-label">Founded At</label>
                        <input value="{{ old('founded_at') }}" type="date" class="form-control" name="founded_at"
                            placeholder="founded_at" id="founded_at" required>

                        @if ($errors->has('founded_at'))
                            <span class="text-danger text-left">{{ $errors->first('founded_at') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input value="{{ old('location') }}" type="text" class="form-control" name="location"
                            placeholder="location" id="location" required>

                        @if ($errors->has('location'))
                            <span class="text-danger text-left">{{ $errors->first('location') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <input value="{{ old('address') }}" type="text" class="form-control" name="address"
                            placeholder="address" id="address" required>

                        @if ($errors->has('address'))
                            <span class="text-danger text-left">{{ $errors->first('address') }}</span>
                        @endif
                    </div>
                </div>

            </div>



            <div class="mb-3">
                <label for="excerpt" class="form-label">Excerpt</label>
                <input value="{{ old('excerpt') }}" type="text" class="form-control" name="excerpt"
                    placeholder="excerpt" id="excerpt" required>

                @if ($errors->has('excerpt'))
                    <span class="text-danger text-left">{{ $errors->first('excerpt') }}</span>
                @endif
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>

                <x-rich-text-editor name="description" required="required" placeholder="Enter retreat description" required>
                    {{ old('description') }}</x-rich-text-editor>

                @if ($errors->has('description'))
                    <span class="text-danger text-left">{{ $errors->first('description') }}</span>
                @endif
            </div>



            <div class="mb-3">
                <label for="tags" class="form-label">Tags</label>
                <input value="{{ old('tags') }}" type="text" class="form-control" name="tags"
                    placeholder="Please enter tags with comma seperated" id="tags" required>

                @if ($errors->has('tags'))
                    <span class="text-danger text-left">{{ $errors->first('tags') }}</span>
                @endif
            </div>


            <div class="mb-3">
                <label for="map_url" class="form-label">Map Url</label>
                <input value="{{ old('map_url') }}" type="text" class="form-control" name="map_url"
                    placeholder="Please enter map url" id="map_url" required>

                @if ($errors->has('map_url'))
                    <span class="text-danger text-left">{{ $errors->first('map_url') }}</span>
                @endif
            </div>


            <button type="submit" class="btn btn-primary">Save Venue</button>
            <a href="{{ route('venue.index') }}" class="btn btn-default">Back</a>
        </form>
    </div>
@endsection
