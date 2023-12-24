@extends('Frontend.app')

@section('title', 'Surya Astrologers')

@section('content')

    <!--Breadcrumb start-->
<div class="ast_pagetitle">
<div class="ast_img_overlay"></div>
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="page_title">
                    <h2>FAQ</h2>
                </div>
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12">
                <ul class="breadcrumb">
                    <li><a href="/">home</a></li>
                    <li>//</li>
                    <li><a href="/faq">FAQ</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!--Breadcrumb end-->
<!-- policy section start -->
<div class="ast_faq_wrapper ast_toppadder70 ast_bottompadder70">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 col-md-12 col-sm-12 col-xs-12 col-lg-offset-1">
                <div class="ast_faq_section">
                    <div class="panel-group" id="accordion">
                    <div class="panel panel-default">
                      <div class="panel-heading">
                        <h4 class="panel-title">
                          <a data-toggle="collapse" data-parent="#accordion" href="#collapse1">What is 'the zodiac' ?</a>
                        </h4>
                      </div>
                      <div id="collapse1" class="panel-collapse collapse in">
                        <div class="panel-body">The zodiac is the twelve divisions of the Earth's orbit around the Sun, as they are experienced in the Northern Hemisphere. The constellations got their names from the zodiac as a means for determining when the individual signs begin and what is to be expected during each sign.</div>
                      </div>
                    </div>
                    <div class="panel panel-default">
                      <div class="panel-heading">
                        <h4 class="panel-title">
                          <a data-toggle="collapse" data-parent="#accordion" href="#collapse2">Why are interpretations by various astrologers so different for the same forecast period?</a>
                        </h4>
                      </div>
                      <div id="collapse2" class="panel-collapse collapse">
                        <div class="panel-body">Astrology is a vast subject, it is an ocean and no man can ever measure its depth.
                          Physics has been developing ever since Galileo and Newton first formalized it 4 centuries back. And yet, within 4 centuries only you have a subject vast enough that even the greatest physicists are not thorough with all its aspects. Those into quantum physics, often get out of touch of classical mechanics, and so on.
                          Now, think of a subject which is not centuries but millenniums old. The first written evidence of astrology dates back to 5500 BC as per current archaeological knowledge. Now, how can someone know it all in just a few years? The moment a man makes the claim of being accurate, know that he isn’t an astrologer. It is outright impossible to be 100% accurate. At best, 70% is what you can hope for. In fact, the strongly a person claims accuracy, the quickly the person gets proven wrong.
                          The most important factor is that astrology has been tampered with, to no end. Astrologers do not read from original scriptures, but books available in markets by any random author. There are branches of astrology that have been completely ignored. Just scroll quora and you would find people making weird claims. “Ketu in 12th house is excellent to have as it would give you moksha” is what I read today. Now, Ketu stays in the 12th house for 2hours each day. Which means 1/12th of the world’s population must be getting moksha any day now.
                          The thing that happens is that the horoscope is a complex chart. If you’ve ever seen a horoscope, it is only a box with 12 small boxes with 9 words and a few numbers. That is all! That is all your life, all the thoughts, all you’ve ever spoken or done.
                          It is from those 12 boxed and 9 words that your entire life is read. Each day, each hour, and each second! Now, just imagine the number of permutations and combinations that would form!!
                          Astrologers differ in opinion because they all have their rules. Memorized rule.
                          And yet, one rules cuts the other, a third rule cuts the second, a fourth cuts the third, and on and on. The more you know, the more factors you can comprehend at once, the more you can visualize, the more you are accurate. Otherwise, you end up being no different that other people.
                          It is not about one method, never about one method. It is about knowledge of all aspects as a whole. It is about wisdom. The more you know, the more you can tell, the less you know, the more you’ll be wrong.
                        </div>
                      </div>
                    </div>
                    <div class="panel panel-default">
                      <div class="panel-heading">
                        <h4 class="panel-title">
                          <a data-toggle="collapse" data-parent="#accordion" href="#collapse3">What is a Rising Sign or Ascendant?</a>
                        </h4>
                      </div>
                      <div id="collapse3" class="panel-collapse collapse">
                        <div class="panel-body">Rising sign (also known as your Ascendant) is your social personality. It is how you dawn on people as it relates to the zodiac sign that was on the Eastern horizon when you were born. Your rising sign represents your physical body and outward style. The ascendant is the astrological sign that is ascending on the eastern horizon at the specific time and location of an event. According to certain astrological theories, celestial phenomena reflect or determine human activity on the principle of 'as above, so below'.</div>
                      </div>
                    </div>
                    <div class="panel panel-default">
                      <div class="panel-heading">
                        <h4 class="panel-title">
                          <a data-toggle="collapse" data-parent="#accordion" href="#collapse4">What is 'the house' ?</a>
                        </h4>
                      </div>
                      <div id="collapse4" class="panel-collapse collapse">
                        <div class="panel-body">Most horoscopic traditions of astrology systems divide the horoscope into a number of houses whose positions depend on time and location rather than on date. In Hindu astrological tradition these are known as Bhāvas or house. The houses are divisions of the ecliptic plane (a great circle containing the Sun's orbit, as seen from the earth), at the time and place of the horoscope in question. They are numbered counter-clockwise from the cusp of the first house.</div>
                      </div>
                    </div>
                  </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- policy section end -->
<!-- Download wrapper start-->

@include('Frontend.Home.wrapper')
<!-- Download wrapper End-->



@endsection

