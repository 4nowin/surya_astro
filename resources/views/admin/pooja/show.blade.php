@extends('admin.layouts.admin')

@section('content')
<div class="bg-light p-4 rounded">
    <h2>{{$pooja->title}}'s pooja</h2>
    <div class="lead">

    </div>

    <div class="container mt-4">
        <div>
        <img src="{{ $pooja->image }}"  height="250px" width="auto"/>
        </div>
        <div class="mt-2">
        <strong>Date:</strong> {{ $pooja->start_date }}
        </div>
        <div class="mt-2">
        <strong>Title:</strong> {{ $pooja->title }}
        </div>
        <div class="mt-2">
        <strong>Tag:</strong> {{ $pooja->tag }}
        </div>
        <div class="mt-2">
        <strong>Excerpt:</strong> {!! $pooja->excerpt !!}
        </div><br/>
        <div>
        <strong>Description:</strong> {!! $pooja->description !!}
        </div>
        <div class="mt-2">
        <strong>Price:</strong> ₹{{ $pooja->price }} &nbsp;&nbsp;&nbsp;<strike>₹{{ $pooja->original_price }}</strike>
        </div>
    </div>

</div>
<div class="mt-4">
    <a href="{{ route('pooja.edit', $pooja->id) }}" class="btn btn-info">Edit</a>
    <a href="{{ route('pooja.index') }}" class="btn btn-default">Back</a>
</div>
@endsection