   <!-- All the models are here -->

   <!-- kundli model start here -->

   <div class="form_container modal" id="kundali-chart-form" tabindex="-1" role="dialog">
       <div class="modal-dialog modal-dialog-centered" role="document">
           <div class="chart-container modal-content">
               <div class="right_container">
                   <form method="POST" action="/inquiry">
                       @csrf
                       <input type="text" name="for" value="kundli" hidden>
                       <header>
                           <h1>Kundali Chart</h1>
                           <div class="container">
                               <div class='row'>
                                   <div class="col-12">
                                       <input type="text" name="name" class="form-control" placeholder="Name" aria-label="Name" required>
                                   </div>
                               </div>
                               <div class='row mt-4'>
                                   <div class="col-6">
                                       <input type="text" name="phone" class="form-control" placeholder="Phone" aria-label="Phone" required>
                                   </div>
                                   <div class='col-6'>
                                       <input type="email" name="email" class="form-control" placeholder="Email" aria-label="Email">
                                   </div>
                               </div>
                               <div class='row mt-2'>
                                   <div class='col-6'>
                                       <label for="country">Country:</label>
                                       <select name="country" id="country" name="country" class="form-select" aria-label="Default select example">
                                           <option value="india" selected>India</option>
                                           <option value="britain">Great Britain</option>
                                           <option value="sri_lanka">Sri Lanka</option>
                                           <option value="nepal">Nepal</option>
                                       </select>
                                   </div>
                                   <div class='col-6'>
                                       <label for='pob'>Birth Place</label>
                                       <input type="text" class="form-control" name="place_of_birth" placeholder="Birth Place" aria-label="place_of_birth" required>
                                   </div>
                               </div>
                               <div class='row mt-2'>
                                   <div class='col-12'>
                                       <label for='dob'>Date of Birth</label>
                                       <input id='birth' class="form-control" name="date_of_birth" placeholder="DD/MM/YY" type='datetime-local' required>
                                   </div>
                               </div>

                               <div class='row mt-3'>
                                   <div class='col-12'>
                                       <label for='gender'>Gender</label>
                                       <div class='radio-container'>
                                           <input checked='' id='female' name='gender' type='radio' value='female'>
                                           <label for='female'>Female</label>
                                           <input id='male' name='gender' type='radio' value='male'>
                                           <label for='male'>Male</label>
                                       </div>
                                   </div>
                               </div>
                               <div class='col-12 mt-3'>
                                   <label for='message'>Question</label>
                                   <div class='question_container mt-1'>
                                       <textarea name='message' rows="4"></textarea>
                                   </div>
                               </div>
                       </header>
                       <footer>
                           <div class='set'>
                               <button data-bs-dismiss="modal" id='back'>Cancel</button>
                               <button id='next' type="submit">Submit</button>
                           </div>
                       </footer>
                   </form>
               </div>
           </div>
       </div>
   </div>

   <!-- Kundli Model end here -->

   <!-- horescope model start here -->

   <div class="form_container modal" id="horoscope-chart-form" tabindex="-1" role="dialog">
       <div class="modal-dialog modal-dialog-centered" role="document">
           <div class="chart-container modal-content">
               <div class="right_container">
                   <form method="POST" action="/inquiry">
                       @csrf
                       <input type="text" name="for" value="horescope" hidden>
                       <header>
                           <h1>Get Horoscope</h1>
                           <div class="container">
                               <div class='row'>
                                   <div class="col-12">
                                       <input type="text" name="name" class="form-control" placeholder="Name" aria-label="Name" required>
                                   </div>
                               </div>
                               <div class='row mt-4'>
                                   <div class="col-6">
                                       <input type="text" name="phone" class="form-control" placeholder="Phone" aria-label="Phone" required>
                                   </div>
                                   <div class='col-6'>
                                       <input type="email" name="email" class="form-control" placeholder="Email" aria-label="Email">
                                   </div>
                               </div>
                               <div class='row mt-2'>
                                   <div class='col-12'>
                                       <label for="type">Frequency:</label>
                                       <select name="country" id="country" name="type" class="form-select" aria-label="Default select example">
                                           <option value="daily" selected>Daily</option>
                                           <option value="weekly">Weekly</option>
                                           <option value="monthly">Monthly</option>
                                           <option value="yearly">Yearly</option>
                                       </select>
                                   </div>
                               </div>
                               <div class='row mt-2'>
                                   <div class='col-12'>
                                       <label for='dob'>Horoscope for</label>
                                       <input id='birth' class="form-control" name="date_of_birth" placeholder="DD/MM/YY" type='date' required>
                                   </div>
                               </div>
                               <div class="row mt-3">
                                   <div class='col-12'>
                                       <label for='gender'>Gender</label>
                                       <div class='radio-container'>
                                           <input checked='' id='female' name='gender' type='radio' value='female'>
                                           <label for='female'>Female</label>
                                           <input id='male' name='gender' type='radio' value='male'>
                                           <label for='male'>Male</label>
                                       </div>
                                   </div>
                               </div>
                           </div>
                       </header>
                       <footer>
                           <div class='set'>
                               <button data-bs-dismiss="modal" id='back'>Cancel</button>
                               <button id='next' type="submit">Submit</button>
                           </div>
                       </footer>
                   </form>
               </div>
           </div>
       </div>
   </div>

   <!-- horescope Model end here -->

   <!-- vastu model start here -->

   <div class="form_container modal" id="vastu-chart-form" tabindex="-1" role="dialog">
       <div class="modal-dialog modal-dialog-centered" role="document">
           <div class="chart-container modal-content">

               <div class="right_container">
                   <form method="POST" action="/inquiry">
                       @csrf
                       <input type="text" name="for" value="vastu" hidden>
                       <header>
                           <h1>Get Vastu Details</h1>
                           <div class="container">
                               <div class='row'>
                                   <div class="col-12">
                                       <input type="text" name="name" class="form-control" placeholder="Name" aria-label="Name" required>
                                   </div>
                               </div>
                               <div class='row mt-4'>
                                   <div class="col-6">
                                       <input type="text" name="phone" class="form-control" placeholder="Phone" aria-label="Phone" required>
                                   </div>
                                   <div class='col-6'>
                                       <input type="email" name="email" class="form-control" placeholder="Email" aria-label="Email">
                                   </div>
                               </div>
                               <div class='row mt-2'>
                                   <div class='col-6'>
                                       <label for="country">Country:</label>
                                       <select name="country" id="country" name="country" class="form-select" aria-label="Default select example">
                                           <option value="india" selected>India</option>
                                           <option value="britain">Great Britain</option>
                                           <option value="sri_lanka">Sri Lanka</option>
                                           <option value="nepal">Nepal</option>
                                       </select>
                                   </div>
                                   <div class='col-6'>
                                       <label for='pob'>Situated At:</label>
                                       <input type="text" class="form-control" name="place_of_birth" placeholder="House Place" aria-label="place_of_birth" required>
                                   </div>
                               </div>
                               <div class='row mt-2'>
                                   <div class='col-12'>
                                       <label for='dob'>House build on</label>
                                       <input id='birth' class="form-control" name="date_of_birth" placeholder="DD/MM/YY" type='date' required>
                                   </div>
                               </div>
                           </div>
                       </header>
                       <footer>
                           <div class='set'>
                               <button data-bs-dismiss="modal" id='back'>Cancel</button>
                               <button id='next' type="submit">Submit</button>
                           </div>
                       </footer>
                   </form>
               </div>
           </div>
       </div>
   </div>

   <!-- vastu Model end here -->