@extends('admin.layouts.admin')

@section('content')
<div class="bg-light p-4 rounded">
    <h5><b>{{ ucfirst($inquiry->name) }}'s</b> Enquiry for <kbd>{{ $inquiry->for }}</kbd></h5>
    <div class="lead">

    </div>

    <div class="container mt-4">

        <table class="table table-striped">
            <thead>
                <th scope="col" width="20%">#</th>
                <th scope="col" width="1%">Value</th>
            </thead>
            <tr>
                <td>Name</td>
                <td>{{ $inquiry->name }}</td>
            </tr>
            <tr>
                <td>Email</td>
                <td>{{ $inquiry->email }}</td>
            </tr>
            <tr>
                <td>Phone</td>
                <td>{{ $inquiry->phone }}</td>
            </tr>
            @if($inquiry->gender)
            <tr>
                <td>Gender</td>
                <td>{{ $inquiry->gender }}</td>
            </tr>
            @endif
            
            @if($inquiry->country)
            <tr>
                <td>Country</td>
                <td>{{ $inquiry->country }}</td>
            </tr>
            @endif

            <tr>
                <td>For</td>
                <td>{{ $inquiry->for }}</td>
            </tr>
            @if($inquiry->frequency)
            <tr>
                <td>Frequency</td>
                <td>{{ $inquiry->frequency }}</td>
            </tr>
            @endif
 
            @if($inquiry->date_of_birth)
            <tr>
                <td>@if($inquiry->for == "Vastu") House build on @else Date Of Birth @endif</td>
                <td>{{ $inquiry->date_of_birth }}</td>
            </tr>
            @endif

            @if($inquiry->place_of_birth)
            <tr>
                <td>@if($inquiry->for == "Vastu") House Situated At @else Place of Birth @endif</td>
                <td>{{ $inquiry->place_of_birth }}</td>
            </tr>
            @endif

            <tr>
                <td>Payment Status</td>
                <td><kbd class="@if($inquiry->payment_status == 'SUCCESS') bg-success @else bg-danger @endif">{{ $inquiry->payment_status }}</kbd></td>
            </tr>
            <tr>
                <td>Paid</td>
                <td>{{ $inquiry->amount }}</td>
            </tr>
            @if($inquiry->message)
            <tr>
                <td>Message</td>
                <td>{{ $inquiry->message }}</td>
            </tr>
            @endif


        </table>
    </div>

</div>
<div class="mt-4">
    <a href="{{ route('enquiry.index') }}" class="btn btn-default">Back</a>
</div>
@endsection