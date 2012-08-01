$(document).ready(function() {
	
	/* Using this method to keep an ugly flash from the default DISQUS container to the DISQUS comments */
	$('#disqus_container').css('display', 'none');
	$('#disqus_container').delay(3000).fadeIn('fas†');
	
/*	$('li.sub-navigation > ul').css('opacity', '0');
	
	$('li.sub-navigation').hoverIntent (
		function () {
			$(this).find('ul').animate( { opacity: "1.0"}, 200 ).slideDown(200);
		},
		function () {
			$(this).find('ul').css('opacity', '0');
		}
	);*/
	
	/* Handling various carousel items */
	
	var carousel_count = $("#carousel > li").length;
	if( carousel_count == 1) {
		$("#slides.article_slideshow").addClass('single');
	}
	
	$('a.previous, a.next').delay(1000).animate({opacity: '1'}, 'slow'); // Show controls if more than 1 image exists
	
/*	var imageCount = $('#carousel.slides_container').length; // Count the number of images in the carousel
	if (imageCount >= "2") {
		$('a.previous, a.next').delay(1000).animate({opacity: '1'}, 'slow'); // Show controls if more than 1 image exists
	}
*/	
	$('ul#carousel').css({opacity: '0', display: 'block'});
	$('ul#carousel').delay(1000).animate({opacity: '1'}, 'slow');
		
    /* Slides */
    
	// Set starting slide to 1
	var startSlide = 1;
	// Get slide number if it exists
	if (window.location.hash) {
		startSlide = window.location.hash.replace('#','');
	}
	// Initialize Slides
	$('#slides.featured_articles').slides({
		preload: false,
		preloadImage: '_images/loading.gif',
		generatePagination: true,
		play: 8000,
		pause: 5000,
		slideSpeed: 400,
		hoverPause: true,
		prev: 'previous',
		paginationClass: 'carousel_controls',
		// Get the starting slide
		start: startSlide,
		animationComplete: function(current){
		}
	});
	
	$('#slides.article_slideshow').slides({
		preload: false,
		preloadImage: '_images/loading.gif',
		generatePagination: true,
		play: 7500,
		pause: 5000,
		bigTarget: false,
		effect: 'fade',
		fadeSpeed: 600,
		hoverPause: true,
		prev: 'previous',
		paginationClass: 'carousel_controls',
		// Get the starting slide
		start: startSlide,
		animationComplete: function(current){
		}
	});
	
});