@extends('admin.layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-user-plus"></i> Create New User
                    </h4>
                </div>
                
                <div class="card-body">
                    @if (count($errors) > 0)
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong><i class="fas fa-exclamation-triangle"></i> Whoops!</strong> There were some problems with your input.
                            <ul class="mt-2 mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    {!! Form::open(['route' => 'admins.store', 'method' => 'POST', 'class' => 'needs-validation', 'novalidate']) !!}
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label fw-bold">
                                    <i class="fas fa-user"></i> Name
                                </label>
                                {!! Form::text('name', null, [
                                    'placeholder' => 'Enter full name',
                                    'class' => 'form-control form-control-lg' . ($errors->has('name') ? ' is-invalid' : ''),
                                    'id' => 'name',
                                    'required'
                                ]) !!}
                                @if ($errors->has('name'))
                                    <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label fw-bold">
                                    <i class="fas fa-envelope"></i> Email
                                </label>
                                {!! Form::email('email', null, [
                                    'placeholder' => 'Enter email address',
                                    'class' => 'form-control form-control-lg' . ($errors->has('email') ? ' is-invalid' : ''),
                                    'id' => 'email',
                                    'required'
                                ]) !!}
                                @if ($errors->has('email'))
                                    <div class="invalid-feedback">{{ $errors->first('email') }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label fw-bold">
                                    <i class="fas fa-lock"></i> Password
                                </label>
                                {!! Form::password('password', [
                                    'placeholder' => 'Enter password',
                                    'class' => 'form-control form-control-lg' . ($errors->has('password') ? ' is-invalid' : ''),
                                    'id' => 'password',
                                    'required'
                                ]) !!}
                                <div class="form-text">Password must be at least 8 characters long.</div>
                                @if ($errors->has('password'))
                                    <div class="invalid-feedback">{{ $errors->first('password') }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm-password" class="form-label fw-bold">
                                    <i class="fas fa-lock"></i> Confirm Password
                                </label>
                                {!! Form::password('confirm-password', [
                                    'placeholder' => 'Confirm your password',
                                    'class' => 'form-control form-control-lg',
                                    'id' => 'confirm-password',
                                    'required'
                                ]) !!}
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="mb-3">
                                <label for="roles" class="form-label fw-bold">
                                    <i class="fas fa-user-tag"></i> Role(s)
                                </label>
                                {!! Form::select('roles[]', $roles, [], [
                                    'class' => 'form-select form-select-lg',
                                    'id' => 'roles',
                                    'multiple' => 'multiple',
                                    'style' => 'height: 150px;'
                                ]) !!}
                                <div class="form-text">Hold Ctrl/Cmd to select multiple roles.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 text-center mt-4">
                            <a href="{{ route('admins.index') }}" class="btn btn-secondary btn-lg me-2">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-paper-plane"></i> Create User
                            </button>
                        </div>
                    </div>

                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('styles')
<style>
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        border: 1px solid rgba(0, 0, 0, 0.125);
    }
    
    .card-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    }
    
    .form-control-lg {
        padding: 0.75rem 1rem;
    }
    
    .btn-lg {
        padding: 0.75rem 1.5rem;
        font-size: 1.1rem;
    }
</style>
@endsection