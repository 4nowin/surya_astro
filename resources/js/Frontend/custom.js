
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
    
    var x = document.getElementById("login-dialog");
    var y = document.getElementById("signup-dialog");

    x.style.display = "none";
    y.style.display = "none";
    
    function myFunction(){
        if(x.style.display  == "none"){
            x.style.display = "block";
            document.getElementById("signup-dialog").style.display = "none";
        } else {
            x.style.display = "none";
        }
    }
    function myFunction1(){
        if(y.style.display  == "none"){
            y.style.display = "block";
            document.getElementById("login-dialog").style.display = "none";
        } else {
            y.style.display = "none";
        }
    }