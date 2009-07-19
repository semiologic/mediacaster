jQuery(document).ready(function($) {
	$('#media-items table, object, embed').hover(function() {
		$('#media-items').sortable('disable');
	}, function() {
		$('#media-items').sortable('enable');
	});
});