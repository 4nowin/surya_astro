@extends('admin.layouts.admin')

@section('content')

<h4>New Schedule</h4>
<p class="text-muted">Add new post.</p>

<div class="mt-4">

    <form method="POST" action="{{ route('schedule.update', $schedule->id) }}" enctype="multipart/form-data">
        @csrf
        @method('patch')

        <div class="mb-3">
            <label for="name" class="form-label">Title</label>
            <input type="text" class="form-control" name="name" placeholder="Name" value="{{$schedule->name}}" required>

            @if ($errors->has('name'))
            <span class="text-danger text-left">{{ $errors->first('name') }}</span>
            @endif
        </div>

        <div class="mb-3">
                <label for="name" class="form-label">Schedule</label>
                <div class="col-lg-12">
                @foreach($schedule_array as $sch)
                    <div id="row" class="row m-3">
                        <div class="col col-4">
                            <label for="time" class="form-label">Time</label>
                            <div class="row">
                                <div class="col col-6">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">Start</span>
                                        </div>
                                        <input type="time" class="form-control" id="start_datetime-input"
                                        placeholder="Enter Start Datetime" name="start_time[]" value="{{$sch['start_time']}}">
                                    </div>
                                </div>
                                <div class="col col-6">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">End</span>
                                        </div>
                                        <input type="time" class="form-control" id="end_datetime-input"
                                        placeholder="Enter End Datetime" name="end_time[]" value="{{$sch['end_time']}}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col col-4">
                            <label for="schedule" class="form-label">Schedule</label>
                            <input type="text" class="form-control" placeholder="schedule" name="schedule[]" value="{{$sch['schedule']}}">
                        </div>
                        <div class="col">
                            <div class="input-group-append">
                                <button class="btn btn-danger mt-4"
                                        id="DeleteRow"
                                        type="button">
                                    <i class="fa fa-trash"></i>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
 
                    <div id="newinput"></div>
                    <button id="rowAdder" type="button" class="btn btn-dark">
                        <span class="fa fa-plus">
                        </span> Add Schedule
                    </button>
                </div>
            </div>

        <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <input value="{{$schedule->notes}}" type="text" class="form-control" name="notes" placeholder="notes" id="notes" required>

            @if ($errors->has('notes'))
            <span class="text-danger text-left">{{ $errors->first('notes') }}</span>
            @endif
        </div>

        <button type="submit" class="btn btn-primary">Save Schedule</button>
        <a href="{{ route('schedule.index') }}" class="btn btn-default">Back</a>
    </form>
</div>

<script type="text/javascript">
        $("#rowAdder").click(function () {
            newRowAdd = `<div id="row" class="row m-3">
                        <div class="col col-4">
                            <div class="row">
                                <div class="col col-6">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">Start</span>
                                        </div>
                                        <input type="time" class="form-control" id="start_datetime-input"
                                        placeholder="Enter Start Datetime" name="start_time[]">
                                    </div>
                                </div>
                                <div class="col col-6">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">End</span>
                                        </div>
                                        <input type="time" class="form-control" id="end_datetime-input"
                                        placeholder="Enter End Datetime" name="end_time[]">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col col-4">
                            <input type="text" class="form-control" placeholder="schedule" name="schedule[]">
                        </div>
                        <div class="col">
                            <div class="input-group-append">
                                <button class="btn btn-danger"
                                        id="DeleteRow"
                                        type="button">
                                    <i class="fa fa-trash"></i>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>`;
 
            $('#newinput').append(newRowAdd);
        });
        $("body").on("click", "#DeleteRow", function () {
            $(this).parents("#row").remove();
        })
    </script>
@endsection