<form action="/payment/razorpay/checkout" method="post" onsubmit="checkForm(event)">
    {{-- <form action="/payment/cashfree/checkout" method="post" onsubmit="checkForm(event)"> --}}
    @csrf
    <input type="hidden" name="event_id" value="{{ $event->id }}" />
    <input type="hidden" name="promoter_id" value="{{ app('request')->input('promoter') }}" />
    <div class="modal-body p-0">
        <div class="row gx-0">
            <div class="col-md">
                <div class="h-100 p-4 ">
                    <div>
                        <h4 class="mb-0">{{ $event->name }} @ {{ $venue->name }}</h4>
                        <small class="text-muted">
                            <i class="fas fa-calendar me-2"></i>
                            {{ \Carbon\Carbon::parse($event_tickets[0]->start_datetime)->format('d-M-Y | h:i A') }} Onwards
                        </small>
                    </div>
                    <div class="table-responsive-sm">
                        <table class="table table-ms mt-3">
                            @foreach ($event_tickets as $n => $event_ticket)
                                <tr data-row="ticket">
                                    <td width="50%" class="ps-0">
                                        <h6 class="mb-0 text-light">{{ $event_ticket->name }}
                                            <br/>
                                            <span class="badge bg-danger">
                                                {{ \Carbon\Carbon::parse($event_ticket->start_datetime)->format('d-m-Y') }}
                                            </span>
                                        </h6>
                                        <small class="text-muted">{{ $event_ticket->description }}</small><br />
                                        <span class="text-light"> @money($event_ticket->price)</span>
                                    </td>
                                    <td class="align-middle pe-0 pr-0" width="1%">
                                        <input type="hidden" name="items[{{ $n }}][event_ticket_id]"
                                            value="{{ $event_ticket->id }}" class="" />
                                        <input type="hidden" value="{{ $event_ticket->price }}" data-field="price" />
                                        <div class="input-group" style="width: 100px;transform: scale(0.8)">
                                            <button type="button" class="btn btn-danger btn-number btn-sm"
                                                data-field="minus">
                                                <span class="fa fa-minus"></span>
                                            </button>
                                            <input type="number" class="form-control form-control-sm bare text-center"
                                                data-field='quantity' name="items[{{ $n }}][quantity]"
                                                value="0" @if ($event_ticket->status == 'SOLD') disabled @endif />
                                            <button type="button" class=" btn btn-danger btn-number btn-sm"
                                                data-field="plus">
                                                <span class="fa fa-plus"></span>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="fs-6 align-middle pe-0 text-nowrap text-end text-light d-sm-table-cell d-none"
                                        width="1%" style="min-width: 60px" data-field="total-price">
                                        @if ($event_ticket->status == 'SOLD')
                                            <span class="badge bg-danger"> Sold Out</span>
                                        @else
                                            <span class="text-light">@money(0)</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            </div>



            <div class="col-md-auto">
                <div class="h-100 p-4">
                    <table class="table fs-6" style="min-width: 250px">
                        <tr>
                            <td class="ps-0 border-bottom-0 text-light">Total Quantity</td>
                            <td class="pe-0 border-bottom-0 text-light" id="total-quantity">0</td>
                        </tr>

                        <tr>
                            <td class="ps-0 text-light">Total Amount</td>
                            <td class="pe-0 text-light" id="total-amount">@money(0)</td>
                        </tr>

                        <tr>
                            <td class="ps-0 border-bottom-0 text-light">Grand Total</td>
                            <td class="pe-0 border-bottom-0 text-light" id="grand-total">@money(0)</td>
                        </tr>

                        <tr class="discount-field d-none">
                            <td class="ps-0 border-bottom-0 text-light">Discount Applied</td>
                            <td class="pe-0 border-bottom-0 text-light" id="discount-total">- @money(0)</td>
                        </tr>

                        <tr class="discount-field d-none">
                            <td class="ps-0 border-bottom-0 text-light">Net Total</td>
                            <td class="pe-0 border-bottom-0 text-light" id="net-total">@money(0)</td>
                        </tr>

                    </table>
                    <div>
                        <hr />
                        <!-- Discount Container -->
                        <input type="hidden" name="discount" value="0" />
                        <div id="discount-container" class="input-group mb-3 d-none">
                            <input type="text" id="coupon" name="coupon" placeholder="Discount Coupon"
                                class="form-control" />
                            <button class="btn btn-danger" type="button"> Redeem </button>
                        </div>
                        {{-- @guest
                            <div>
                                <hr />
                                <!-- Email Container -->
                                <div id="email-container">
                                    <div class="mb-3">
                                        <input type="email" id="email" name="email" placeholder="Email"
                                            class="form-control" />
                                    </div>
                                    <button class="btn btn-primary w-100" type="button">Continue</button>
                                </div>
                                <!-- login Container -->
                                <div id="login-container" style="display: none">
                                    <div class="mb-3" class="password">
                                        <input type="password" placeholder="Password" name="password"
                                            class="form-control" />
                                    </div>
                                        @if (Route::has('password.request'))
                                            <a class="btn btn-link" style="color:lightgrey" target="_blank" href="{{ route('password.request') }}">
                                                {{ __('Forgot Your Password?') }}
                                            </a>
                                        @endif
                                    <button class="btn btn-primary w-100" type="button">Login</button>
                                </div>
                                <!-- register Container -->
                                <div id="register-container" style="display: none">
                                    <div class="mb-3" class="name">
                                        <input type="text" placeholder="Full name" class="form-control"
                                            name="name" />
                                    </div>
                                    <div class="mb-3" class="mobile">
                                        <input type="number" placeholder="Phone" name="mobile" class="form-control" />
                                    </div>

                                    <div class="mb-3" class="password">
                                        <input type="password" placeholder="Password" name="password"
                                            class="form-control" />
                                    </div>
                                    <button class="btn btn-primary w-100" type="button">Register</button>
                                </div>
                            </div>
                        @endguest --}}
                        <hr />
                        <div>
                            <div class="mb-3">
                                <input type="text" placeholder="Full name" class="form-control" name="name" />
                            </div>
                            <div class="input-group mb-3">
                                <span class="input-group-text text-dark" id="basic-addon1">+91</span>
                                <input type="text" placeholder="Phone" name="mobile" class="form-control"
                                    onkeyup="event.target.value = event.target.value.replace(/[^\d.-]+/g, '')" />
                            </div>

                            <div class="mb-3">
                                <input type="email" placeholder="Email" name="email" class="form-control" />
                            </div>

                            <button class="btn btn-primary w-100" id="checkout" type="submit">Checkout</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <button type="button" class="btn btn-sm btn-link position-absolute top-0 end-0 text-light" aria-label="Close"
        data-bs-dismiss="modal"><i class="fas fa-close"></i></button>
</form>


<script>
    function calculateSummery() {
        const total_quantity_el = document.getElementById("total-quantity");
        const total_amount_el = document.getElementById("total-amount");
        const grand_total_el = document.getElementById("grand-total");

        total_quantity_el.innerHTML = (() => {
            let all = 0;
            document.querySelectorAll("[data-field=quantity]").forEach((current) => all += parseInt(current
                .value) > 0 ? parseInt(current.value) : 0);
            return all;
        })();
        const total_amount = (() => {
            let all = 0;
            document.querySelectorAll("[data-field=total-price]").forEach((current) => all += parseInt(
                current.getAttribute("amount")) > 0 ? parseInt(current.getAttribute("amount")) : 0);
            return all;
        })();
        total_amount_el.innerHTML = money(total_amount);
        grand_total_el.innerHTML = total_amount_el.innerHTML;

        const discount_total_el = document.getElementById("discount-total");
        const net_total_el = document.getElementById("net-total");
        const discount_field = document.querySelector("input[name=discount]");
        const _discount_amount = (parseInt(discount_field.value) * total_amount) / 100
        const _adjusted_amount = total_amount - _discount_amount;
        discount_total_el.innerHTML = "- " + money(_discount_amount);
        net_total_el.innerHTML = money(_adjusted_amount);

        //toggle fields
        let discount_container = document.querySelector("#discount-container");
        if (total_amount > 0) {
            discount_container.classList.remove("d-none");
        } else {
            discount_container.classList.add("d-none");
        }

        let discount_elements = document.querySelectorAll(".discount-field");
        discount_elements.forEach(element => {
            if (_discount_amount > 0) {
                element.classList.remove("d-none");
            } else {
                element.classList.add("d-none");
            }
        })

    }

    function checkForm(_evt) {
        const total_quantity = parseInt(document.getElementById("total-quantity").innerHTML);
        if (total_quantity > 0) {} else {
            _evt.preventDefault();
            alert("Please select a ticket");
        }
    }


    $(function() {

        document.querySelectorAll("[data-row=ticket]").forEach(_row => {
            const _quantity_field = _row.querySelector("[data-field=quantity]");
            const _price_field = _row.querySelector("[data-field=price]");
            const _total_price_field = _row.querySelector("[data-field=total-price]");
            const _minus_btn = _row.querySelector("[data-field=minus]");
            const _plus_btn = _row.querySelector("[data-field=plus]");



            _quantity_field.addEventListener("change", (_evt) => {
                const _quantity = parseInt(_evt.target.value) < 0 ? 0 : parseInt(_evt.target
                    .value);
                const _total_price = parseInt(_price_field.value) * parseInt(_quantity) > 0 ?
                    parseInt(_price_field.value) * parseInt(_quantity) : 0;

                _quantity_field.value = _quantity;
                _total_price_field.innerHTML = money(_total_price);
                _total_price_field.setAttribute("amount", _total_price);

                calculateSummery()
            });

            _minus_btn.addEventListener("click", () => {
                const _quantity = (parseInt(_quantity_field.value) < 0 ? 0 : parseInt(
                    _quantity_field.value)) - 1;
                _quantity_field.value = _quantity >= 0 ? _quantity : 0;
                var event = new Event('change');
                _quantity_field.dispatchEvent(event);
            });
            _plus_btn.addEventListener("click", () => {
                const _quantity = _quantity_field.value == "" ? 0 : parseInt(_quantity_field
                    .value);
                _quantity_field.value = _quantity + 1;
                var event = new Event('change');
                _quantity_field.dispatchEvent(event);
            })
        });

        $('#email-container button').on('click', function(e) {
            e.preventDefault();
            const email = $("#email").val();
            if (email == "") return alert("Please enter your email");
            $.ajax({
                url: '/auth/check-user-email',
                type: 'POST',
                data: {
                    email: email
                },
                dataType: 'JSON',
                success: function(res) {
                    console.log(res);
                    if (res.data.is_existing_user) {
                        $('#login-container').show();
                        $('#register-container').hide();
                    } else {
                        $('#login-container').hide();
                        $('#register-container').show();
                    }
                    $('#email-container button').hide();
                }
            });
            return false;
        });

        $('#discount-container button').on('click', function(e) {
            e.preventDefault();
            const discount = $("#coupon").val();

            $.ajax({
                url: '/auth/check-user-discount',
                type: 'POST',
                data: {
                    coupon: discount
                },
                dataType: 'JSON',
                success: function(res) {
                    if (res.status == 200) {
                        document.querySelector("input[name=discount]").value = res.discount;
                        calculateSummery();
                    } else {
                        alert("Invalid Coupon Code");
                    }
                }
            });
            return false;
        });

        $("#coupon").keyup(evt => {
            document.querySelector("input[name=discount]").value = 0;
            calculateSummery();
        });


        $('#login-container button').on('click', function(e) {
            const email = $("#email").val();
            const password = $("#login-container input[name=password]").val();
            if (password == "" || password.length < 8) return alert(
                "please enter 8 alphanumeric password.");
            e.preventDefault();
            $.ajax({
                url: '/auth/try-login',
                type: 'POST',
                data: {
                    email: email,
                    password: password
                },
                dataType: 'JSON',
                success: function(res) {
                    if (res.data) {
                        $("#email-container").hide();
                        $('#login-container').hide();
                        $('#checkout').show();
                    } else {
                        alert(res.message);
                    }
                }
            });
            return false;
        });
        $('#register-container button').on('click', function(e) {
            e.preventDefault();
            const email = $("#email").val();
            const password = $("#register-container input[name=password]").val();
            const name = $("#register-container input[name=name]").val();
            const mobile = $("#register-container input[name=mobile]").val();
            console.log("");
            if (password == "" || password.length < 8) return alert(
                "please enter 8 alphanumeric password.");
            if (name == "") return alert("please enter your name.");
            if (mobile == "") return alert("please enter yor mobile number.");
            $.ajax({
                url: '/auth/try-login',
                type: 'POST',
                data: {
                    email: email,
                    name: name,
                    mobile: mobile,
                    password: password
                },
                dataType: 'JSON',
                success: function(res) {
                    if (res.data) {
                        $("#email-container").hide();
                        $('#register-container').hide();
                        $('#checkout').show();
                    } else {
                        alert(res.message);
                    }
                }
            });
            return false;
        });

    })
</script>
