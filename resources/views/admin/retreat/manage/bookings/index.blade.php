@extends('admin.layouts.admin')
@section('subnav')
    @include('admin.retreat.partials.subnav')
@endsection
@section('content')
    <div>
        <div>
            <div class="row">
                <div class="col">
                    <h4>PASSES</h4>
                </div>
                <div class="col-auto">
                    <button type="button" onclick="openForm('')" class="btn btn-primary">New Passes</button>
                </div>
            </div>

            <div class="mt-2">
                @include('admin.layouts.partials.messages')
            </div>


            <div class="row">
                @foreach ($retreat_bookings as $key => $retreat_booking)
                    <div class="col-md-4 mb-4">
                        <div class="card position-relative overflow-hidden" style="cursor: pointer" onclick="openForm('{{ $retreat_booking->id }}')">
                            <div class="card-header bg-primary text-light d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <i class="fas fa-booking"></i> {{ $retreat_booking->name }}<br />
                                    <small style="font-size: 12px">
                                        {{ \Carbon\Carbon::parse($retreat_booking->start_datetime)->format('d/m/Y h:i A') }} 
                                        to
                                        {{ \Carbon\Carbon::parse($retreat_booking->end_datetime)->format('d/m/Y h:i A') }}
                                    </small>
                                </div>
                                <span>
                                    @money($retreat_booking->price)
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col text-center">
                                        <span class="fs-5">{{ $retreat_booking->total_sale_count }}</span><br />
                                        <span class="text-primary">Passes Sold</span>
                                    </div>

                                    {{-- <div class="col text-center">
                                        <span class="fs-5">
                                            @money($retreat_booking->total_sale_amount)
                                        </span><br />
                                        <span class="text-primary">Revenue</span>
                                    </div> --}}
                                </div>
                            </div>

                            <div class="position-absolute bottom-0 right-0 end-0 bg-{{$status_colors[$retreat_booking->status]}} px-2 text-light" style="border-top-left-radius: 8px">
                                <small>{{ $retreat_booking->status }}</small>
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>
        </div>

        

        <script>
        let formModal = null;

        function openForm(retreat_booking_id) {
            jQuery.ajax({
                url: "{{ url('/admin/retreat/' . $retreat->id . '/bookings/form') }}",
                method: 'post',
                data: {
                    retreat_booking_id: retreat_booking_id
                },
                success: function(result) {
                    $("#form-modal .modal-dialog").html(result.html);
                    formModal = new bootstrap.Modal(document.getElementById('form-modal'), {});
                    formModal.show();
                }
            });
        }

        $('.booking-form').click(function(retreat){ 
            retreat.preventDefault();
            console.log('yes here');
            // jQuery.ajax({
            //     url: "{{ url('/admin/retreat/' . $retreat->id . '/bookings') }}",
            //     method: 'post',
            //     cache: false,
            //     contentType: false,
            //     processData: false,
            //     data: new FormData(retreat.target),
            //     success: function(result) {
            //         alert(result.message);
            //         // window.location.reload();
            //         formModal.hide();
            //     }
            // });
        });
    </script>
    </div>
@endsection
