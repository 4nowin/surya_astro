@extends('Frontend.app')

@section('title', 'Surya Astrologers')

@section('content')
    
<!--Slider Start-->
<div class="ast_slider_wrapper ast_index_gemstone">
    <div class="ast_banner_text">
        <div class="ast_waves2">
            <div class="ast_wave"></div>
            <div class="ast_wave"></div>
            <div class="ast_wave"></div>
        </div>
        <div class="container">
            <div class="ast_bannertext_wrapper">
                <h1>Astrology revels the will of God</h1>
                <ul class="ast_toppadder40 ast_bottompadder50">
                    <li>horoscopes</li>
                    <li>gemstones</li>
                    <li>numerology</li>
                </ul>
                <a class="ast_btn">make it now</a>
            </div>
        </div>
    </div>
</div>
<!--Slider End-->
<!--gemstone form start-->
<div class="ast_searchbox_wrapper gray_wrapper gemstone_search">
    <div class="container">
        <div class="ast_search_box">
            <div class="row">
                <h1>Know your Lucky Gemstone</h1>
                <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                    <input type="text" placeholder="Name">
                </div>
                <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                    <input type="text" placeholder="Place Of Birth">
                </div>
                <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                    <input type="text" placeholder="Time Of Birth">
                </div>
                <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                    <input type="text" placeholder="Date Of Birth">
                </div>
                <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                    <select>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
                <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                    <button type="submit" class="ast_btn">Get now</button>
                </div>
            </div>
        </div>
    </div>
</div>
<!--gemstone form end-->
<!--Gemstones Start-->
<div class="ast_gemstones_wrapper gray_wrapper ast_toppadder70 ast_bottompadder50">
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                <div class="ast_gemstones_slider">
                    <div class="row">
                        <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                            <div class="ast_gemstonea_slider_box">
                                <img src="images/page/garnet.jpg" alt="Stone">
                                <h4>Garnet (गोमेद रत्न)</h4>
                                <p>Garnet is a semi-precious gemstone and is best known for its healing properties.</p>
                                <div class="clearfix"></div>
                                <a href="/gemstones_single" class="ast_btn">know more</a>
                            </div>                      
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                            <div class="ast_gemstonea_slider_box">
                                <img src="images/page/amethyst.jpg" alt="Stone">
                                <h4>Amethyst (जमुनिया)</h4>
                                <p>Worn to overcome financial stress, professional instability and unhealthy addictions.</p>
                                <div class="clearfix"></div>
                                <a href="/gemstones_single" class="ast_btn">know more</a>
                            </div>                      
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                            <div class="ast_gemstonea_slider_box">
                                <img src="images/page/aquamarine.jpg" alt="Stone">
                                <h4>Aquamarine (बैरूंज रत्न)</h4>
                                <p>It is a precious blue-green gem & is often worn by those seeking tranquillity and serenity.</p>
                                <div class="clearfix"></div>
                                <a href="/gemstones_single" class="ast_btn">know more</a>
                            </div>                      
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                            <div class="ast_gemstonea_slider_box">
                                <img src="images/page/diamond.jpg" alt="Stone">
                                <h4>Diamond (हीरा रत्न)</h4>
                                <p>It is renowned for improving & enhancing health & beauty, intellect & coolness of mind.</p>
                                <div class="clearfix"></div>
                                <a href="/gemstones_single" class="ast_btn">know more</a>
                            </div>                      
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                            <div class="ast_gemstonea_slider_box">
                                <img src="images/page/emerald.jpg" alt="Stone">
                                <h4>Emerald (पन्ना रत्न)</h4>
                                <p>You will be blessed with good fortune, have strengthened memory, and intelligence.</p>
                                <div class="clearfix"></div>
                                <a href="/gemstones_single" class="ast_btn">know more</a>
                            </div>                      
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                            <div class="ast_gemstonea_slider_box">
                                <img src="images/page/alexandrite.jpg" alt="Stone">
                                <h4>Alexandrite (एलेग्जेंडर रत्न)</h4>
                                <p>It has been believed to bring good luck, good fortune, prosperity, and abundance.</p>
                                <div class="clearfix"></div>
                                <a href="/gemstones_single" class="ast_btn">know more</a>
                            </div>                      
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                            <div class="ast_gemstonea_slider_box">
                                <img src="images/page/ruby.jpg" alt="Stone">
                                <h4>Ruby (माणिक रत्न)</h4>
                                <p>It ensures good health, Mental Sharpness, Healthy Heart, Blood pressure and immunity.</p>
                                <div class="clearfix"></div>
                                <a href="/gemstones_single" class="ast_btn">know more</a>
                            </div>                      
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                            <div class="ast_gemstonea_slider_box">
                                <img src="images/page/peridot.jpg" alt="Stone">
                                <h4>Peridot (पेरीडॉट रत्‍न)</h4>
                                <p>It brings good health, restful sleep & peace to relationships by balancing emotions and mind.</p>
                                <div class="clearfix"></div>
                                <a href="/gemstones_single" class="ast_btn">know more</a>
                            </div>                      
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                            <div class="ast_gemstonea_slider_box">
                                <img src="images/page/sapphire.jpg" alt="Stone">
                                <h4>Sapphire (नीलम रत्न)</h4>
                                <p>It is very effective Gemstone. It brings Wealth, Money, Fame & Power to the wearer.</p>
                                <div class="clearfix"></div>
                                <a href="/gemstones_single" class="ast_btn">know more</a>
                            </div>                      
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                            <div class="ast_gemstonea_slider_box">
                                <img src="images/page/rzircon.jpg" alt="Stone">
                                <h4>rose zircon (रोज जरकन रत्न)</h4>
                                <p>It helps in achieving of dreams & goals. It will help to attract many goods and honors.</p>
                                <div class="clearfix"></div>
                                <a href="/gemstones_single" class="ast_btn">know more</a>
                            </div>                      
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                            <div class="ast_gemstonea_slider_box">
                                <img src="images/page/topaz.jpg" alt="Stone">
                                <h4>Topaz (पुखराज रत्न)</h4>
                                <p>It brings joy, generosity, abundance & good health. It is also a stone of love & good fortune.</p>
                                <div class="clearfix"></div>
                                <a href="/gemstones_single" class="ast_btn">know more</a>
                            </div>                      
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                            <div class="ast_gemstonea_slider_box">
                                <img src="images/page/bzecron.jpg" alt="Stone">
                                <h4>blue zecron (नीला जरकन रत्न)</h4>
                                <p>It brings calmness and mental stability. Boosts Creativity & Self Confidence</p>
                                <div class="clearfix"></div>
                                <a href="/gemstones_single" class="ast_btn">know more</a>
                            </div>                      
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--Gemstones End-->
<!--About Us Start-->
<div class="ast_about_wrapper ast_toppadder70 ast_bottompadder70">
    <div class="container">
        <div class="row">
            <div class="col-lg-5 col-md-5 col-sm-12 col-xs-12">
                <div class="ast_about_info_img abt_img">
                <img src="images/page/gemstones.jpg" alt="" class="img-responsive">
                </div>
            </div>
            <div class="col-lg-7 col-md-7 col-sm-12 col-xs-12">
                <div class="ast_about_info">
                    <h4>know about gemstones</h4>
                    <p>Gemstones are basically rocks or minerals that are cut and polished to enhance their natural beauty. They are of various colors and shapes. Human kind has always been fascinated by gemstones and their beauty. Today, gemstones are used for astronomical as well as ornamental purposes. The beautiful cut, shine, and shapes attract a lot of people from all over the world.</p>
                    <p>There are many reasons why you should wear gemstones. Traditionally, they are believed to remove all the negative energies attached to any zodiac sign. There are different gemstones for all zodiac signs, so you can benefit a lot by wearing a gemstone of your zodiac sign. Opal, yellow sapphire, emerald, ruby, red coral etc are the names of a few gemstones which are said to possess great powers. It is said that they transfer energies to people by body contact and cures many diseases. It is also said that they are bring good luck with them. So if you are having problems such as marriage conflicts, promotion issues or health issues, you can wear a gemstone, and who knows, your luck may take a U-turn and things may start falling into place!</p>
                </div>
            </div>
        </div>
    </div>
</div>
<!--About Us End-->
@include('Frontend.Home.overview')
@include('Frontend.Home.wrapper')


@endsection

