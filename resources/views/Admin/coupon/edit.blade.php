@extends('admin.layouts.admin')

@section('content')
    <h4>New Coupon</h4>

    <div class="mt-4">

        <form method="POST" action="{{ route('coupon.update', $coupon->id) }}" enctype="multipart/form-data">
            @csrf
            @method('patch')

            <div class="mb-3">
                <label for="code" class="form-label">Code</label>
                <input type="text" class="form-control" name="code" placeholder="Code" value="{{ $coupon->code }}"
                    required>

                @if ($errors->has('code'))
                    <span class="text-danger text-left">{{ $errors->first('code') }}</span>
                @endif
            </div>

            <div class="mb-3">
                <label for="discount" class="form-label">Discount</label>
                <input type="number" class="form-control" name="discount" placeholder="discount" id="discount"
                    value="{{ $coupon->discount }}" required>

                @if ($errors->has('discount'))
                    <span class="text-danger text-left">{{ $errors->first('discount') }}</span>
                @endif
            </div>

            <div class="mb-3">
                <label for="type" class="form-label">Type</label>
                <select value="{{ $coupon->type }}" class="form-control" name="type" id="type" required>
                    <option {{ $coupon->type == 'FLAT' ? 'selected' : '' }} value="FLAT">Flat</option>
                    <option {{ $coupon->type == '%' ? 'selected' : '' }} value="%">%</option>
                </select>
                @if ($errors->has('type'))
                    <span class="text-danger text-left">{{ $errors->first('type') }}</span>
                @endif
            </div>

            <div class="mb-3">
                <label for="remaining_count" class="form-label">Reamining</label>
                <input value="{{ $coupon->remaining_count }}" type="number" class="form-control" name="remaining_count"
                    placeholder="remaining_count" id="remaining_count" required>

                @if ($errors->has('remaining_count'))
                    <span class="text-danger text-left">{{ $errors->first('remaining_count') }}</span>
                @endif
            </div>


            <button type="submit" class="btn btn-primary">Save Teacher</button>
            <a href="{{ route('coupon.index') }}" class="btn btn-default">Back</a>
        </form>
    </div>
@endsection
