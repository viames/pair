/**
 * @version $Id$
 * @author	VIames Marino
 * @package	Pair
 */

$(document).ready(function() {
	
	// show/hide password content in user edit
	$('input[name="showPassword"]').click(function(){
		var type = $(this).is(':checked') ? 'text' : 'password';
		$('input[name="password"]').attr('type', type);
	});
	
	var timezone = jstz.determine();
	$("input[name='timezone']").val(timezone.name());

});