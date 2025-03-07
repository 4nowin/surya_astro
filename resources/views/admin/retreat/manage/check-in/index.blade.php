@extends('admin.layouts.admin')
@section('subnav')
    <!-- @include('admin.retreat.partials.subnav') -->
    <div class="bg-white border-bottom" style="background-color: #eee;">
        <div class="container-lg">
            <form onsubmit="openCheckInDetails(retreat)" id="search-form" class="d-flex align-items-center">
                <div class="fs-5">
                    ID
                </div>
                <input type="text" class="form-control form-control-lg border-0 bg-transparent flex-grow-1"
                    placeholder="Please enter order id" name="id" value="{{ app('request')->input('id') }}" />
                <div class="vr"></div>
                <select class="form-select  border-0  bg-transparent" name="date" style="width: 150px">
                    @foreach ($dates as $date)
                        <option>{{ $date }}</option>
                    @endforeach
                </select>
                <div class="vr"></div>
                <button class="btn border-0" onclick="startScan();" type="button"><i class="fa-solid fa-qrcode"></i></button>
            </form>
        </div>
    </div>
@endsection
@section('content')
    <div class="overflow-auto mt-4" id="details-container" style="min-height: 400px; max-width: 600px; margin:auto">

    </div>
    <script>
        function openCheckInDetails(retreat) {
            event.preventDefault();
            const order_id = (new FormData(retreat.target)).get("id");
            const date = (new FormData(retreat.target)).get("date");
            jQuery.ajax({
                url: "{{ url('/admin/retreat/' . $retreat->id . '/check-in/details') }}",
                method: 'post',
                data: {
                    order_id: order_id,
                    date: date
                },
                success: function(result) {
                    console.log(result);
                    $("#details-container").html(result.html);
                },
                complete: (result) => {

                },
                error: (error) => {
                    $("#details-container").html("");
                }
            });
        }

        function checkIn(order_id) {
            if (!confirm("Are you sure?")) return false;
            jQuery.ajax({
                url: "{{ url('/admin/retreat/' . $retreat->id . '/check-in') }}",
                method: 'post',
                data: {
                    order_id: order_id
                },
                success: function(result) {
                    openCheckInDetails({
                        target: document.getElementById("search-form"),
                        preventDefault: () => {}
                    })
                }
            });
        }
    </script>



    <div class="modal fade" id="scanner-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Scanner</h5>
                </div>
                <div class="modal-body">
                    <div id="reader" style="width: 265px"></div>
                    <style>
                        #reader{
                            margin: auto
                        }
                        #reader video{
                            width: 265px !important;
                            height: 265px !important;
                            object-fit: cover !important;
                            object-position: center !important;
                        }
                        #reader{
                            width: 265px !important;
                            height: 265px !important;
                        }
                        </style>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="stopScan()">CANCEL</button>
                </div>
            </div>
        </div>
    </div>


    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        $(function() {
            var scanner = null;
            const scanner_modal_container = document.getElementById('scanner-modal');
            const scanner_modal = new bootstrap.Modal(scanner_modal_container, {
                backdrop: 'static',
                keyboard: false,
            });
            window.startScan = async () => {
                scanner_modal.show();
                scanner = scanner ?? new Html5Qrcode("reader");;
                scanner.start({ facingMode: "environment" }, {
                    fps: 144,
                    qrbox: {
                        width: 200,
                        height: 200
                    }
                }, (decodedText, decodedResult) => {
                    // handle the scanned code as you like, for example:
                    //console.log(`Code matched = ${decodedText}`, decodedResult);
                    document.querySelector("#search-form input[name=id]").value = decodedText;
                    openCheckInDetails({
                        target: document.querySelector("#search-form"),
                        preventDefault: () => {}
                    });
                    stopScan();
                }, () => {})
            }

            window.stopScan = ()=> {
                if (scanner) scanner.stop();
                scanner_modal.hide();
            }
        });
    </script>
@endsection
