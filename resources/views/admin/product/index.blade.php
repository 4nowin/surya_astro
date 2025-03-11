@extends('admin.layouts.admin')

@section('title', '| ' . __('product.all_product'))

@section('content')

<div class="d-flex">
    <div class="flex-grow-1">
        <h2>{{ __('product.all_product') }}</h2>
        <p class="text-muted">{{ __('product.manage_product') }}</p>
    </div>
    <div>
        <a href="{{ route('product.create') }}" class="btn btn-primary btn-sm float-right">{{ __('product.add_product') }}</a>
    </div>
</div>
<div class="mt-2">
    @include('admin.layouts.partials.messages')
</div>

<table class="table">
    <thead>
        <tr>
            <th width="5%">#</th>
            <th width="5%">{{ __('product.image') }}</th>
            <th>{{ __('product.name') }}</th>
            <th>{{ __('product.active') }}</th>
            <th width="3%" colspan="3">{{ __('product.action') }}</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($products as $product)
        <tr>
            <th>{{ $product->id }}</th>
            <th><img src="{{ $product->image }}" width="50px" height="auto"></th>
            <td>{{ $product->title }}</td>
            <td>
                <div class="form-check form-switch">
                    {!! Form::open(['method' => 'POST', 'route' => ['product.active', $product->id], 'style' => 'display:inline']) !!}
                    @csrf
                    <input type="hidden" name="active_product" value="0">
                    <input class="form-check-input" type="checkbox" name="active_product" value="1" role="switch" onChange='this.form.submit();' @if($product->active) checked @endif>
                    {!! Form::close() !!}
                </div>
            </td>
            <td>
                <a class="btn btn-info btn-sm" href="{{ route('product.show', $product->id) }}">{{ __('product.show') }}</a>
            </td>
            <td>
                <a class="btn btn-primary btn-sm" href="{{ route('product.edit', $product->id) }}">{{ __('product.edit') }}</a>
            </td>
            <td>
                {!! Form::open(['method' => 'DELETE','route' => ['product.destroy', $product->id],'style'=>'display:inline']) !!}
                @csrf
                {!! Form::submit(__('product.delete'), ['class' => 'btn btn-danger btn-sm']) !!}
                {!! Form::close() !!}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

@endsection
