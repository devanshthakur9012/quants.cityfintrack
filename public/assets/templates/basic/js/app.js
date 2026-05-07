'use strict';

$( document ).ready(function() {
  //preloader
  $(".preloader").delay(300).animate({
    "opacity" : "0"
    }, 300, function() {
    $(".preloader").css("display","none");
  });
});

// menu options custom affix
var fixed_top = $(".header");
$(window).on("scroll", function(){
    if( $(window).scrollTop() > 50){  
        fixed_top.addClass("animated fadeInDown menu-fixed");
    }
    else{ 
        fixed_top.removeClass("animated fadeInDown menu-fixed");
    }
});

// mobile menu js
$(".navbar-collapse>ul>li>a, .navbar-collapse ul.sub-menu>li>a").on("click", function() {
  const element = $(this).parent("li");
  if (element.hasClass("open")) {
    element.removeClass("open");
    element.find("li").removeClass("open");
  }
  else {
    element.addClass("open");
    element.siblings("li").removeClass("open");
    element.siblings("li").find("li").removeClass("open");
  }
});

var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl)
})

// main wrapper calculator
var bodySelector = document.querySelector('body');
var header = document.querySelector('.header');
var footer = document.querySelector('.footer');

(function(){
  if(bodySelector.contains(header) && bodySelector.contains(footer)){
    var headerHeight = document.querySelector('.header').clientHeight;
    var footerHeight = document.querySelector('.footer').clientHeight;
    
    document.querySelector('.page-scroll-bar').style.top = `calc(${headerHeight}px + 1px)`;

    // if header isn't fixed to top
    // var totalHeight = parseInt( headerHeight, 10 ) + parseInt( footerHeight, 10 ) + 'px'; 
    
    // if header is fixed to top
    var totalHeight = parseInt( footerHeight, 10 ) + 'px'; 
    var minHeight = '100vh';
    document.querySelector('.main-wrapper').style.minHeight = `calc(${minHeight} - ${totalHeight})`;
  }
})();

// Show or hide the sticky footer button
$(window).on("scroll", function() {
	if ($(this).scrollTop() > 200) {
			$(".scroll-to-top").fadeIn(200);
	} else {
			$(".scroll-to-top").fadeOut(200);
	}
});

// Animate the scroll to top
$(".scroll-to-top").on("click", function(event) {
	event.preventDefault();
	$("html, body").animate({scrollTop: 0}, 300);
});

new WOW().init();

gsap.registerPlugin(ScrollTrigger);
gsap.to('progress', {
  value: 100,
  ease: 'none',
  scrollTrigger: { scrub: 0.3 }
});

Array.from(document.querySelectorAll('table')).forEach(table => {
	let heading = table.querySelectorAll('thead tr th');
	Array.from(table.querySelectorAll('tbody tr')).forEach((row) => {
		Array.from(row.querySelectorAll('td')).forEach((colum, i) => {
			if (colum.hasAttribute('colspan') && i == 0) {
				return false;
			}
			colum.setAttribute('data-label', heading[i].innerText)
		});
	});
});
