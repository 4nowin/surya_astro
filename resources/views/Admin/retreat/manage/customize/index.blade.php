@extends('admin.layouts.admin')
@section('subnav')
@include('admin.retreat.partials.subnav')
@endsection
@section('content')
<div>
    <h4>Customize retreat</h4>

    <div class="card mt-4" id="general">
        <div class="card-header">General</div>
        <div class="card-body">
            <form enctype="multipart/form-data" method="post">
                @csrf

                <x-image-chooser class="border border-grey mb-3" height="200px" width="100%" :value="$retreat->cover_image" name="cover_image" />

                <div class="mb-3">
                    <label for="title">Title</label>

                    <div>
                        <input id="title" type="text" class="form-control" placeholder='Enter Name here' name="title" value="{{ $retreat ? $retreat->title : '' }}" required="">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="retreat_type">Retreat Type</label>

                            <div>
                                <input id="retreat_type" type="text" class="form-control" placeholder='Enter retreat Type here' name="retreat_type" value="{{ $retreat ? $retreat->retreat_type : '' }}" required="">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="mb-3">
                            <label for="price">Price</label>

                            <div>
                                <input id="price" type="number" class="form-control" placeholder='Enter retreat Type here' name="price" value="{{ $retreat ? $retreat->price : '' }}" required="">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="mb-3">
                            <label for="entry_type">Level</label>
                            <div>
                                <select id="entry_type" class="form-select" name="entry_type[]" required="" multiple>
                                    <option></option>
                                    <option {{ $retreat && $retreat->entry_type == 'Beginner' ? 'selected' : '' }}>Beginner
                                    </option>
                                    <option {{ $retreat && $retreat->entry_type == 'Intermediate' ? 'selected' : '' }}>Intermediate
                                    </option>
                                    <option {{ $retreat && $retreat->entry_type == 'Advance' ? 'selected' : '' }}>Advance
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="mb-3">
                            <label for="style">Style</label>

                            <div>
                                <select id="style" type="text" class="form-select" name="style" required="">
                                    <option value="1">Yin</option>
                                    <option value="2">Ashtanga</option>
                                    <option value="2">Hatha</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="language">Language</label>

                            <div>
                                <input id="language" type="text" class="form-control" placeholder='Enter Language here' name="language" value="{{ $retreat ? $retreat->language : '' }}" required="">
                            </div>
                        </div>
                    </div>


                    <div class="mb-3">
                        <label for="overview">Overview</label>

                        <div>
                            <x-rich-text-editor name="overview" required="required" placeholder="Enter retreat overview" required>{!! $retreat ? html_entity_decode($retreat->overview) : '' !!}</x-rich-text-editor>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="mb-3">
                            <label for="dates">Dates</label>
                            <div>
                                <input id="dates" type="text" class="form-control dates" placeholder='Enter Start Date here' name="dates" value="{{ $retreat ? $retreat->dates : '' }}" required="">
                            </div>
                        </div>
                    </div>

                    <!-- <div class="col-md-3">
                        <div class="mb-3">
                            <label for="start_date">Start Date</label>

                            <div>
                                <input id="start_date" type="date" class="form-control" placeholder='Enter Start Date here' name="start_date" value="{{ $retreat ? $retreat->start_date : '' }}" required="">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="end_date">End Date</label>

                            <div>
                                <input id="end_date" type="date" class="form-control" placeholder='Enter End Date here' name="end_date" value="{{ $retreat ? $retreat->end_date : '' }}" required="">
                            </div>
                        </div>
                    </div> -->


                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="teacher_id">Teachers</label>
                            <x-selectize :options="$teachers" :multiple="true" name="teacher_id[]" :selected="$retreat->teacher_id"></x-selectize>
                        </div>
                    </div>


                    <div class="col-md-2">
                        <div class="mb-3">
                            <label for="min_age">Minimum Age</label>

                            <div>
                                <input id="min_age" type="number" class="form-control" placeholder='Enter minimum age here' name="min_age" value="{{ $retreat ? $retreat->min_age : '' }}" required="">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="mb-3">
                            <label for="occurrence">Occurrence</label>

                            <div>
                                <select id="occurrence" type="text" class="form-select" name="occurrence" required="">
                                    <option value="1">Once</option>
                                    <option value="2">Multiple</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="date_info">Date Info</label>
                            <div>
                                <x-rich-text-editor name="date_info" required="required" placeholder="Enter retreat date info" required>{!! $retreat ? html_entity_decode($retreat->date_info) : '' !!}
                                </x-rich-text-editor>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="duration_info">Duration Info</label>
                            <div>
                                <x-rich-text-editor name="duration_info" required="required" placeholder="Enter retreat duration info" required>{!! $retreat ? html_entity_decode($retreat->duration_info) : '' !!}
                                </x-rich-text-editor>
                            </div>
                        </div>
                    </div>


                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="excerpt">Excerpt</label>

                            <div>
                                <x-rich-text-editor name="excerpt" required="required" placeholder="Enter retreat excerpt" required>{!! $retreat ? html_entity_decode($retreat->excerpt) : '' !!}
                                </x-rich-text-editor>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="course_info">course_info</label>

                            <div>
                                <x-rich-text-editor name="course_info" required="required" placeholder="Enter retreat course_info" required>{!! $retreat ? html_entity_decode($retreat->course_info) : '' !!}
                                </x-rich-text-editor>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="venue">Venue</label>
                            <div>
                                <x-selectize :options="$venues" name="venue" :selected="$retreat ? $retreat->venue : ''"></x-selectize>
                            </div>
                        </div>
                    </div>


                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="location">Location</label>
                            <x-selectize :options="$locations" :multiple="false" name="location" :selected="$retreat->location">
                            </x-selectize>
                        </div>
                    </div>


                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="city">City</label>

                            <div>
                                <input id="city" type="text" class="form-control" placeholder='Enter City here' name="city" value="{{ $retreat ? $retreat->city : '' }}" required="">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="address">Address</label>

                            <div>
                                <input id="address" type="text" class="form-control" placeholder='Enter Address here' name="address" value="{{ $retreat ? $retreat->address : '' }}" required="">
                            </div>
                        </div>
                    </div>




                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="highlights">highlights</label>

                        <div>
                            <x-rich-text-editor name="highlights" required="required" placeholder="Enter retreat highlights" required>{!! $retreat ? html_entity_decode($retreat->highlights) : '' !!}
                            </x-rich-text-editor>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="requirements">requirements</label>

                        <div>
                            <x-rich-text-editor name="requirements" required="required" placeholder="Enter retreat requirements" required>{!! $retreat ? html_entity_decode($retreat->requirements) : '' !!}
                            </x-rich-text-editor>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="how">how retreat works</label>

                    <div>
                        <x-rich-text-editor name="how" required="required" placeholder="Enter how retreat works" required>{!! $retreat ? html_entity_decode($retreat->how) : '' !!}
                        </x-rich-text-editor>
                    </div>
                </div>


                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="inclusion">inclusion</label>

                        <div>
                            <x-rich-text-editor name="inclusion" required="required" placeholder="Enter retreat inclusion" required>{!! $retreat ? html_entity_decode($retreat->inclusion) : '' !!}
                            </x-rich-text-editor>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="exclusion">exclusion</label>

                        <div>
                            <x-rich-text-editor name="exclusion" required="required" placeholder="Enter retreat exclusion" required>{!! $retreat ? html_entity_decode($retreat->exclusion) : '' !!}
                            </x-rich-text-editor>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                        <div class="mb-3">
                            <label for="video_link">Video Link</label>

                            <div>
                                <input id="video_link" type="text" class="form-control" placeholder='Enter Video Link here' name="video_link" value="{{ $retreat ? $retreat->video_link : '' }}" required="">
                            </div>
                        </div>
                    </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="accommodation_id" class="form-label">Accommodation</label>
                        <x-selectize :options="$accommodation" :multiple="true" name="accommodation_id[]" :selected="$retreat->accommodation_id"></x-selectize>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="food_id" class="form-label">Food</label>
                        <div>
                            <x-selectize :options="$food" :multiple="true" name="food_id[]" :selected="$retreat->food_id"></x-selectize>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="schedule_id" class="form-label">Schedule</label>
                        <div>
                            <x-selectize :options="$schedule" :multiple="true" name="schedule_id[]" :selected="$retreat->schedule_id"></x-selectize>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="organizer_id" class="form-label">Organizer</label>
                        <x-selectize :options="$organizer" :multiple="true" name="organizer_id[]" :selected="$retreat->organizer_id"></x-selectize>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="cancellation_id" class="form-label">Cancellation</label>
                        <x-selectize :options="$cancellation" :multiple="true" name="cancellation_id[]" :selected="$retreat->cancellation_id"></x-selectize>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="retreat_location">Retreat Location</label>
                        <div>
                            <input id="retreat_location" type="text" class="form-control" placeholder='Enter Retreat Location' name="retreat_location" value="{{ $retreat ? $retreat->retreat_location : '' }}" required="">
                        </div>
                    </div>
                </div>


                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="duration">Duration (in Days)</label>
                        <div>
                            <input id="duration" type="number" class="form-control" placeholder='Enter Days' name="duration" value="{{ $retreat ? $retreat->duration : '' }}" required="">
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="location_info">Location Info</label>

                        <div>
                            <x-rich-text-editor name="location_info" required="required" placeholder="Enter location info" required>{!! $retreat ? html_entity_decode($retreat->location_info) : '' !!}
                            </x-rich-text-editor>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="location_map">Location Map</label>

                        <div>
                            <x-rich-text-editor name="location_map" required="required" placeholder="Enter Location Map" required>{!! $retreat ? html_entity_decode($retreat->location_map) : '' !!}
                            </x-rich-text-editor>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="terms">Terms & Conditions</label>

                    <div>
                        <x-rich-text-editor name="terms" required="required" placeholder="Enter retreat terms and conditions" required>{!! $retreat ? html_entity_decode($retreat->terms) : '' !!}
                        </x-rich-text-editor>
                    </div>
                </div>

                </div>


                <button class="btn btn-primary">Save retreat</button>
            </form>
        </div>
    </div>
    <div class="card mt-4" id="album">
        <div class="card-header">Album</div>
        <div class="card-body">
            <div class="row">
                @foreach ($retreat_album_images as $retreat_album_image)
                <div class="col-md-2 col-sm-3 col-6">
                    <div class="position-relative border" style="width:100%; padding-top: 100%; background-size:cover; background-position:center; background-image:url('{{ url('/storage/uploads/' . $retreat_album_image->image) }}')">
                        <form class="position-absolute" style="top: 0; right:0" action="{{ url('/admin/retreat/' . $retreat->id . '/customize/album-images') }}" method="post" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="_method" value="delete" />
                            <input type="hidden" name="retreat_album_image_id" value="{{ $retreat_album_image->id }}" />
                            <button class="btn btn-sm btn-danger" type="submit"><i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            <form action="{{ url('/admin/retreat/' . $retreat->id . '/customize/album-images') }}" id="album_image_form" class="mt-4" method="post" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-auto">
                        <input class="form-control form-control-sm" type="file" name="image" />
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-sm btn-primary" type="submit">Upload</button>
                    </div>
                </div>

            </form>
        </div>
    </div>


</div>

<script>
    $(document).ready(function() {
        $('#dates').datepicker({
            multidate: true,
            format: 'dd-mm-yyyy'
        });
    });
</script>
@endsection
