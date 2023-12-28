@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex">
        <div class="flex-grow-1">
            <h4>Amenities</h4>
            Manage your Amenities here.
        </div>
        <div>
            <a href="{{ route('amenities.create') }}" class="btn btn-primary btn-sm float-right">Add Amenities</a>
        </div>
    </div>

    <div class="mt-4">
        <form>
            <div class="row">
                <input type="hidden" name="page" value="{{ app('request')->input('page') }}" />
                <div class="col-auto">
                    <div class="input-group">
                        <span class="input-group-text" id="basic-addon1"><i class="fas fa-search"></i></span>
                        <input class="form-control" placeholder="Search Amenities" name="keyword"
                            value="{{ app('request')->input('keyword') }}" />
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </div>
        </form>
    </div>

    <div class="mt-2">
        @include('admin.layouts.partials.messages')
    </div>

    @if (count($amenities) > 0)
        <div class="overflow-auto card rounded mt-4">
            <table class="table table-striped bg-white mb-0">
                <thead>
                    <tr>
                        <th scope="col" width="1%">#</th>
                        <th scope="col">Name</th>
                        <th scope="col">Icon</th>
                        <th scope="col" width="3%"></th>
                    </tr>
                </thead>
                @foreach ($amenities as $key => $amenity)
                    <tr>
                        <td>{{ $amenity->id }}</td>
                        <td>{{ $amenity->name }}</td>
                        <td> <i class="{{ $amenity->icon }}"></i> {{ $amenity->icon }}</td>
                        <td class="text-nowrap">
                            {{-- <a class="btn btn-sm btn-link py-0" href="{{ route('amenities.show', $amenity->id) }}"><i class="fas fa-eye"></i></a> --}}
                            <a class="btn btn-sm btn-link" href="{{ route('amenities.edit', $amenity->id) }}"><i class="fas fa-pencil"></i></a></li>

                            {!! Form::open([
                                'method' => 'DELETE',
                                'route' => ['amenities.destroy', $amenity->id],
                                'style' => 'display:inline',
                                'onsubmit'=>'if(!confirm("Are you sure?")){event.preventDefault();}'
                            ]) !!}
                            {!! Form::button('<i class="fas fa-trash"></i>', ['class' => 'btn btn-sm btn-link text-danger', 'type' => 'submit', ]) !!}
                            {!! Form::close() !!}

                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    @else
        <div class="text-center mt-4">
            <p class="mb-0"><i class="fas fa-circle-info"></i> No data found</p>
        </div>
    @endif

    <div class="d-flex mt-4">
        @include('admin.common.pagination', ['paginator' => $amenities])
    </div>
@endsection
