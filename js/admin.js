jQuery(document).ready(function() {
	var mc = {
		player: null,
		interval: null,
		post_id: null,
		default_width: 420,
		max_width: 420,
		letter_width: 620,
		media_player: null,
		site_url: null,
		
		show_crossdomain: function(post_id) {
			jQuery("#mc-crossdomain-" + post_id).not(":visible").fadeIn('fast');
			return false;
		},
		
		set_type: function() {
			mc.cancel_snapshot(0);
			mc.set_max(0);
			
			var src = jQuery("#mc-src-0").val();
			
			if ( !src )
				return mc.set_file();
			else if ( !src || !src.match(/\//) )
				return mc.set_invalid();
			else if ( src.match(/^(https?:\/\/)?([^\/]+\.)?youtube\.com/i) )
				return mc.set_youtube();
			else if ( src.match(/\.(mp3|m4a|aac)$/i) || src.match(/[\/=](rss2?|xml|feed|atom)(\/|&|$)/i) )
				return mc.set_audio();
			else if ( src.match(/\.(flv|f4b|f4p|f4v|mp4|m4v|mov|3pg|3g2)$/i) )
				return mc.set_video();
			else if ( src.match(/\.[0-9a-z]+$/i) )
				return mc.set_file();
			else
				return mc.set_invalid();
		},
		
		set_youtube: function() {
			jQuery("#mc-content-row, #mc-link-row, #mc-snapshot-row, #mc-thickbox-row, #mc-thumbnail-row, #mc-autostart-row, #mc-player-row").not(":hidden").fadeOut('fast');
			jQuery("#mc-youtube-row, #mc-format-row").not(":visible").fadeIn('fast');
			return false;
		},
		
		set_audio: function() {
			jQuery("#mc-youtube-row, #mc-format-row, #mc-snapshot-row, #mc-thickbox-row, #mc-thumbnail-row").not(":hidden").fadeOut('fast');
			jQuery("#mc-content-row, #mc-link-row, #mc-autostart-row, #mc-player-row").not(":visible").fadeIn('fast');
			return false;
		},
		
		set_video: function() {
			jQuery("#mc-youtube-row").not(":hidden").fadeOut('fast');
			jQuery("#mc-content-row, #mc-link-row, #mc-format-row, #mc-snapshot-row, #mc-thickbox-row, #mc-thumbnail-row, #mc-autostart-row, #mc-player-row").not(":visible").fadeIn('fast');
			return false;
		},
		
		set_file: function() {
			jQuery("#mc-link-row, #mc-youtube-row, #mc-format-row, #mc-snapshot-row, #mc-thickbox-row, #mc-thumbnail-row, #mc-autostart-row, #mc-player-row").not(":hidden").fadeOut('fast');
			jQuery("#mc-content-row").not(":visible").fadeIn('fast');
			return false;
		},
		
		set_invalid: function() {
			jQuery("#mc-content-row, #mc-link-row, #mc-youtube-row, #mc-format-row, #mc-snapshot-row, #mc-thickbox-row, #mc-thumbnail-row, #mc-autostart-row, #mc-player-row").not(":hidden").fadeOut('fast');
			return false;
		},

		set_max: function(post_id) {
			jQuery("#mc-width-" + post_id).val('');
			jQuery("#mc-height-" + post_id).val('');
			mc.get_scale(post_id);
			return false;
		},

		set_4_3: function(post_id) {
			var default_width = mc.default_width;
			var template = jQuery(window.parent.document.getElementById('page_template')).val();
			
			if ( template == 'letter.php' )
				default_width = 620;
			else if ( template == 'monocolumn.php' && default_width != mc.max_width )
				default_width = mc.max_width;
			
			if ( jQuery("#mc-preview-" + post_id).children('img').size() )
				default_width = parseInt(jQuery("#mc-snapshot-width-" + post_id).val());
			
			if ( !jQuery("#mc-width-" + post_id).val() )
				jQuery("#mc-width-" + post_id).val(default_width);
			jQuery("#mc-height-" + post_id).val(Math.ceil(parseInt(jQuery("#mc-width-" + post_id).val()) * 3 / 4));
			
			jQuery("#mc-scale-" + post_id).val(4 / 3);
			return false;
		},

		set_3_2: function(post_id) {
			var default_width = mc.default_width;
			var template = jQuery(window.parent.document.getElementById('page_template')).val();
			if ( template == 'letter.php' )
				default_width = 620;
			else if ( template == 'monocolumn.php' && default_width != mc.max_width )
				default_width = mc.max_width;
			
			if ( jQuery("#mc-preview-" + post_id).children('img').size() )
				default_width = parseInt(jQuery("#mc-snapshot-width-" + post_id).val());
			
			if ( !jQuery("#mc-width-" + post_id).val() )
				jQuery("#mc-width-" + post_id).val(default_width);
			jQuery("#mc-height-" + post_id).val(Math.ceil(parseInt(jQuery("#mc-width-" + post_id).val()) * 2 / 3));
			
			jQuery("#mc-scale-" + post_id).val(3 / 2);
			return false;
		},

		set_16_9: function(post_id) {
			var default_width = mc.default_width;
			var template = jQuery(window.parent.document.getElementById('page_template')).val();
			if ( template == 'letter.php' )
				default_width = 620;
			else if ( template == 'monocolumn.php' && default_width != mc.max_width )
				default_width = mc.max_width;
			
			if ( jQuery("#mc-preview-" + post_id).children('img').size() )
				default_width = parseInt(jQuery("#mc-snapshot-width-" + post_id).val());
			
			if ( !jQuery("#mc-width-" + post_id).val() )
				jQuery("#mc-width-" + post_id).val(default_width);
			jQuery("#mc-height-" + post_id).val(Math.ceil(parseInt(jQuery("#mc-width-" + post_id).val()) * 9 / 16));
			
			jQuery("#mc-scale-" + post_id).val(16 / 9);
			return false;
		},

		set_185_1: function(post_id) {
			var default_width = mc.default_width;
			var template = jQuery(window.parent.document.getElementById('page_template')).val();
			if ( template == 'letter.php' )
				default_width = 620;
			else if ( template == 'monocolumn.php' && default_width != mc.max_width )
				default_width = mc.max_width;
			
			if ( jQuery("#mc-preview-" + post_id).children('img').size() )
				default_width = parseInt(jQuery("#mc-snapshot-width-" + post_id).val());
			
			if ( !jQuery("#mc-width-" + post_id).val() )
				jQuery("#mc-width-" + post_id).val(default_width);
			jQuery("#mc-height-" + post_id).val(Math.ceil(parseInt(jQuery("#mc-width-" + post_id).val()) * 1 / 1.85));
			
			jQuery("#mc-scale-" + post_id).val(1.85);
			return false;
		},

		get_scale: function(post_id) {
			var width = parseInt(jQuery("#mc-width-" + post_id).val());
			var height = parseInt(jQuery("#mc-height-" + post_id).val());
			
			if ( width && height && width > 0 && height > 0 ) {
				if ( !jQuery("#mc-scale-" + post_id).val() )
					jQuery("#mc-scale-" + post_id).val(width / height);
			} else {
				var img = jQuery("#mc-preview-" + post_id).children('img:first');
				jQuery("#mc-scale-" + post_id).val('');
			
				if ( img.size() ) {
					var img_width = img.width();
					var img_height = img.height();
					if ( img_width && img_height )
						jQuery("#mc-scale-" + post_id).val(img_width / img_height);
				}
			}
			return false;
		},

		set_scale: function(elt, post_id) {
			if ( !jQuery(elt).val() ) {
				jQuery("#mc-width-" + post_id).val('');
				jQuery("#mc-height-" + post_id).val('');
				return false;
			}
			
			var scale = parseFloat(jQuery("#mc-scale-" + post_id).val());
			
			if ( !scale )
				return false;
			
			if ( jQuery(elt).is("#mc-width-" + post_id) ) {
				var width = parseInt(jQuery("#mc-width-" + post_id).val());
				var old_height = parseInt(jQuery("#mc-height-" + post_id).val());
				var new_height = Math.round(width / scale);
				if ( !old_height || Math.abs(new_height - old_height) > 1 )
					jQuery("#mc-height-" + post_id).val(new_height);
			} else {
				var height = jQuery("#mc-height-" + post_id).val();
				old_width = parseInt(jQuery("#mc-width-" + post_id).val());
				new_width = Math.round(height * scale);
				if ( !old_width || Math.abs(new_width - old_width) > 1 )
					jQuery("#mc-width-" + post_id).val(new_width);
			}

			return false;
		},
		
		new_snapshot: function(post_id, user_id, nonce) {
			var s_id, s_width, s_height, s_src, img, shot;
			
			if ( !jQuery("#mc-src-" + post_id).val() )
				return jQuery("#mc-src-" + post_id).focus();
			
			do {
				s_id = 'mc-snapshot-' + Math.floor(Math.random() * 10000);
			} while ( document.getElementById(s_id) );
			
			shot = jQuery("#mc-preview-" + post_id).children('img:first');
			if ( shot.size() && shot.width() ) {
				s_width = shot.width();
				s_height = shot.height();
			} else {
				s_width = parseInt(jQuery("#mc-snapshot-width-" + post_id).val());
				s_height = parseInt(jQuery("#mc-snapshot-height-" + post_id).val());
			}
			
			if ( !s_width || !s_height ) {
				s_width = 460;
				s_height = 345;
			} else if ( s_width > 460 ) {
				s_height = Math.round(s_height * 460 / s_width);
				s_width = 460;
			}
			
			jQuery("#mc-preview-" + post_id).css('width', s_width).parent().css('height', s_height);
			
			if ( mc.post_id || mc.post_id === 0 )
				mc.cancel_snapshot(mc.post_id);
			if ( mc.interval )
				clearInterval(mc.interval);
			mc.player = null;
			
			jQuery("#mc-preview-" + post_id).fadeOut('slow', function() {
				jQuery(this).html('<div style="width:' + s_width + 'px; height:' + s_height + 'px;"><div id="' + s_id + '"></div></div>');
				
				jQuery("#mc-new-snapshot-" + post_id).hide();
				jQuery("#mc-cancel-snapshot-" + post_id).show();

				var params = {};
				params.allowfullscreen = 'false';
				params.allowscriptaccess = 'true';

				var flashvars = {};
				flashvars.file = jQuery("#mc-src-" + post_id).val();
				flashvars.controlbar = 'over';
				flashvars.plugins = 'quickkeys-1,snapshot-1';
				flashvars['snapshot.script'] = mc.site_url + '/mc-snapshot.' + post_id + '.' + user_id + '.' + nonce + '.php';
				flashvars.id = s_id;

				var attributes = {};
				attributes.id = s_id;
				attributes.name = s_id;

				swfobject.embedSWF(mc.media_player, s_id, s_width, s_height, '9.0.0', false, flashvars, params, attributes);
				
				mc.post_id = post_id;
				mc.player = document.getElementById(s_id);
				mc.interval = setInterval('mc.take_snapshot();', 100);
			}).fadeIn('slow');
		},

		take_snapshot: function() {
			var p = mc.player,
				post_id = mc.post_id;
			
			if ( !p || typeof p.getConfig != 'function' )
				return;
			
			try {
				var img = p.getConfig()['image'];
				
				if ( !img )
					return;
				
				var img_attr = img.replace(/.*\?/, '').split('.');
				var img_id = parseInt(img_attr[0]);
				var new_width = parseInt(img_attr[1]);
				var new_height = parseInt(img_attr[2]);
				img = img.replace(/\?.*/, '');
			} catch ( err ) {
				return;
			}
			
			if ( mc.interval )
				clearInterval(mc.interval);
			mc.interval = null;
			mc.player = null;
			mc.post_id = null;
			
			jQuery("#mc-image-" + post_id).val(img);
			jQuery("#mc-image-id-" + post_id).val(img_id);
			jQuery("#mc-snapshot-src-" + post_id).val(img);
			jQuery("#mc-snapshot-width-" + post_id).val(new_width);
			jQuery("#mc-snapshot-height-" + post_id).val(new_height);
			
			mc.refresh_snapshot(post_id);
			
			jQuery("#mc-cancel-snapshot-" + post_id).hide();
			jQuery("#mc-new-snapshot-" + post_id).show();
		},

		cancel_snapshot: function(post_id) {
			if ( ( mc.post_id || mc.post_id === 0 ) && post_id != mc.post_id )
				mc.cancel_snapshot(mc.post_id);
			
			if ( mc.interval )
				clearInterval(mc.interval);
			mc.interval = null;
			mc.player = null;
			mc.post_id = null;
			
			mc.refresh_snapshot(post_id);
			
			jQuery("#mc-cancel-snapshot-" + post_id).hide();
			jQuery("#mc-new-snapshot-" + post_id).show();
		},

		change_snapshot: function(post_id) {
			mc.cancel_snapshot(post_id);
			
			var img = jQuery("#mc-preview-" + post_id).children('img:first');
			jQuery("#mc-scale-" + post_id).val('');
			
			if ( img.size() ) {
				var img_width = img.width();
				var img_height = img.height();
				
				if ( img_width && img_height ) {
					jQuery("#mc-scale-" + post_id).val(img_width / img_height);
					var width = parseInt(jQuery("#mc-width-" + post_id).val());
					var height = parseInt(jQuery("#mc-height-" + post_id).val());
					if ( height && width && Math.abs(height - Math.round(width * img_height / img_width)) > 1 )
						jQuery("#mc-height-" + post_id).val(Math.round(width * img_height / img_width));
				}
			}
		},
		
		refresh_snapshot: function(post_id) {
			var img_src = jQuery("#mc-image-" + post_id).val();
			var shot_src = jQuery("#mc-snapshot-src-" + post_id).val();
			
			if ( shot_src && !img_src )
				jQuery("#mc-image-" + post_id).val(shot_src);
			
			if ( shot_src && ( !img_src || img_src == shot_src ) )
				img_src = shot_src + '?' + jQuery("#mc-image-id-" + post_id).val();
			
			if ( !img_src ) {
				jQuery("#mc-preview-" + post_id).fadeOut('slow', function() {
					mc.clear_snapshot(post_id)
				}).fadeIn('slow');
				return;
			}
			
			var s_width = jQuery("#mc-preview-" + post_id).width();
			var s_height = jQuery("#mc-preview-" + post_id).height();
			
			if ( s_width && s_height )
				jQuery("#mc-preview-" + post_id).css('width', s_width).parent().css('height', s_height);
			else
				jQuery("#mc-preview-" + post_id).css('width', '').parent().css('height', 0);
			
			var img = new Image;
			jQuery(img).load(function() {
				var shot, s_width, s_height;
				
				shot = jQuery(this);
				
				s_width = shot.width();
				s_height = shot.height();
				
				if ( !s_width || !s_height ) {
					jQuery("#mc-preview-" + post_id).fadeOut('slow', function() {
						mc.clear_snapshot(post_id);
					}).show();
					return;
				}
				
				jQuery("#mc-scale-" + post_id).val(s_width / s_height);
				
				var width = parseInt(jQuery("#mc-width-" + post_id).val());
				var height = parseInt(jQuery("#mc-height-" + post_id).val());
				if ( width && height && Math.abs(height - Math.round(width * s_height / s_width)) > 1 )
					jQuery("#mc-height-" + post_id).val(Math.round(width * s_height / s_width));
				
				jQuery("#mc-snapshot-size-" + post_id ).text('(' + s_width + ' x ' + s_height + ')');
				
				if ( s_width > 460 ) {
					s_height = Math.round(s_height * 460 / s_width);
					s_width = 460;
					shot.attr('width', s_width);
				}
				
				jQuery("#mc-preview-" + post_id).css('width', s_width).parent().css('height', '');
			}).error(function() {
				return mc.clear_snapshot(post_id);
			});
			
			jQuery("#mc-preview-" + post_id).fadeOut('slow', function() {
				jQuery(img).attr('src', img_src);
				jQuery(this).html(img);
			}).fadeIn('slow');
		},
		
		clear_snapshot: function(post_id) {
			jQuery("#mc-scale-" + post_id).val('');
			jQuery("#mc-snapshot-size-" + post_id ).text('');
			jQuery("#mc-preview-" + post_id).html('').css('width', '').parent().css('height', '');
		},
		
		change_thumbnail: function(post_id) {
			var img_src = jQuery("#mc-thumbnail-" + post_id).val();
			
			if ( !img_src ) {
				jQuery("#mc-thumbnail-preview-" + post_id).fadeOut('slow', function() {
					jQuery(this).html('').css('width', '').parent().css('height', '');
				}).fadeIn('slow');
				return;
			}
			
			var s_width = jQuery("#mc-thumbnail-preview-" + post_id).width();
			var s_height = jQuery("#mc-thumbnail-preview-" + post_id).height();
			
			if ( s_width && s_height )
				jQuery("#mc-thumbnail-preview-" + post_id).css('width', s_width).parent().css('height', s_height);
			else
				jQuery("#mc-thumbnail-preview-" + post_id).css('width', '').parent().css('height', 0);
			
			var img = new Image;
			jQuery(img).load(function() {
				var shot, s_width, s_height;
				
				shot = jQuery(this);
				
				s_width = shot.width();
				s_height = shot.height();
				
				if ( !s_width || !s_height ) {
					jQuery("#mc-thumbnail-preview-" + post_id).fadeOut('slow', function() {
						jQuery(this).html('').css('width', '').parent().css('height', '');
					}).show();
					return;
				}
				
				if ( s_width > 460 ) {
					s_height = Math.round(s_height * 460 / s_width);
					s_width = 460;
					shot.attr('width', s_width);
				}
				
				jQuery("#mc-thumbnail-preview-" + post_id).css('width', s_width).parent().css('height', '');
			}).error(function() {
				jQuery(this).html('').css('width', '').parent().css('height', '');
				return;
			});
			
			jQuery("#mc-thumbnail-preview-" + post_id).fadeOut('slow', function() {
				jQuery(img).attr('src', img_src);
				jQuery(this).html(img);
			}).fadeIn('slow');
		}
	};
	
	window.mc = mc;
	
	jQuery('#media-items table').hover(function() {
		jQuery('#media-items').sortable('disable');
	}, function() {
		jQuery('#media-items').sortable('enable');
	});
});

