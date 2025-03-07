<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title">Order Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
        <div class="rounded">
            <div class="mt-4">
                <div>
                    ID: #{{ $order->id }}
                </div>
                <div>
                    Name: {{ $order->name }}
                </div>
                <div>
                    Email: {{ $order->email }}
                </div>
                <div>
                    Mobile: {{ $order->mobile }}
                </div>
            </div>
        </div>
        <div class="mt-4">
            <h4>Bookings</h4>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th width="1%">#</th>
                        <th>Item</th>
                        <th width="1%">Quantity</th>
                        <th width="1%">Persons</th>
                        <th class="text-end" width="1%">Rate</th>
                        <th class="text-end" width="1%">Price</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                    $total_persons = 0
                    @endphp
                    @foreach($order_details as $key=>$order_detail)
                    @php
                    $total_persons += $order_detail->retreat_booking_persons * $order_detail->quantity
                    @endphp
                    <tr>
                        <td>{{$key+1}}</td>
                        <td>{{$order_detail->retreat_booking_name}}</td>
                        <td>{{$order_detail->quantity}}</td>
                        <td>{{$order_detail->retreat_booking_persons * $order_detail->quantity}}</td>
                        <td class="text-end">₹{{$order_detail->price}}</td>
                        <td class="text-end">₹{{$order_detail->price}}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3">Total</th>
                        <th>
                            {{$total_persons}}
                        </th>
                        <th></th>
                        <th class="text-end">
                            ₹{{$order->total_price}}
                        </th>
                    </tr>

                </tfoot>

            </table>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    </div>
</div>