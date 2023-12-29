
$('.owl-carousel').owlCarousel({
    loop:true,
    margin:10,
    nav:true,
    autoplay:true,
    responsive:{
        0:{
            items:1
        },
        600:{
            items:1
        },
        1000:{
            items:1
        }
    }
});

document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("openbtn").addEventListener("click", function() {
        document.getElementById("mySidenav").style.width = "300px";
    });
    document.getElementById("closebtn").addEventListener("click", function() {
        document.getElementById("mySidenav").style.width = "0";
    });

    window.onscroll = function() {
      const header = document.getElementById("header");
      const h1font = document.getElementById("h1font");
      const hsvg = document.getElementsByClassName('hsvg');
      if(header){
        header.classList.toggle("scrolled", window.scrollY > 200);
      }
      if(h1font){
        h1font.classList.toggle("hscrolled", window.scrollY > 200);
      }
      hsvg[0].classList.toggle("svgscrolled", window.scrollY > 200);
      hsvg[1].classList.toggle("svgscrolled", window.scrollY > 200);
      hsvg[2].classList.toggle("svgscrolled", window.scrollY > 200);
      hsvg[3].classList.toggle("svgscrolled", window.scrollY > 200);
      };
  });
