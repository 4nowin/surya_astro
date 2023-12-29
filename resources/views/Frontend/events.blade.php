@extends('Frontend.app')

@section('title', 'Events')

@section('content')


<!--Breadcrumb start-->
<div class="ast_pagetitle">
<div class="ast_img_overlay"></div>
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="page_title">
                    <h2>About Us</h2>
                </div>
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12">
                <ul class="breadcrumb">
                    <li><a href="/">home</a></li>
                    <li>//</li>
                    <li><a href="/about">About us</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!--Breadcrumb end-->

<!--==========================
      Buy Ticket Section
    ============================-->
  <div class="ast_journal_wrapper ast_toppadder70 ast_bottompadder70">
    <div class="container">
    <section id="buy-tickets" class="section-with-bg wow fadeInUp">
      <div class="container">
        <div class="section-header" style="text-align: center;">
          <h2>Maha Shivratri 2024 Puja</h2>
          <p>Below are the puja plans for Maha Shivratri.</p>
        </div>
        <div class="row">
          <div class="col-lg-3">
            <div class="card mb-5 mb-lg-0">
                <div class="card-body" style="padding: 5px;">
                  <h3 class="text-center" style="text-align: center;">About Puja's</h3>
                  <p>Puja's will be done by us on the respective place and it can be shared  online, or by telephone. Skype or other online apps can be used to do the puja's via video or audio connections. It is not necessary to be in-person for the puja's.</p><hr>
                  <div class="card text-white bg-dark mb-3" style="padding: 5px; text-align: center;">
                      <h5 class="card-header text-white">SCHEDULE A PUJA</h5>
                    <div class="card-body" style="text-align: left;">
                      <p>1.) Select the puja.</p>
                      <p>2.) Purchase the puja.</p>
                      <p>3.) You will receive a confirmation email.</p>
                    </div>
                  </div><hr>
                  <div class="card bg-light mb-3" style="text-align: center;">
                      <h5 class="card-header">TYPES OF SERVICES</h5>
                    <div class="card-body" style="padding: 5px;" style="text-align: left;">
                      <h5 class="card-title">Kirpa</h5>
                      <h5 class="card-title">Siddhi</h5>
                      <h5 class="card-title">Rudra</h5>
                    </div>
                  </div><hr>
                  <div class="card text-white bg-success mb-3" style="padding: 5px;">
                      <h5 class="card-header text-white">For payments please use this QR Code</h5>
                    <div class="card-body">
                      <img src="images/page/qrcode.jpg" alt="">
                      <p>For confirmation please share your payment screenshot with us on the following WhatsApp number +91 70185 65737</p>
                    </div>
                  </div> <hr>
                  <div class="card text-white bg-danger mb-3" style="padding: 5px;">
                      <h5 class="card-header text-white">IMPORTANT NOTES</h5>
                    <div class="card-body">
                      <p>1.) All puja's appointment times are according to Indian Standard Time (IST).</p>
                      <p>2.) Puja's are all via online video/audio recorded or by telephone.</p>
                    </div>
                  </div> <hr>
                  <div class="card text-white bg-danger mb-3">
                      <h5 class="card-header text-white">Cancellations/Refunds</h5>
                    <div class="card-body" style="padding: 5px;">
                    <h3 class="card-title text-white">Cancelations</h3> 
                    <p>48 hours before the date of Puja a refund is available less a $25 cancellation fee.</p>
                    <h3 class="card-title text-white">Scheduling Changes</h3>
                    <p>You may change the type of a puja 24 hours before the scheduled puja time at no charge.</p>
                    </div>
                  </div>
                </div>
              </div>
          </div>
          <div class="col-lg-3">
            <div class="card mb-5 mb-lg-0">
              <div class="card-body" style="text-align: center;">
                <h1 class="card-price text-center" id="astros">Horoscope</h1>
                <h2 class="text-muted text-uppercase text-center">&#8377; 11 - 2551</h2>
                <p>For long life and good health, happiness and prosperity</p>
                <hr>
                <ul class="fa-ul" style="text-align: left;">
                  <li><span class="fa-li"><i class="fa fa-check"></i></span>Obstacles in business are overcome.</li>
                  <li><span class="fa-li"><i class="fa fa-check"></i></span>Job progresses.</li>
                  <li><span class="fa-li"><i class="fa fa-check"></i></span>Money grows in the home.</li>
                  <li><span class="fa-li"><i class="fa fa-check"></i></span>Indications/Relationships</li>
                  <li><span class="fa-li"><i class="fa fa-check"></i></span>Career/Finances.</li>
                  <li><span class="fa-li"><i class="fa fa-check"></i></span>In every way there is happiness and prosperity in the family.</li>
                </ul>
                <hr>
                <ul style="text-align: left;"><b>Prasad -</b><br>
                  <li>Panchmeva ka prasad or mishri</li>
                  <li>Intended thread</li>
                  <li>Photo of jyotirlinga</li>
                </ul><br>
                <div class="text-center">
                  <button type="button" class="btn payme btn-danger" data-bs-toggle="modal" data-bs-target="#horoscope-chart-form" amount="29" goal-selector="Kripa Puja" data-ticket-type="standard-access">Book Now</button>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-3">
            <div class="card mb-5 mb-lg-0">
              <div class="card-body" style="text-align: center;">
                <h1 class="text-center">Kundali</h1>
                <h2 class="text-muted text-uppercase text-center">&#8377; 111</h2>
                <p>For your life at present, the future that past life karma you brought & relationships & career.</p>
                <hr>
                <ul class="fa-ul" style="text-align: left;">
                  <li><span class="fa-li"><i class="fa fa-check"></i></span>All of Kripra puja</li>
                  <li><span class="fa-li"><i class="fa fa-check"></i></span>There are mental and physical health benefits.</li>
                  <li><span class="fa-li"><i class="fa fa-check"></i></span>There is happiness and prosperity in the family</li>
                  <li><span class="fa-li"><i class="fa fa-check"></i></span>Gaumata (Mother Cow) worship</li>
                </ul>
                <hr>
                <ul style="text-align: left;"><b>Prasad -</b><br>
                  <li>Panchmeva ka prasad or mishri</li>
                  <li>Intended thread</li>
                  <li>Photo of jyotirlinga</li>
                </ul><br>
                <div class="text-center">
                  <button type="button" class="btn payme btn-danger" data-bs-toggle="modal" data-bs-target="#kundali-chart-form" amount="59" goal-selector="Siddhi Puja" data-ticket-type="standard-access">Book Now</button>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-3">
            <div class="card">
              <div class="card-body"style="text-align: center;">
                <h1 class="text-center">Vastu</h1>
                <h2 class=" text-muted text-uppercase text-center">&#8377; 151</h2>
                <p>To fulfill all wishes, do Rudrabhishek in this Shivaratri</p>
                <hr>
                <ul class="fa-ul">
                  <li><span class="fa-li"><i class="fa fa-check"></i></span>All of Siddhi puja</li>
                  <li><span class="fa-li"><i class="fa fa-check"></i></span>With the grace of Lord Shiva, there is no fear of famine death.</li>
                  <li><span class="fa-li"><i class="fa fa-check"></i></span>You and the whole family are in good health.</li>
                  <li><span class="fa-li"><i class="fa fa-check"></i></span>Feeding 2 Brahmins.</li>
                </ul>
                <hr>

                <ul style="text-align: left;"><b>Prasad -</b><br>
                  <li>Panchmeva ka prasad or mishri</li>
                  <li>Intended thread</li>
                  <li>Photo of jyotirlinga</li>
                </ul><br>
                <div class="text-center">
                  <button type="button" class="btn payme btn-danger" data-bs-toggle="modal" data-bs-target="#vastu-chart-form" amount="69" goal-selector="Rudra Puja"  data-ticket-type="standard-access">Book Now</button>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>


      <!-- Modal Order Form -->
      <div id="ticket-modal" class="modal fade">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <div id="typein">
              </div>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <form method="POST" action="#">


                <label for="name">Your Name</label>
                <div class="input-group">
                  <input type="text" class="form-control" name="name" id="name" placeholder="Your Name" required="Please input your name" size="100%;">
                </div>

                <div class="form-group" style="display: none;">
                  <input type="text" class="form-control" name="type" id="type">
                  <input type="text" class="form-control" name="amount" id="amount">
                </div>

                <label>Date of Birth:</label>
                <div class="input-group date" id="id_4">
                    <input type="text" class="form-control" name="dob" id="dob" placeholder="Date of Birth" required="Please input your DOB"/>
                    <span class="input-group-addon">
                        <i class="glyphicon glyphicon-calendar fa fa-calendar"></i>
                    </span>
                </div>

                <label>Birth Time:</label>
                <div class="input-group date" id="id_2">
                    <input type="text" class="form-control" name="dot" id="dot" required/>
                    <span class="input-group-addon">
                        <i class="glyphicon glyphicon-time fa fa-clock-o"></i>
                    </span>
                </div>

                <label for="place">Birth City</label>
                <div class="input-group">
                  <input type="text" class="form-control" name="place" id="place" placeholder="Place of Birth" required="Please input your place of birth" size="100%;">
                </div>


                <label for="country">Country of Birth</label>
                <div class="input-group">
                  <input type="text" class="form-control" name="Country" id="country" placeholder="Country of Birth" required="Please input a country" size="100%;">
                </div>


                <label for="dob">Your Email</label>
                <div class="input-group">
                  <input type="email" class="form-control" name="your-email" id="email" placeholder="Your Email" required="Please input your email" size="100%;">
                </div>


                <label for="state">Any Special request for Puja?</label>
                <div class="input-group col-sm-12 col-md-12">
                  <textarea class="form-control" rows="3"  name="state" id="state" placeholder="Write NO if there is no request" style="width: 100%;"></textarea>
                </div>

                <div class="alert alert-danger" id="myDIV" role="alert" style="display: none;">
                    All Fields Are Required
                </div>
                
                <p id="error" class="hidden">Please check the checkbox</p>
                <label><input id="paycheck" type="checkbox"> Click Here to Continue</label>
                <div id='all_button' style="display: none;">
                <div id="paybu" style="text-align: center;">
                </div>
                <div id="pay_b">
                <div id="paypal-button-container"></div>
                </div>
                </div>
                <div style="margin-bottom: 45px;">
                </div>
              </form>
            </div>
          </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
      </div><!-- /.modal -->


        <!-- Modal Order Form -->
        <div id="loading" class="modal fade in" data-keyboard="false" data-backdrop="static">
          <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="container">
              <div class="row">
                <div class="col col-4 offset-4">
                <div class="loader">
                </div>
          </div><!-- /.modal-dialog -->
        </div><!-- /.modal -->
      </div>
      </div>
      </div>

      <!-- Modal Order Form -->
      <div id="buy-ticket-modal" class="modal fade">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h3 class="modal-title" style="color: green; text-align: center;">Payment Successful</h3>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <div id="success-modal">
              </div>
            </div>
          </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
      </div><!-- /.modal -->

      <!-- Form -->
      <div id="fail-modal" class="modal fade">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h3 class="modal-title" style="color: red; text-align: center;">Payment Unsuccessful</h3>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
                Oops! Something went wrong
            </div>
          </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
      </div><!-- /.modal -->

    </section>
  </div>
</div>


@include('Frontend.Home.wrapper')


<!-- some js was deleted from here in this page -->

@endsection
