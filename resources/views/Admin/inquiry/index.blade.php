@extends('admin.layouts.admin')

@section('content')

<div class="d-flex">
    <div class="flex-grow-1">
        <h2>New Enquiries</h2>
        <p class="text-muted">New Enquiries are listed here.</p>
    </div>
</div>
<div class="mt-2">
    @include('admin.layouts.partials.messages')
</div>

<table class="table table-bordered">
    <tr>
        <th width="1%">No</th>
        <th>Name</th>
        <th>For</th>
        <th>Phone</th>
        <th width="3%" colspan="3" class="text-center">Action</th>
    </tr>
    @foreach ($inquiries as $key => $inquiry)
    @if($inquiry->status === 0)
    <tr>
        <td>{{ $inquiry->id }}</td>
        <td>{{ $inquiry->name }}</td>
        <td>{{ $inquiry->for }}</td>
        <td>{{ $inquiry->phone }}</td>
        <td>
            <a class="btn btn-info btn-sm" href="{{ route('enquiry.show', $inquiry->id) }}">Show</a>
        </td>
        
    </tr>
    @endif
    @endforeach
</table>

<div class="d-flex">
</div>

</div>
@endsection