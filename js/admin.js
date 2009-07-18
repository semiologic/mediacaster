jQuery(document).ready(function($) {
	$('#media-items table').hover(function() {
		$('#media-items').sortable('disable');
	}, function() {
		$('#media-items').sortable('enable');
	});
});