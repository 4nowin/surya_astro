<!-- <form onsubmit="handleOnSubmitBookingForm(retreat)" enctype="multipart/form-data" method="post" action="/admin/retreat"> -->
<form enctype="multipart/form-data" method="post" action="/admin/retreat/{{ $retreat->id }}/bookings">
    @csrf
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Booking Passes Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" value="{{ $retreat->id }}" name="retreat_id">
            <input type="hidden" value="{{ $retreat_booking->id }}" name="retreat_booking_id">
            <div class="form-group">
                <label for="name-input">Name</label>
                <input type="text" class="form-control" id="name-input" placeholder="Enter Name" name="name"
                    value="{{ $retreat_booking->name }}">
            </div>
            <div class="row mt-3">
                <div class="col">
                    <div class="form-group">
                        <label for="persons-input">Persons</label>
                        <div class="input-group">

                            <span class="input-group-text" id="basic-price"><i class="fas fa-user text-grey"></i></span>

                            <input type="number" class="form-control" id="persons-input" placeholder="Enter Persons"
                                name="persons" value="{{ $retreat_booking->persons }}">
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="form-group">
                        <label for="price-input">Price</label>
                        <div class="input-group">
                            <span class="input-group-text" id="basic-price">₹</span>

                            <input type="number" class="form-control" id="price-input" placeholder="Enter Price"
                                name="price" value="{{ $retreat_booking->price }}">
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="form-group">
                        <label for="price-input">Status</label>
                        <div class="input-group">
                            <span class="input-group-text" id="basic-price">₹</span>

                            <select class="form-select" name="status" id="status-input">
                                <option {{ $retreat_booking->status === 'CREATED' ? 'selected' : '' }}>CREATED</option>
                                <option {{ $retreat_booking->status === 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                                <option {{ $retreat_booking->status === 'SOLD' ? 'selected' : '' }}>SOLD</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-6">
                    <div class="form-group">
                        <label for="start_datetime-input">Start At</label>
                        <div class="input-group">

                            <span class="input-group-text" id="basic-start_datetime"><i
                                    class="fas fa-calendar text-grey"></i></span>

                            <input type="datetime" class="form-control" id="start_datetime-input"
                                placeholder="Enter Start Datetime" name="start_datetime"
                                value="{{ $retreat_booking->start_datetime }}">
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label for="end_datetime-input">End At</label>
                        <div class="input-group">
                            <span class="input-group-text" id="basic-end_datetime"><i
                                    class="fas fa-calendar text-grey"></i></span>

                            <input type="datetime" class="form-control" id="end_datetime-input"
                                placeholder="Enter End Datetime" name="end_datetime"
                                value="{{ $retreat_booking->end_datetime }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group mt-3">
                <label for="description-input">Description</label>
                <textarea type="text" class="form-control" id="description-input" placeholder="Enter Description" name="description">{{ $retreat_booking->description }}</textarea>
            </div>



        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary" >Save Booking</button>

        </div>
    </div>
</form>

<script>

        //     let formModal = null;

        //     function openForm(retreat_booking_id) {
        //     jQuery.ajax({
        //         url: "{{ url('/admin/retreat/' . $retreat->id . '/bookings/form') }}",
        //         method: 'post',
        //         data: {
        //             retreat_booking_id: retreat_booking_id
        //         },
        //         success: function(result) {
        //             $("#form-modal .modal-dialog").html(result.html);
        //             formModal = new bootstrap.Modal(document.getElementById('form-modal'), {});
        //             formModal.show();
        //         }
        //     });
        // }

            function handleOnSubmitBookingForm(event) {
                event.preventDefault();
                jQuery.ajax({
                url: "{{ url('/admin/retreat/' . $retreat->id . '/bookings') }}",
                    method: 'post',
                    cache: false,
                    contentType: false,
                    processData: false,
                    data: new FormData(event.target),
                    success: function(result) {
                        alert(result.message);
                        window.location.reload();
                        formModal.hide();
                    }
                });
            }

        $('#bookingFormSubmit').click(function(e){ 
            e.preventDefault();
           
            var status = document.getElementById("status-input");
            let data = {
                name: $('#name-input').val(),
                persons: $('#persons-input').val(),
                price: $('#price-input').val(),
                status: status.value,
                start_datetime: $('#start_datetime-input').val(),
                end_datetime: $('#end_datetime-input').val(),
                description: $('#description-input').val(),
            }
            
            console.log('yes here', data);
            // jQuery.ajax({
            //     url: "{{ url('/admin/retreat/' . $retreat->id . '/book') }}",
            //     method: 'post',
            //     cache: false,
            //     contentType: false,
            //     processData: false,
            //     data: data,
            //     success: function(result) {
            //         alert(result.message);
            //         window.location.reload();
            //         formModal.hide();
            //     }
            // });

            $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                    }
                });
                $.ajax({
                    
                url: "{{ url('/admin/retreat/' . $retreat->id . '/book') }}",
                    method: 'post',
                    data: data,
                    success: function(result){
                        if(result.errors)
                        {
                            $('.alert-danger').html('');

                            $.each(result.errors, function(key, value){
                                $('.alert-danger').show();
                                $('.alert-danger').append('<li>'+value+'</li>');
                            });
                        }
                        else
                        {
                            $('.alert-danger').hide();
                            // $('#exampleModal').modal('hide');
                        }
                    }
                });
        });
    </script>