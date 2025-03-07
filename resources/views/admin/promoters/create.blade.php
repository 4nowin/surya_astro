@extends('admin.layouts.admin')

@section('content')
    <div class="bg-light p-4 rounded">
        <h2>Add new promoter</h2>
        <div class="lead">
            Add new promoter.
        </div>

        <div class="container mt-4">

            <form method="POST" action="{{ route('promoters.store') }}">
                @csrf
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input value="{{ old('name') }}" 
                        type="text" 
                        class="form-control" 
                        name="name" 
                        placeholder="Name" required>

                    @if ($errors->has('name'))
                        <span class="text-danger text-left">{{ $errors->first('name') }}</span>
                    @endif
                </div>

                <div class="mb-3">
                    <label for="commission" class="form-label">Commission</label>
                    <input value="{{ old('commission') }}" 
                        type="number" 
                        class="form-control" 
                        name="commission" 
                        placeholder="Commission" required>

                    @if ($errors->has('commission'))
                        <span class="text-danger text-left">{{ $errors->first('commission') }}</span>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary">Save Promoter</button>
                <a href="{{ route('promoters.index') }}" class="btn btn-default">Back</a>
            </form>
        </div>

    </div>
@endsection