/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

(function ($) {

    // extended functions
    $.extend(
			
        /**
         * Display a message box in the subject container DOM object.
         * ex. $('#notificationArea').prependMessageBox('Title','Message text','info');
         *
         * @param    string    Message title.
         * @param    string    Message text.
         * @param    string    Type (info, success, warning, error)
         */
        $.fn.prependMessageBox = function (title, message, type) {
			
        	if ('info'==type || 'success'==type || 'warning'==type || 'error'==type) {
			toastr[type](message, title);
        	} else {
        		toastr['error']('Unexpected server response', 'Error');
        	}
        	
        },

         /**
         * Sets the event list visible or hidden.
         */
        $.toggleLogEvents = function() {

        	if ($('#log .events').is(':visible')) {
        		$('#log .events').hide().addClass('hidden');
        		$('#logShowEvents').html('Show <span class="fa fa-caret-down"></span>');
        		$.cookie('LogShowEvents', 0, {path: '/'});
        	} else {
        		$('#log .events').show().removeClass('hidden');
        		$('#logShowEvents').html('Hide <span class="fa fa-caret-up"></span>');
        		$.cookie('LogShowEvents', 1, {path: '/'});
        		$('html, body').stop().animate({'scrollTop': $('#log .events').offset().top-60}, 200, 'swing');
        	}
        	
        },

        /**
         * Sets the queries visible or hidden in log area.
         */
        $.toggleLogQueries = function() {

        	var menuItem = $('#log .head .item.database');
        	
        	if (menuItem.hasClass('active')) {
        		menuItem.removeClass('active');
        		$('#log .query').addClass('hidden');
        		$.cookie('LogShowQueries', 0, {path: '/'});
        	} else {
        		menuItem.addClass('active');
        		$('#log .query').removeClass('hidden');
        		$.cookie('LogShowQueries', 1, {path: '/'});
        		if ($('#log .events').is(':hidden')) {
        			$('#logShowEvents').trigger('click');
        		}
        	}
        	
        },

        $.addAjaxLog = function(log) {
        	$('#log > .events').append(log);
        }
		
    );

})(jQuery);

$(document).ready(function() {
	
    $("#menu-toggle").click(function(e) {
        e.preventDefault();
        $("#wrapper").toggleClass("toggled");
    });

    /**
     * Listener for click on log showEvents button.
     */
	$('#logShowEvents').click(function() {
		$.toggleLogEvents();
	});
	
	/**
	 * Show events when clicked the warning item in the log header
	 */
	$('#log .head a.item.warning').click(function() {
		if ($('#log .events').is(':hidden')) {
			$('#logShowEvents').trigger('click');
		}
	});
	
	/**
	 * Show events when clicked the error item in the log header
	 */
	$('#log .head a.item.error').click(function() {
		if ($('#log .events').is(':hidden')) {
			$('#logShowEvents').trigger('click');
		}
	});

	/**
	 * Listener for click on log showQueries item.
	 */
	$("#log .head .item.database").click(function() {
		$.toggleLogQueries();
	});

});
