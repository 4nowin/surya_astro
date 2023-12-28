@extends('admin.layouts.admin')

@section('content')
<div class="bg-light p-4 rounded">
    <h1>{{ ucfirst($inquiry->name) }}'s Enquiry</h1>
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
            <tr>
                <td>Gender</td>
                <td>{{ $inquiry->gender }}</td>
            </tr>
            <tr>
                <td>Country</td>
                <td>{{ $inquiry->country }}</td>
            </tr>
            <tr>
                <td>For</td>
                <td>{{ $inquiry->for }}</td>
            </tr>
            <tr>
                <td>Date Of Birth</td>
                <td>{{ $inquiry->date_of_birth }}</td>
            </tr>
            <tr>
                <td>Place of Birth</td>
                <td>{{ $inquiry->place_of_birth }}</td>
            </tr>
            <tr>
                <td>Status</td>
                <td>{{ $inquiry->status }}</td>
            </tr>
            <tr>
                <td>Message</td>
                <td>{{ $inquiry->message }}</td>
            </tr>


        </table>
    </div>

</div>
<div class="mt-4">
    <a href="{{ route('enquiry.index') }}" class="btn btn-default">Back</a>
</div>
@endsection