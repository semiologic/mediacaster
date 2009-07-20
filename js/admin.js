jQuery(document).ready(function($) {
	$('#media-items object, #media-items embed').hover(function() {
		$('#media-items').sortable('disable');
	}, function() {
		$('#media-items').sortable('enable');
	});
});