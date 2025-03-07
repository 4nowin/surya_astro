@extends('admin.layouts.admin')

@section('content')
    <div class="bg-light p-4 rounded">
        <h2>Add new location</h2>
        <div class="lead">
            Add new location.
        </div>

        <div class="container mt-4">

            <form method="POST" action="{{ route('locations.store') }}">
                @csrf
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input value="{{ old('name') }}" type="text" class="form-control" name="name" placeholder="Name"
                        required>

                    @if ($errors->has('name'))
                        <span class="text-danger text-left">{{ $errors->first('name') }}</span>
                    @endif
                </div>

                <div class="mb-3">
                    <label for="country" class="form-label">Country</label>
                    <select value="{{ old('country') }}" class="form-control" name="country" placeholder="Country" required>
                        <option> </option>
                        @foreach (Countries::getList('en') as $code => $name)
                            <option value="{{$code}}" @if(old('country')=== $code) {{"selected"}} @endif>{{$name}}</option>
                        @endforeach


                    </select>

                    @if ($errors->has('country'))
                        <span class="text-danger text-left">{{ $errors->first('country') }}</span>
                    @endif
                </div>


                <button type="submit" class="btn btn-primary">Save Location</button>
                <a href="{{ route('locations.index') }}" class="btn btn-default">Back</a>
            </form>
        </div>

    </div>
@endsection
