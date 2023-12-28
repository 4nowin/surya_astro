<div class="card card-sm">
    <div class="card-header">
        <h5>Order Details</h5>
    </div>
    <div class="card-body">
        <div class="rounded">
            <div>
                <div class="row">
                    <div class="col">
                        <div>
                            ID: #{{ $order->id }}
                        </div>
                        <div>
                            Name: {{ $order->name }}
                        </div>
                        <div>
                            Checked In: <span
                                class="text-{{ $order->is_checked_in ? 'success' : 'danger' }}">{{ $order->is_checked_in ? 'Yes' : 'No' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <h4>Bookings</h4>
    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th width="1%">#</th>
                <th>Item</th>
                <th width="1%">Quantity</th>
            </tr>
        </thead>
        <tbody>
            @php
                $total_persons = 0;
            @endphp
            @foreach ($order_details as $key => $order_detail)
                @php
                    $total_persons += $order_detail->retreat_booking_persons * $order_detail->quantity;
                @endphp
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td>{{ $order_detail->retreat_booking_name }}</td>
                    <td>{{ $order_detail->quantity }}</td>
                </tr>
            @endforeach
        </tbody>

    </table>
</div>

<div class="text-center">
    @if ($order->status == 'SUCCESS' && !$order->is_checked_in)
        <button type="button" class="btn btn-success" onclick="checkIn('{{ $order->id }}')">Check In</button>
    @endif
</div>
