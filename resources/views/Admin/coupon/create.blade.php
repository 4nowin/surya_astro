@extends('admin.layouts.admin')

@section('content')
    <h4>New Coupon</h4>
    <p class="text-muted">Add new post.</p>

    <div class="mt-4">

        <form method="POST" action="{{ route('coupon.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label for="code" class="form-label">Code</label>
                <input value="{{ old('code') }}" type="text" class="form-control" name="code" placeholder="Name"
                    required>

                @if ($errors->has('code'))
                    <span class="text-danger text-left">{{ $errors->first('code') }}</span>
                @endif
            </div>

            <div class="mb-3">
                <label for="discount" class="form-label">Discount</label>
                <input value="{{ old('discount') }}" type="number" class="form-control" name="discount" placeholder="discount" id="discount" required>

                @if ($errors->has('discount'))
                    <span class="text-danger text-left">{{ $errors->first('discount') }}</span>
                @endif
            </div>

            <div class="mb-3">
                <label for="type" class="form-label">Type</label>
                <select class="form-control" name="type" placeholder="type" id="type" required>
                    <option {{ old('type') == "FLAT" ?"selected":"" }} value="FLAT">Flat</option>
                    <option {{ old('type') == "%" ?"selected":"" }} value="%">%</option>
                </select>
                @if ($errors->has('type'))
                    <span class="text-danger text-left">{{ $errors->first('type') }}</span>
                @endif
            </div>

            <div class="mb-3">
                <label for="remaining_count" class="form-label">remaining_count</label>
                <input value="{{ old('remaining_count') }}" type="number" class="form-control" name="remaining_count"
                    placeholder="remaining_count" id="remaining_count" required>

                @if ($errors->has('remaining_count'))
                    <span class="text-danger text-left">{{ $errors->first('remaining_count') }}</span>
                @endif
            </div>
            <button type="submit" class="btn btn-primary">Save Coupon</button>
            <a href="{{ route('coupon.index') }}" class="btn btn-default">Back</a>
        </form>
    </div>
@endsection
