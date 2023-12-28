<!--Slider Start-->
<div class="ast_slider_wrapper">
    <div class="ast_banner_text">
        <div class="starfield">
          <span></span>
          <span></span>
          <span></span>
          <span></span>
        </div>
        <div class="ast_waves">
            <div class="ast_wave"></div>
            <div class="ast_wave"></div>
            <div class="ast_wave"></div>
        </div>
        <div class="ast_waves2">
            <div class="ast_wave"></div>
            <div class="ast_wave"></div>
            <div class="ast_wave"></div>
        </div>
        <div class="ast_waves3">
            <div class="ast_wave"></div>
            <div class="ast_wave"></div>
            <div class="ast_wave"></div>
        </div>
        <div class="container">
            <div class="ast_bannertext_wrapper">
                <h1>Astrology reveals the will of God</h1>
                <ul class="ast_toppadder40 ast_bottompadder50">
                    <li>horoscopes</li>
                    <li>gemstones</li>
                    <li>numerology</li>
                </ul>
                <a class="typeform-share button" data-bs-toggle="modal" data-bs-target="#kundalichart-form" style="display:inline-block;text-decoration:none;background-color:#FF6F00;color:white;cursor:pointer;font-family:Helvetica,Arial,sans-serif;font-size:20px;line-height:50px;text-align:center;margin:0;height:50px;padding:0px 33px;border-radius:25px;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:bold;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;" data-size="100" target="_blank">Make It Now </a>
            </div>
        </div>
    </div>
</div>
<!--Slider End-->

<!-- Form Starts -->

<div class="form_container modal" id="kundalichart-form" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="kundalichart-container modal-content">
            <div class="left_container">
                <i><img src="images/header/favicon.png" alt=""></i>
                <h1 class="navgarah">Navgarah</h1>
                <div class="left_img">
                    <img class="l_img" src='https://s3-us-west-2.amazonaws.com/s.cdpn.io/38816/image-from-rawpixel-id-542207-jpeg.png'>
                </div>
            </div>

            <div class="right_container">
                <header>
                    <h1>Kundali Chart</h1>
                    <div class='set'>
                        <div class='name'>
                            <label for='name'>Name</label>
                            <input id='name' placeholder="Name" type='text'>
                        </div>
                        <div class='DoB'>
                            <label for='dob'>Date of Birth</label>
                            <input id='dob' placeholder="DD/MM/YY" type='text'>
                        </div>
                    </div>
                    <div class='set'>
                        <div class='gender'>
                            <label for='gender'>Gender</label>
                            <div class='radio-container'>
                                <input checked='' id='female' name='gender' type='radio' value='female'>
                                <label for='female'>Female</label>
                                <input id='male' name='gender' type='radio' value='male'>
                                <label for='male'>Male</label>
                            </div>
                        </div>
                        <div class='PoB'>
                            <label for='pob'>Place of Birth</label>
                            <input id='birthplace' placeholder="Birth Place" type='text'>
                        </div>
                    </div>
                    <div class='upload'>
                        <label for='upload'>Upload Kundali</label>
                        <div class='upload_container'>
                        <input checked='' id='pet-weight-0-25' name='pet-weight' type='file' value='0-25'>
                    </div>
                    <div class='question'>
                        <label for='question'>Question</label>
                        <div class='question_container'>
                        <input checked='' id='question' name='question' type='textarea'>
                    </div>
                </header>
                <footer>
                    <div class='set'>
                        <button data-bs-dismiss="modal" id='back'>Cancel</button>
                        <button id='next' type="submit">Submit</button>
                    </div>
                </footer>
            </div>
        </div>
    </div>
</div>

<script>
    $('#myModal').on('shown.bs.modal', function () {
  $('#myInput').trigger('focus')
})
</script>