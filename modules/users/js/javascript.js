/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	VMS
 */

$(document).ready(function() {

	$('input[name="password"]').pwstrength({
        ui: { showVerdictsInsideProgressBar: true }
    });
	
	// unmask password checkbox
	$('input[name="showPassword"]').on('ifChanged', function(){
		var type = $(this).is(':checked') ? 'text' : 'password';
		$('input[name="password"]').attr('type', type);
	});
	
});