@extends('admin.layouts.admin')

@section('content')
    <div>
        <h4>Coupons</h4>
        <div class="d-flex">
            <div class="flex-grow-1">Manage your Coupon here.</div>
            <a href="{{ route('coupon.create') }}" class="btn btn-primary btn-sm float-right">Add coupon</a>
        </div>

        <div class="mt-2">
            @include('admin.layouts.partials.messages')
        </div>

        <div class="row">
            @foreach ($coupons as $key => $coupon)
                <div class="col-md-3 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-light d-flex align-items-center">
                            <div class="flex-grow-1">
                                <i class="fas fa-rug"></i> {{ $coupon->code }}
                            </div>
                            <span>
                                @if($coupon->type == "FLAT")
                                @money($coupon->discount)
                                @else
                                {{$coupon->discount}}%
                                @endif
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col text-center">
                                    <span class="fs-5">{{ $coupon->remaining_count }}</span><br />
                                    <span class="text-primary">Remaining</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer p-0">
                            <div class="btn-group d-flex" role="group" aria-label="Basic example">
                                <a class="btn btn-light flex-grow-1 text-primary"
                                    href="{{ route('coupon.edit', $coupon->id) }}">
                                    <i class="fas fa-pencil"></i>
                                </a>
                                <div class="flex-grow-1 d-flex align-items-center">
                                    {!! Form::open(['method' => 'DELETE', 
                                    'route' => ['coupon.destroy', $coupon->id], 'class'=>"d-block flex-grow-1"]) !!}
                                    {!! Form::submit('Delete', ['class' => 'btn btn-default text-danger d-block w-100']) !!}
                                    {!! Form::close() !!}
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            @endforeach
        </div>

        <div class="d-flex mt-4">
            @include('admin.common.pagination', ['paginator' => $coupons])
        </div>

    </div>
@endsection
