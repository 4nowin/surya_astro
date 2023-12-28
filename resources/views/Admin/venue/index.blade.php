@extends('admin.layouts.admin')

@section('content')
    <div class="bg-light p-4 rounded">
        <h3>Venues</h3>
        <div class="">
            <p class="text-muted">Manage your venue here.</p>
            <a href="{{ route('venue.create') }}" class="btn btn-primary btn-sm float-right">Add venue</a>
        </div>

        <div class="mt-2">
            @include('admin.layouts.partials.messages')
        </div>


        <div class="row mt-4">
            @foreach ($venues as $key => $venue)
                <div class="col-sm-4">
                    <div class="card">
                        <div class="card-header d-flex align-items-center bg-primary text-light"
                            style="background-image: url({{ url($venue->cover) }}); background-size:cover; background-position:center">
                            <img src="{{ url($venue->logo) }}" style="width:60px; height: 60px;" class="rounded-circle border border-grey" />
                            <div class="flex-grow-1 ps-3">
                                <h5 class="mb-0">{{ $venue->name }}</h5>
                                <p class="mb-0">{{ $venue->location }}</p>
                            </div>
                        </div>
                        <div class="card-body">
                            {{ $venue->excerpt }}
                        </div>
                        <div class="card-footer p-0 d-flex">
                            <a class="btn btn-default flex-grow-1" href="{{ route('venue.edit', $venue->id) }}">Edit</a>
                            <div class="vr"></div>
                            {!! Form::open([
                                'method' => 'DELETE',
                                'route' => ['venue.destroy', $venue->id],
                                'style' => 'display:inline',
                                'class' => 'flex-grow-1',
                            ]) !!}
                            {!! Form::submit('Delete', ['class' => 'btn btn-default  d-block w-100']) !!}
                            {!! Form::close() !!}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>


        <div class="d-flex">
            {!! $venues->links() !!}
        </div>

    </div>
@endsection
