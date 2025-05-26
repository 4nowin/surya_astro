@extends('admin.layouts.admin')

@section('content')
<div class="container">
    <h2>Send Firebase Notification</h2>

    @if(session('status'))
        <div class="alert alert-info">
            <pre>{{ session('status') }}</pre>
        </div>
    @endif

    <form method="POST" action="{{ route('fcm.send') }}">
        @csrf
        <div class="mb-3">
            <label for="token" class="form-label">FCM Token</label>
            <input type="text" name="token" id="token" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input type="text" name="title" id="title" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="body" class="form-label">Body</label>
            <textarea name="body" id="body" class="form-control" required></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Send Notification</button>
    </form>
</div>
@endsection
