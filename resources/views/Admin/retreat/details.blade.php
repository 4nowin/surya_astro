@extends('admin.layouts.admin')

@section('content')
<div class="">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <form enctype="multipart/form-data" method="post">
                @csrf
                <!-- {{ csrf_field() }} -->
                <div class="card">
                    <div class="card-header">Retreat Details</div>

                    <div class="card-body">

                        <div class="row mb-3">
                            <label for="name" class="col-md-4 col-form-label text-md-end">Name</label>

                            <div class="col-md-6">
                                <input id="name" type="text" class="form-control" placeholder='Enter Name here' name="name" value="{{$retreat ? $retreat->name:''}}" required="">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="entry_type" class="col-md-4 col-form-label text-md-end">Entry Type</label>

                            <div class="col-md-6">
                                <select id="entry_type" class="form-select" name="entry_type" required="">
                                    <option></option>
                                    <option {{$retreat && $retreat->entry_type =='Normal'?"selected":"" }}>Normal</option>

                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="venue" class="col-md-4 col-form-label text-md-end">Venue</label>

                            <div class="col-md-6">
                                <input id="venue" type="text" class="form-control" placeholder='Enter Venue here' name="venue" value="{{$retreat ? $retreat->venue:''}}" required="">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="city" class="col-md-4 col-form-label text-md-end">City</label>

                            <div class="col-md-6">
                                <input id="city" type="text" class="form-control" placeholder='Enter City here' name="city" value="{{$retreat ? $retreat->city :''}}" required="">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="address" class="col-md-4 col-form-label text-md-end">Address</label>

                            <div class="col-md-6">
                                <input id="address" type="text" class="form-control" placeholder='Enter Address here' name="address" value="{{$retreat ? $retreat->address:''}}" required="">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="start_date" class="col-md-4 col-form-label text-md-end">Start Date</label>

                            <div class="col-md-6">
                                <input id="start_date" type="date" class="form-control" placeholder='Enter Start Date here' name="start_date" value="{{$retreat ? $retreat->start_date:''}}" required="">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="end_date" class="col-md-4 col-form-label text-md-end">End Date</label>

                            <div class="col-md-6">
                                <input id="end_date" type="date" class="form-control" placeholder='Enter End Date here' name="end_date" value="{{$retreat ? $retreat->end_date:''}}" required="">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="occurrence" class="col-md-4 col-form-label text-md-end">Occurrence</label>

                            <div class="col-md-6">
                                <select id="occurrence" type="text" class="form-select" name="occurrence" required="">
                                    <option value="1">Once</option>
                                    <option value="2">Multiple</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="description" class="col-md-4 col-form-label text-md-end">Description</label>

                            <div class="col-md-6">
                                <textarea id="description" class="form-control" placeholder='Enter Description here' name="description" required="">{{$retreat ? $retreat->description:''}}</textarea>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="cover_image" class="col-md-4 col-form-label text-md-end">Cover Image</label>

                            <div class="col-md-6">
                                <input id="cover_image" type="file" class="form-control" placeholder='Enter Cover Image here' name="cover_image">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="video_link" class="col-md-4 col-form-label text-md-end">Video Link</label>

                            <div class="col-md-6">
                                <input id="video_link" type="text" class="form-control" placeholder='Enter Video Link here' name="video_link" value="{{$retreat ? $retreat->video_link:''}}" required="">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="retreat_type" class="col-md-4 col-form-label text-md-end">Retreat Type</label>

                            <div class="col-md-6">
                                <input id="retreat_type" type="text" class="form-control" placeholder='Enter Retreat Type here' name="retreat_type" value="{{$retreat ? $retreat->retreat_type:''}}" required="">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="teacher" class="col-md-4 col-form-label text-md-end">Teacher</label>

                            <div class="col-md-6">
                                <input id="teacher" type="text" class="form-control" placeholder='Enter Teacher here' name="teacher" value="{{$retreat ? $retreat->teacher:''}}" required="">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="abilities" class="col-md-4 col-form-label text-md-end">Abilities</label>

                            <div class="col-md-6">
                                <input id="abilities" type="text" class="form-control" placeholder='Enter Abilities here' name="abilities" value="{{$retreat ? $retreat->abilities:''}}" required="">
                            </div>
                        </div>




                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">Retreat Passes</div>

                    <div class="card-body">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th style="width: 100px;">Price</th>
                                    <th style="width: 100px;">Persons</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $count = 1;
                                @endphp
                                @if(isset($retreat_tickets) && $retreat_tickets != null)
                                @foreach($retreat_tickets as $retreat_ticket)
                                <tr>
                                    <td>
                                        {{$count}}
                                        <input type="hidden" name="retreat_tickets[{{$count}}][id]" value="{{$retreat_ticket->id}}" />
                                    </td>
                                    <th><input type="text" class="form-control form-control-sm" name="retreat_tickets[{{$count}}][name]" placeholder="Name" value="{{$retreat_ticket->name}}" /></th>
                                    <th>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text" id="basic-addon1">₹</span>
                                            <input type="number" class="form-control form-control-sm" name="retreat_tickets[{{$count}}][price]" placeholder="Price" value="{{$retreat_ticket->price}}">
                                        </div>
                                    </th>
                                    <th>
                                        <input type="number" class="form-control form-control-sm" name="retreat_tickets[{{$count}}][persons]" placeholder="Persons" value="{{$retreat_ticket->persons}}">
                                    </th>
                                    <th><input type="text" class="form-control form-control-sm" name="retreat_tickets[{{$count}}][description]" placeholder="Description" value="{{$retreat_ticket->description}}"></th>
                                </tr>
                                @php
                                $count++;
                                @endphp
                                @endforeach
                                @endif
                                @for($count=$count; $count < 10; $count++) <tr>
                                    <td>
                                        {{$count}}
                                        <input type="hidden" name="retreat_tickets[{{$count}}][id]" value="0" />
                                    </td>
                                    <th><input type="text" class="form-control form-control-sm" name="retreat_tickets[{{$count}}][name]" placeholder="Name" /></th>
                                    <th>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text" id="basic-addon1">₹</span>
                                            <input type="number" class="form-control form-control-sm" name="retreat_tickets[{{$count}}][price]" placeholder="Price">
                                        </div>
                                    </th>
                                    <th>
                                        <input type="number" class="form-control form-control-sm" name="retreat_tickets[{{$count}}][persons]" placeholder="Persons">
                                    </th>
                                    <th><input type="text" class="form-control form-control-sm" name="retreat_tickets[{{$count}}][description]" placeholder="Description"></th>
                                    </tr>
                                    @endfor
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <button type="submit" class="btn btn-primary">
                        Save Retreat
                    </button>
                    <a type="submit" class="btn btn-danger" href="{{url('admin/retreat')}}">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection