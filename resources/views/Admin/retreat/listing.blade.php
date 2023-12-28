@extends('admin.layouts.admin')
@section('content')
    <div>


        <h4 class="flex-grow-1">{{ __('Retreats') }}</h4>

    </div>

    <div>
        <form>
            <div class="row">
                <div class="col-auto">
                    <input class="form-control" type="date" name="fromDate"
                        value="{{ app('request')->input('fromDate') }}" />
                </div>
                <div class="col-auto">
                    <input class="form-control" type="date" name="toDate" value="{{ app('request')->input('toDate') }}" />
                </div>

                <div class="col-auto">
                    <select class="form-select" type="date" name="status"
                        value="{{ app('request')->input('toDate') }}">
                        <option value="">---STATUS---</option>
                        <option {{ app('request')->input('status') === 'CREATED' ? 'selected' : '' }}>CREATED</option>
                        <option {{ app('request')->input('status') === 'CLOSED' ? 'selected' : '' }}>CLOSED</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary" type="submit">Search</button>
                </div>
                <div class="col">
                </div>

                <div class="col-auto">
                    @if (Auth::user()->can('retreat:create'))
                        <a data-bs-toggle="modal" data-bs-target="#new-retreat-modal" class="btn btn-primary">Create Retreat</a>
                    @endif
                </div>
            </div>
        </form>
    </div>


    <div class="row mt-4">
        @foreach ($retreats as $index => $retreat)
            @php
                $revenue = isset($sales[$retreat->id]) ? $sales[$retreat->id]['revenue'] : 0;
                $quantity = isset($sales[$retreat->id]) ? $sales[$retreat->id]['quantity'] : 0;
            @endphp
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-light d-flex align-items-center">
                        <div class="bg-white text-primary text-center p-1 border-primary border"
                            style="width: 50px; height: 60px; margin-top: -20px">
                            <small>{{ \Carbon\Carbon::parse($retreat->start_date)->format('M') }}</small>
                            <span class="fs-5">{{ \Carbon\Carbon::parse($retreat->start_date)->format('d') }}</span>
                        </div>
                        <div class="flex-grow-1 ps-3">
                            <h5 class="mb-0">{{ $retreat->name }}</h5>
                            <p class="mb-0">At {{ $retreat->venue }}</p>
                        </div>
                        <div>
                            <div class="">
                                <select class="form-select form-select-sm border-0" inline-edit-table="retreats"
                                    inline-edit-field="status" inline-edit-where="id='{{ $retreat->id }}'">
                                    <option {{ $retreat->status === 'CREATED' ? 'selected' : '' }}>CREATED</option>
                                    <option {{ $retreat->status === 'CLOSED' ? 'selected' : '' }}>CLOSED</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col text-center">
                                <span class="fs-5">{{ $quantity }}</span><br />
                                <span class="text-primary">Passes Sold</span>
                            </div>

                            <div class="col text-center">
                                <span class="fs-5">
                                    @money($revenue)
                                </span><br />
                                <span class="text-primary">Revenue</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer p-0">
                        <div class="btn-group d-flex" role="group" aria-label="Basic example">
                            @if (Auth::user()->can('retreat:dashboard'))
                                <a class="btn btn-light flex-grow-1 text-primary"
                                    href="{{ url('/admin/retreat/' . $retreat->id . '/dashboard') }}">
                                    <i class="fas fa-eye"></i>
                                </a>
                            @endif
                            @if (Auth::user()->can('retreat:tickets'))
                                <a class="btn btn-light flex-grow-1 text-primary"
                                    href="{{ url('/admin/retreat/' . $retreat->id . '/tickets') }}">
                                    <i class="fas fa-ticket"></i>
                                </a>
                            @endif
                            @if (Auth::user()->can('retreat:orders'))
                                <a class="btn btn-light flex-grow-1 text-primary"
                                    href="{{ url('/admin/retreat/' . $retreat->id . '/orders') }}">
                                    <i class="fa-solid fa-bag-shopping"></i>
                                </a>
                            @endif
                            @if (Auth::user()->can('retreat:check-in'))
                                <a class="btn btn-light flex-grow-1 text-primary"
                                    href="{{ url('/admin/retreat/' . $retreat->id . '/check-in') }}">
                                    <i class="fa-solid fa-person-booth"></i>
                                </a>
                            @endif
                            @if (Auth::user()->can('retreat:delete'))
                                <a class="btn btn-light flex-grow-1 text-danger"
                                    onclick="ask('Are you sure want to delete the retreat?').then(e=>window.location.href=`{{ url('admin/retreat/delete/' . $retreat->id) }}`).catch(console.log)">
                                    <i class="fas fa-trash"></i>
                                </a>
                            @endif
                            @if (Auth::user()->can('retreat:customize'))
                                <a class="btn btn-light flex-grow-1 text-success"
                                    href="{{ url('/admin/retreat/' . $retreat->id . '/customize') }}">
                                    <i class="fas fa-pencil"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        @endforeach

    </div>

    <div class="mt-2 text-end">
        @include('admin.common.pagination', ['paginator' => $retreats])
    </div>
    </div>
   
@endsection
