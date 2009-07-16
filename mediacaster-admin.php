<?php
/**
 * mediacaster_admin
 *
 * @package Mediacaster
 **/

add_action('save_post', array('mediacaster_admin', 'save_entry'));
add_action('add_attachment', array('mediacaster_admin', 'save_attachment'));
add_action('edit_attachment', array('mediacaster_admin', 'save_attachment'));

add_action('settings_page_mediacaster', array('mediacaster_admin', 'save_options'), 0);

add_filter('attachment_fields_to_edit', array('mediacaster_admin', 'attachment_fields_to_edit'), 20, 2);
add_filter('attachment_fields_to_save', array('mediacaster_admin', 'attachment_fields_to_save'), 20, 2);
add_filter('media_send_to_editor', array('mediacaster_admin', 'media_send_to_editor'), 20, 3);

add_filter('type_url_form_audio', array('mediacaster_admin', 'type_url_form_audio'));
add_filter('audio_send_to_editor_url', array('mediacaster_admin', 'audio_send_to_editor_url'), 10, 3);

add_filter('type_url_form_video', array('mediacaster_admin', 'type_url_form_video'));
add_filter('video_send_to_editor_url', array('mediacaster_admin', 'video_send_to_editor_url'), 10, 3);

add_filter('type_url_form_file', array('mediacaster_admin', 'type_url_form_file'));
add_filter('file_send_to_editor_url', array('mediacaster_admin', 'file_send_to_editor_url'), 10, 3);

class mediacaster_admin {
	/**
	 * save_attachment()
	 *
	 * @return void
	 **/

	function save_attachment($post_id) {
		$post_id = (int) $post_id;
		if ( !$post_id || !isset($_POST['attachments'][$post_id]['image']) )
			return;
		
		$attachment = $_POST['attachments'][$post_id];
		
		$image = trim(strip_tags($attachment['image']));
		
		if ( $image ) {
			$image = addslashes(esc_url_raw($image));
			update_post_meta($post_id, '_mc_image', $image);
		} else {
			delete_post_meta($post_id, '_mc_image');
		}
		
		foreach ( array('width', 'height') as $arg ) {
			$val = intval($attachment[$arg]);
			if ( $val )
				update_post_meta($post_id, '_mc_' . $arg, $val);
			else
				delete_post_meta($post_id, '_mc_' . $arg);
		}
	} # save_attachment()
	
	
	/**
	 * save_entry()
	 *
	 * @param int $post_id
	 * @return void
	 **/

	function save_entry($post_id) {
		if ( !$_POST || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id) )
			return;
		
		delete_post_meta($post_id, '_mc_enclosures');
		delete_post_meta($post_id, '_mc_enclosed');
	} # save_entry()
	
	
	/**
	 * save_options()
	 *
	 * @return void
	 **/

	function save_options() {
		if ( !$_POST )
			return;
		
		check_admin_referer('mediacaster');
		
		$old_ops = get_option('mediacaster');
		$cover = $old_ops['player']['cover'];
		
		if ( isset($_POST['delete_cover']) && $cover ) {
			if ( file_exists(WP_CONTENT_DIR . $cover) )
				@unlink(WP_CONTENT_DIR . $cover);
			$cover = false;
		}

		if ( @ $_FILES['new_cover']['name'] ) {
			$name = $_FILES['new_cover']['name'];
			$tmp_name = $_FILES['new_cover']['tmp_name'];
			
			$name = strip_tags(stripslashes($name));

			preg_match("/\.(jpg|jpeg|png)$/i", $name, $ext);
			$ext = strtolower(end($ext));
			
			if ( !in_array($ext, array('jpg', 'jpeg', 'png')) ) {
				echo '<div class="error">'
					. "<p>"
						. "<strong>"
						. __('Invalid Cover File.', 'mediacaster')
						. "</strong>"
					. "</p>\n"
					. "</div>\n";
			} else {
				if ( $cover && file_exists(WP_CONTENT_DIR . $cover) )
					@unlink(WP_CONTENT_DIR . $cover);
				
				$entropy = intval(get_option('sem_entropy')) + 1;
				update_option('sem_entropy', $entropy);
				
				$cover = '/cover-' . $entropy . '.' . $ext;
				
				$new_name = WP_CONTENT_DIR . $cover;
				
				@move_uploaded_file($tmp_name, $new_name);
				$stat = stat(dirname($new_name));
				$perms = $stat['mode'] & 0000666;
				@chmod($new_name, $perms);
			}
		}
		
		$new_ops = $_POST['mediacaster'];
		
		$player = array();
		$player['position'] = in_array($new_ops['player']['position'], array('top', 'bottom', 'none'))
			? $new_ops['player']['position']
			: 'top';
		$player['skin'] = in_array($new_ops['player']['skin'], array('bekle', 'kleur', 'metarby10', 'modieus', 'silverywhite'))
			? $new_ops['player']['skin']
			: 'modius';
		$player['cover'] = $cover;
		$player['autostart'] = isset($new_ops['player']['autostart']);
		
		$itunes = array();
		foreach ( array('author', 'summary', 'copyright') as $var )
			$itunes[$var] = stripslashes(strip_tags($new_ops['itunes'][$var]));
		for ( $i = 1; $i <= 3; $i++ )
			$itunes['category'][$i] = stripslashes(strip_tags($new_ops['itunes']['category'][$i]));
		$itunes['explicit'] = in_array($new_ops['itunes']['explicit'], array('yes', 'no', 'clean'))
			? $new_ops['itunes']['explicit']
			: 'no';
		$itunes['block'] = in_array($new_ops['itunes']['block'], array('yes', 'no'))
			? $new_ops['itunes']['block']
			: 'no';
		
		$longtail = array(
			'licensed' => false,
			'pub_id' => '',
			'ad_flow' => array(),
			);
		
		$options = compact('player', 'itunes', 'longtail');
		update_option('mediacaster', $options);
		
		echo '<div class="updated fade">' . "\n"
			. '<p>'
				. '<strong>'
				. __('Settings saved.', 'mediacaster')
				. '</strong>'
			. '</p>' . "\n"
			. '</div>' . "\n";
	} # save_options()


	/**
	 * edit_options()
	 *
	 * @return void
	 **/

	function edit_options() {
		$options = get_option('mediacaster');
		
		echo '<div class="wrap">' . "\n";
		
		echo '<form enctype="multipart/form-data" method="post" action="">' . "\n";

		$bytes = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );

		echo '<input type="hidden" name="MAX_FILE_SIZE" value="' . esc_attr($bytes) .'" />' . "\n";

		wp_nonce_field('mediacaster');
		
		screen_icon();
		
		echo '<h2>'. __('Mediacaster Settings', 'mediacaster') . '</h2>' . "\n";
		
		echo '<h3>'
				. __('Media Player', 'mediacaster')
				. '</h3>' . "\n";

		echo '<table class="form-table">';
		
		echo '<tr valign="top">'
			. '<th scope="row">'
			. __('Player Skin', 'mediacaster')
			. '</th>'
			. '<td>'
			. '<ul>' . "\n";
		
		foreach ( array(
			'bekle' => __('Bekle', 'mediacaster'),
			'kleur' => __('Kleur', 'mediacaster'),
			'modieus' => __('Modieus', 'mediacaster'),
			) as $skin_id => $skin_name ) {
			echo '<li>'
				. '<label>'
				. '<input type="radio" name="mediacaster[player][skin]"'
					. ' value="' . $skin_id . '"'
					. checked($options['player']['skin'], $skin_id, false)
					. ' />'
				. '&nbsp;'
				. $skin_name
				. '</label>'
				. '</li>' . "\n";
		}
		
		echo '</ul>' . "\n"
			. '</td>'
			. '</tr>' . "\n";
		
		echo '<tr valign="top">'
			. '<th scope="row">'
			. __('MP3 Playlist Position', 'mediacaster')
			. '</th>'
			. '<td>'
			. '<label for="mediacaster-player-position-top">'
			. '<input type="radio"'
				. ' id="mediacaster-player-position-top" name="mediacaster[player][position]"'
				. ' value="top"'
				. ( $options['player']['position'] == 'top'
					? ' checked="checked"'
					: ''
					)
				. ' />'
			. '&nbsp;'
			. __('Top', 'mediacaster')
			. '</label>'
			. ' &nbsp; '
			. '<label for="mediacaster-player-position-bottom">'
			. '<input type="radio"'
				. ' id="mediacaster-player-position-bottom" name="mediacaster[player][position]"'
				. ' value="bottom"'
				. ( $options['player']['position'] == 'bottom'
					? ' checked="checked"'
					: ''
					)
				. ' />'
			. '&nbsp;'
			. __('Bottom', 'mediacaster')
			. '</label>'
			. ' &nbsp; '
			. '<label for="mediacaster-player-position-none">'
			. '<input type="radio"'
				. ' id="mediacaster-player-position-none" name="mediacaster[player][position]"'
				. ' value="none"'
				. ( $options['player']['position'] == 'none'
					? ' checked="checked"'
					: ''
					)
				. ' />'
			. '&nbsp;'
			. __('None (manual inserts only)', 'mediacaster')
			. '</label>'
			. '</td>'
			. '</tr>' . "\n";
		
		echo '<tr valign="top">'
			. '<th scope="row">'
			. __('MP3 Playlist Behavior', 'mediacaster')
			. '</th>'
			. '<td>'
			. '<label>'
			. '<input type="checkbox"'
				. ' name="mediacaster[player][autostart]"'
				. ( $options['player']['autostart']
					? ' checked="checked"'
					: ''
					)
				. ' />'
			. '&nbsp;'
			. __('Automatically start the (first) playlist.', 'mediacaster')
			. '</label>'
			. '</td>'
			. '</tr>' . "\n";
		
		$cover = $options['player']['cover'];

		echo '<tr valign="top">'
			. '<th scope="row">'
				. __('MP3 Playlist Cover', 'mediacaster')
			. '</th>' . "\n"
			. '<td>';
		
		if ( $cover && !is_file(WP_CONTENT_DIR . $cover) ) {
			$options['player']['cover'] = false;
			update_option('mediacaster', $options);
			$cover = false;
		}
			
		
		if ( $cover ) {
			echo '<div style="margin-botton: 6px;">';
			
			echo '<img src="' . esc_url(WP_CONTENT_URL . $cover) . '" />' . "\n"
				. '<br />' . "\n";
				
			if ( is_writable(WP_CONTENT_DIR . $cover) ) {
				echo '<label for="delete_cover">'
					. '<input type="checkbox"'
						. ' id="delete_cover" name="delete_cover"'
						. ' style="text-align: left; width: auto;"'
						. ' />'
					. '&nbsp;'
					. __('Delete', 'mediacaster')
					. '</label>';
			} else {
				echo __('Your cover is not writable by the server.', 'mediacaster');
			}
			
			echo '</div>';
		}

		echo '<div style="margin-botton: 6px;">'
			. '<label for="new_cover">'
				. __('New Image (jpg or png)', 'mediacaster') . ':'
			. '</label>'
			. '<br />' . "\n"
			. '<input type="file" id="new_cover" name="new_cover" />'
			. '</div>' . "\n";
		
		echo '</td>'
			. '</tr>';
		
		echo '</table>';

		echo '<p class="submit">'
			. '<input type="submit"'
				. ' value="' . esc_attr(__('Save Changes', 'mediacaster')) . '"'
				. ' />'
			. '</p>' . "\n";


		echo '<h3>'
			. __('iTunes', 'mediacaster')
			. '</h3>' . "\n";

		echo '<table class="form-table">';
		
		echo '<tr valign="top">'
			. '<th scope="row">'
			. '<label for="mediacaster-itunes-author">'
				. __('Author', 'mediacaster')
				. '</label>'
			. '</th>'
			. '<td>'
			. '<input type="text" class="widefat"'
				. ' id="mediacaster-itunes-author" name="mediacaster[itunes][author]"'
				. ' value="' . esc_attr($options['itunes']['author']) . '"'
				. ' />' . "\n"
			. '</td>'
			. '</tr>' . "\n";


		echo '<tr valign="top">'
			. '<th scope="row">'
			. '<label for="mediacaster-itunes-summary">'
				. __('Summary', 'mediacaster')
				. '</label>'
			. '</th>'
			. '<td>'
			. '<textarea class="widefat" cols="58" rows="3"'
				. ' id="mediacaster-itunes-summary" name="mediacaster[itunes][summary]"'
				. ' >' . "\n"
				. esc_html($options['itunes']['summary'])
				. '</textarea>' . "\n"
			. '</td>'
			. '</tr>' . "\n";


		echo '<tr valign="top">'
			. '<th scope="row">'
				. __('Categories', 'mediacaster')
			. '</th>'
			. '<td>';

		for ( $i = 1; $i <= 3; $i++ ) {
			echo '<select name="mediacaster[itunes][category][' . $i . ']">' . "\n"
				. '<option value="">' . __('- Select -', 'mediacaster') . '</option>' . "\n";

			foreach ( mediacaster_admin::get_itunes_categories() as $key => $category ) {
				$category = $category;

				echo '<option'
					. ' value="' . esc_attr($key) . '"'
					. ( ( $key == $options['itunes']['category'][$i] )
						? ' selected="selected"'
						: ''
						)
					. '>'
					. esc_html($category)
					. '</option>' . "\n";
			} echo '</select>'
			 	. '<br />'. "\n";
		}

		echo '</td>'
		 	. '</tr>' . "\n";
		

		echo '<tr valign="top">'
			. '<th scope="row">'
				. __('Explicit', 'mediacaster')
			. '</th>'
			. '<td>';

		foreach ( array(
			'yes' => __('Yes', 'mediacaster'),
			'no' => __('No', 'mediacaster'),
			'clean' => __('Clean', 'mediacaster'),
			) as $key => $answer ) {
			echo '<label>'
				. '<input type="radio" name="mediacaster[itunes][explicit]"'
				. ' value="' . esc_attr($key) . '"'
				. ( ( $key == $options['itunes']['explicit'] )
					? ' checked="checked"'
					: ''
					)
				. '>'
				. '&nbsp;'
				. $answer
				. '</label>' . " &nbsp; \n";
		}

		echo '</td>'
			. '</tr>' . "\n";


		echo '<tr valign="top">'
			. '<th scope="row">'
				. __('Block iTunes', 'mediacaster')
			. '</th>'
			. '<td>';

		foreach ( array(
			'yes' => __('Yes', 'mediacaster'),
			'no' => __('No', 'mediacaster'),
			) as $key => $answer ) {
			echo '<label>'
				. '<input type="radio" name="mediacaster[itunes][block]"'
				. ' value="' . esc_attr($key) . '"'
				. ( ( $key == $options['itunes']['block'] )
					? ' checked="checked"'
					: ''
					)
				. '>'
				. '&nbsp;'
				. $answer
				. '</label>' . " &nbsp; \n";
		}

		echo '</td>'
			. '</tr>' . "\n";

		echo '<tr valign="top">'
			. '<th scope="row">'
			. '<label for="mediacaster-itunes-copyright">'
				. __('Copyright', 'mediacaster')
				. '</label>'
			. '</th>'
			. '<td>'
			. '<textarea class="widefat" cols="58" rows="2"'
				. ' id="mediacaster-itunes-copyright" name="mediacaster[itunes][copyright]"'
				. ' >' . "\n"
				. esc_html($options['itunes']['copyright'])
				. '</textarea>' . "\n"
			. '</td>'
			. '</tr>' . "\n";
			
		echo '</table>';

		echo '<p class="submit">'
			. '<input type="submit"'
				. ' value="' . esc_attr(__('Save Changes', 'mediacaster')) . '"'
				. ' />'
			. '</p>' . "\n";
		
		echo '</form>' . "\n"
			. '</div>' . "\n";
	} # edit_options()


	/**
	 * get_itunes_categories()
	 *
	 * @return void
	 **/

	function get_itunes_categories() {
		return array(
			'Arts' => __('Arts', 'mediacaster'),
			'Arts / Design' => __('Arts / Design', 'mediacaster'),
			'Arts / Fashion & Beauty' => __('Arts / Fashion & Beauty', 'mediacaster'),
			'Arts / Food' => __('Arts / Food', 'mediacaster'),
			'Arts / Literature' => __('Arts / Literature', 'mediacaster'),
			'Arts / Performing Arts' => __('Arts / Performing Arts', 'mediacaster'),
			'Arts / Visual Arts' => __('Arts / Visual Arts', 'mediacaster'),

			'Business' => __('Business', 'mediacaster'),
			'Business / Business News' => __('Business / Business News', 'mediacaster'),
			'Business / Careers' => __('Business / Careers', 'mediacaster'),
			'Business / Investing' => __('Business / Investing', 'mediacaster'),
			'Business / Management & Marketing' => __('Business / Management & Marketing', 'mediacaster'),
			'Business / Shopping' => __('Business / Shopping', 'mediacaster'),

			'Comedy' => __('Comedy', 'mediacaster'),

			'Education' => __('Education', 'mediacaster'),
			'Education / Education Technology' => __('Education / Education Technology', 'mediacaster'),
			'Education / Higher Education' => __('Education / Higher Education', 'mediacaster'),
			'Education / K-12' => __('Education / K-12', 'mediacaster'),
			'Education / Language Courses' => __('Education / Language Courses', 'mediacaster'),
			'Education / Training' => __('Education / Training', 'mediacaster'),

			'Games & Hobbies' => __('Games & Hobbies', 'mediacaster'),
			'Games & Hobbies / Automotive' => __('Games & Hobbies / Automotive', 'mediacaster'),
			'Games & Hobbies / Aviation' => __('Games & Hobbies / Aviation', 'mediacaster'),
			'Games & Hobbies / Hobbies' => __('Games & Hobbies / Hobbies', 'mediacaster'),
			'Games & Hobbies / Other Games' => __('Games & Hobbies / Other Games', 'mediacaster'),
			'Games & Hobbies / Video Games' => __('Games & Hobbies / Video Games', 'mediacaster'),

			'Government & Organizations' => __('Government & Organizations', 'mediacaster'),
			'Government & Organizations / Local' => __('Government & Organizations / Local', 'mediacaster'),
			'Government & Organizations / National' => __('Government & Organizations / National', 'mediacaster'),
			'Government & Organizations / Non-Profit' => __('Government & Organizations / Non-Profit', 'mediacaster'),
			'Government & Organizations / Regional' => __('Government & Organizations / Regional', 'mediacaster'),

			'Health' => __('Health', 'mediacaster'),
			'Health / Alternative Health' => __('Health / Alternative Health', 'mediacaster'),
			'Health / Fitness & Nutrition' => __('Health / Fitness & Nutrition', 'mediacaster'),
			'Health / Self-Help' => __('Health / Self-Help', 'mediacaster'),
			'Health / Sexuality' => __('Health / Sexuality', 'mediacaster'),

			'Kids & Family' => __('Kids & Family', 'mediacaster'),

			'Music' => __('Music', 'mediacaster'),

			'News & Politics' => __('News & Politics', 'mediacaster'),

			'Religion & Spirituality' => __('Religion & Spirituality', 'mediacaster'),
			'Religion & Spirituality / Buddhism' => __('Religion & Spirituality / Buddhism', 'mediacaster'),
			'Religion & Spirituality / Christianity' => __('Religion & Spirituality / Christianity', 'mediacaster'),
			'Religion & Spirituality / Hinduism' => __('Religion & Spirituality / Hinduism', 'mediacaster'),
			'Religion & Spirituality / Islam' => __('Religion & Spirituality / Islam', 'mediacaster'),
			'Religion & Spirituality / Judaism' => __('Religion & Spirituality / Judaism', 'mediacaster'),
			'Religion & Spirituality / Other' => __('Religion & Spirituality / Other', 'mediacaster'),
			'Religion & Spirituality / Spirituality' => __('Religion & Spirituality / Spirituality', 'mediacaster'),

			'Science & Medicine' => __('Science & Medicine', 'mediacaster'),
			'Science & Medicine / Medicine' => __('Science & Medicine / Medicine', 'mediacaster'),
			'Science & Medicine / Natural Sciences' => __('Science & Medicine / Natural Sciences', 'mediacaster'),
			'Science & Medicine / Social Sciences' => __('Science & Medicine / Social Sciences', 'mediacaster'),

			'Society & Culture' => __('Society & Culture', 'mediacaster'),
			'Society & Culture / History' => __('Society & Culture / History', 'mediacaster'),
			'Society & Culture / Personal Journals' => __('Society & Culture / Personal Journals', 'mediacaster'),
			'Society & Culture / Philosophy' => __('Society & Culture / Philosophy', 'mediacaster'),
			'Society & Culture / Places & Travel' => __('Society & Culture / Places & Travel', 'mediacaster'),

			'Sports & Recreation' => __('Sports & Recreation', 'mediacaster'),
			'Sports & Recreation / Amateur' => __('Sports & Recreation / Amateur', 'mediacaster'),
			'Sports & Recreation / College & High School' => __('Sports & Recreation / College & High School', 'mediacaster'),
			'Sports & Recreation / Outdoor' => __('Sports & Recreation / Outdoor', 'mediacaster'),
			'Sports & Recreation / Professional' => __('Sports & Recreation / Professional', 'mediacaster'),

			'Technology' => __('Technology', 'mediacaster'),
			'Technology / Gadgets' => __('Technology / Gadgets', 'mediacaster'),
			'Technology / Tech News' => __('Technology / Tech News', 'mediacaster'),
			'Technology / Podcasting' => __('Technology / Podcasting', 'mediacaster'),
			'Technology / Software How-To' => __('Technology / Software How-To', 'mediacaster'),

			'TV & Film' => __('TV & Film', 'mediacaster'),
		);
	} # get_itunes_categories()
	
	
	/**
	 * attachment_fields_to_edit()
	 *
	 * @param array $post_fields
	 * @param object $post
	 * @return array $post_fields
	 **/

	function attachment_fields_to_edit($post_fields, $post) {
		$file_url = wp_get_attachment_url($post->ID);
		if ( !preg_match("/\.([a-z0-9]+)$/i", $file_url, $ext) )
			return $post_fields;
		$ext = esc_attr(strtolower(end($ext)));
		
		global $content_width;
		$o = get_option('mediacaster');
		$default_width = $content_width ? intval($content_width) : 420;
		$default_height = round($default_width * 9 / 16);
		
		switch ( $post->post_mime_type ) {
		case 'video/mpeg':
		case 'video/mp4':
		case 'video/x-flv':
		case 'video/quicktime':
			if ( !in_array($ext, mediacaster::get_extensions('video')) )
				break;
			
			static $scripts;
			if ( !isset($scripts) ) {
				$scripts = <<<EOS
<script type="text/javascript">
var mc = {
	i: 0,
	
	set_default: function(post_id) {
		jQuery("#attachments-width-" + post_id).val('');
		jQuery("#attachments-height-" + post_id).val('');
		
		return false;
	},

	set_16_9: function(post_id) {
		if ( !jQuery("#attachments-width-" + post_id).val() )
			jQuery("#attachments-width-" + post_id).val($default_width);
		jQuery("#attachments-height-" + post_id).val(Math.round(jQuery("#attachments-width-" + post_id).val() * 9 / 16));
		return false;
	},

	set_4_3: function(post_id) {
		if ( !jQuery("#attachments-width-" + post_id).val() )
			jQuery("#attachments-width-" + post_id).val($default_width);
		jQuery("#attachments-height-" + post_id).val(Math.round(jQuery("#attachments-width-" + post_id).val() * 3 / 4));
		return false;
	}
};
</script>
EOS;
			} else {
				$scripts = false;
			}
			
			$width = get_post_meta($post->ID, '_mc_width', true);
			$width = $width ? (int) $width : '';
			
			$height = get_post_meta($post->ID, '_mc_height', true);
			$height = $height ? (int) $height : '';
			
			$post_fields['format'] = array(
				'label' => __('Width x Height', 'mediacaster'),
				'input' => 'html',
				'html' => $scripts . '<input id="attachments-width-' . $post->ID . '" name="attachments[' . $post->ID . '][width]" value="' . $width . '" type="text" size="3" style="width: 40px;"> x <input id="attachments-height-' . $post->ID . '" name="attachments[' . $post->ID . '][height]" value="' . $height . '" type="text" size="3" style="width: 40px;">
	<button type="button" class="button" onclick="return mc.set_default(' . $post->ID . ');">' . __('Default', 'mediacaster') . '</button>
	<button type="button" class="button" onclick="return mc.set_16_9(' . $post->ID . ');">' . __('16/9', 'mediacaster') . '</button>
	<button type="button" class="button" onclick="return mc.set_4_3(' . $post->ID . ');">' . __('4/3', 'mediacaster') . '</button>',
				);
			
			$image = get_post_meta($post->ID, '_mc_image', true);
			$image = $image ? esc_url($image) : '';
			
			$post_fields['image'] = array(
				'label' => __('Preview Image', 'mediacaster'),
				'value' => $image,
				'helps' => __('The URL of a preview image when the video isn\'t playing.', 'mediacaster'),
				);
			
			$post_fields['autostart'] = array(
				'label' => __('Autostart', 'mediacaster'),
				'input' => 'html',
				'html' => '<label style="font-weight: normal"><input type="checkbox" name="attachments[' . $post->ID . '][autostart]">&nbsp;' . __('Automatically start the (first) video (NB: bandwidth intensive).', 'mediacaster') . '</label>',
				);
			
			$post_fields['thickbox'] = array(
				'label' => __('Thickbox', 'mediacaster'),
				'input' => 'html',
				'html' => '<label style="font-weight: normal"><input type="checkbox" name="attachments[' . $post->ID . '][thickbox]">&nbsp;' . __('Open the video in a thickbox window (requires a preview image).', 'mediacaster') . '</label>',
				);
		}
		
		switch ( $post->post_mime_type ) {
		case 'audio/mpeg':
		case 'audio/mp3':
		case 'audio/aac':
		case 'video/mpeg':
		case 'video/mp4':
		case 'video/x-flv':
		case 'video/quicktime':
		case 'video/3gpp':
			if ( !in_array($ext, mediacaster::get_extensions()) )
				break;
			unset($post_fields['post_excerpt']);
			$post_fields['url']['html'] = preg_split("/<br/", $post_fields['url']['html']);
			$post_fields['url']['html'] = $post_fields['url']['html'][0];
			$bad_urls = array();
			$bad_urls[] = $file_url;
			$bad_urls[] = get_permalink($post->ID);
			if ( $post->post_parent )
				$bad_urls[] = get_permalink($post->post_parent);
			foreach ( $bad_urls as $k => $bad_url )
				$bad_urls[$k] = " value='" . esc_url($bad_url) . "'";
			$post_fields['url']['html'] = str_replace($bad_urls, " value=''", $post_fields['url']['html']);
			$post_fields['url']['helps'] = __('The link URL to which the player should direct users to (e.g. an affiliate link).', 'mediacaster');
			
			if ( !isset($post_fields['autostart']) ) {
				$post_fields['autostart'] = array(
					'label' => __('Autostart', 'mediacaster'),
					'input' => 'html',
					'html' => '<label style="font-weight: normal"><input type="checkbox" name="attachments[' . $post->ID . '][autostart]">&nbsp;' . __('Automatically start the (first) podcast (NB: bandwidth intensive).', 'mediacaster') . '</label>',
					);
			}
			break;
		
		default:
			if ( !preg_match("/^(?:application|text)\//", $post->post_mime_type) )
				break;
			unset($post_fields['post_excerpt']);
			unset($post_fields['url']);
		}
		
		return $post_fields;
	} # attachment_fields_to_edit()
	
	
	/**
	 * attachment_fields_to_save()
	 *
	 * @param array $post
	 * @param array $attachment
	 * @return array $post
	 **/

	function attachment_fields_to_save($post, $attachment) {
		foreach ( array('width', 'height', 'image') as $var ) {
			if ( isset($attachment[$var]) )
				$post[$var] = $attachment[$var];
		}
		
		return $post;
	} # attachment_fields_to_save()
	
	
	/**
	 * media_send_to_editor()
	 *
	 * @param string $html
	 * @param int $send_id
	 * @param array $attachment
	 * @return string $html
	 **/

	function media_send_to_editor($html, $send_id, $attachment) {
		if ( preg_match("/^\[/", $html) )
			return $html;
		
		$send_id = intval($send_id);
		$post = get_post($send_id);
		
		$file_url = wp_get_attachment_url($post->ID);
		if ( !preg_match("/\.([a-z0-9]+)$/i", $file_url, $ext) )
			return $html;
		$ext = strtolower(end($ext));
		
		$autostart = !empty($attachment['autostart'])
			? ' autostart'
			: '';
		
		$link = trim(stripslashes($attachment['url']));
		$link = $link
			? ( ' link="' . esc_url_raw($link) . '"' )
			: '';
		
		switch ( $post->post_mime_type ) {
		case 'audio/mpeg':
		case 'audio/mp3':
		case 'audio/aac':
			if ( !preg_match("/\b(?:" . implode('|', mediacaster::get_extensions('audio')) . ")\b/i", $file_url) )
				break;
			
			$html = '[mc id="' . $send_id . '" type="audio"' . $link . $autostart . ']'
			 	. $attachment['post_title']
			 	. '[/mc]';
			break;
		
		case 'video/mpeg':
		case 'video/mp4':
		case 'video/x-flv':
		case 'video/quicktime':
		case 'video/3gpp':
			if ( !preg_match("/\b(?:" . implode('|', mediacaster::get_extensions('video')) . ")\b/i", $file_url) )
				break;
			
			$width = intval($attachment['width']);
			$width = $width
				? ( ' width="' . $width . '"' )
				: '';
			
			$height = intval($attachment['height']);
			$height = $height
				? ( ' height="' . $height . '"' )
				: '';
			
			$image = trim(stripslashes($attachment['image']));
			$image = $image
				? ( ' image="' . esc_url_raw($image) . '"' )
				: '';
			
			$thickbox = !empty($attachment['thickbox'])
				? ' thickbox'
				: '';
			
			$html = '[mc id="' . $send_id . '"' . $width . $height . ' type="video"' . $autostart . $thickbox . $link . $image . ']'
				. $attachment['post_title']
				. '[/mc]';
			break;
		
		default:
			if ( !preg_match("/^(?:application|text)\//", $post->post_mime_type) )
				break;
			
			$html = '[mc id="' . $send_id . '" type="file"]'
				. $attachment['post_title']
				. '[/mc]';
		}
		
		return $html;
	} # media_send_to_editor()
	
	
	/**
	 * type_url_form_audio()
	 *
	 * @param string $html
	 * @return string $html
	 **/

	function type_url_form_audio($html) {
		return '
			<table class="describe"><tbody>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[href]">' . __('Audio File URL', 'mediacaster') . '</label></span>
						<span class="alignright"><abbr title="required" class="required">*</abbr></span>
					</th>
					<td class="field"><input id="insertonly[href]" name="insertonly[href]" value="" type="text" aria-required="true"></td>
				</tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[title]">' . __('Title', 'mediacaster') . '</label></span>
					</th>
					<td class="field"><input id="insertonly[title]" name="insertonly[title]" value="" type="text"></td>
				</tr>
				<tr><td></td><td class="help">' . __('Link text, e.g. &#8220;Still Alive by Jonathan Coulton&#8221;', 'mediacaster') . '</td></tr>
				<tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[url]">' . __('Link URL', 'mediacaster') . '</label></span>
					</th>
					<td class="field"><input id="insertonly[url]" name="insertonly[url]" value="" type="text"></td>
				</tr>
				<tr><td></td><td class="help">' . __('The link URL to which the player should direct users to (e.g. an affiliate link). Only applicable for mp3, m4a and aac files.', 'mediacaster') . '</td></tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[autostart]">' . __('Autostart', 'mediacaster') . '</label></span>
					</th>
					<td class="field"><label style="font-weight: normal"><input type="checkbox" name="insertonly[autostart]">&nbsp;' . __('Automatically start the (first) podcast (NB: bandwidth intensive).', 'mediacaster') . '</label></td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input type="submit" class="button" name="insertonlybutton" value="' . esc_attr(__('Insert into Post', 'mediacaster')) . '" />
					</td>
				</tr>
			</tbody></table>
		';
	} # type_url_form_audio()
	
	
	/**
	 * audio_send_to_editor_url()
	 *
	 * @param string $html
	 * @param string $src
	 * @param string $title
	 * @return string $html
	 **/

	function audio_send_to_editor_url($html, $src, $title) {
		$title = stripslashes($_POST['insertonly']['title']);
		$src = esc_url_raw(stripslashes($_POST['insertonly']['href']));
		
		if ( !$title )
			$title = basename($src);
		
		if ( preg_match("/\b(" . implode('|', mediacaster::get_extensions('audio')) . "|rss2?|xml|feed|atom)\b/i", $src) ) {
			$link = trim(stripslashes($_POST['insertonly']['url']));
			$link = $link ? ( ' link="' . esc_url_raw($link) . '"' ) : '';
			$autostart = isset($_POST['insertonly']['autostart'])
				? ' autostart'
				: '';
			$html = '[mc src="' . $src . '" type="audio"' . $autostart . $link . ']'
				. $title
				. '[/mc]';
		}
		
		return $html;
	} # audio_send_to_editor_url()
	
	
	/**
	 * type_url_form_video()
	 *
	 * @param string $html
	 * @return string $html
	 **/

	function type_url_form_video($html) {
		global $content_width;
		$o = get_option('mediacaster');
		$default_width = $content_width ? intval($content_width) : 420;
		$default_height = round($default_width * 9 / 16);
		
		return '
<script type="text/javascript">
var mc = {
	i: 0,
	
	set_default: function() {
		jQuery("#insertonly-width").val("");
		jQuery("#insertonly-height").val("");
		
		return false;
	},

	set_16_9: function() {
		if ( !jQuery("#insertonly-width").val() )
			jQuery("#insertonly-width").val(' . $default_width . ');
		jQuery("#insertonly-height").val(Math.round(jQuery("#insertonly-width").val() * 9 / 16));
		return false;
	},

	set_4_3: function() {
		if ( !jQuery("#insertonly-width").val() )
			jQuery("#insertonly-width").val(' . $default_width . ');
		jQuery("#insertonly-height").val(Math.round(jQuery("#insertonly-width").val() * 3 / 4));
		return false;
	}
};
</script>
			<table class="describe"><tbody>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[href]">' . __('Video URL', 'mediacaster') . '</label></span>
						<span class="alignright"><abbr title="required" class="required">*</abbr></span>
					</th>
					<td class="field"><input id="insertonly[href]" name="insertonly[href]" value="" type="text" aria-required="true"></td>
				</tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[title]">' . __('Title', 'mediacaster') . '</label></span>
					</th>
					<td class="field"><input id="insertonly[title]" name="insertonly[title]" value="" type="text"></td>
				</tr>
				<tr><td></td><td class="help">' . __('Link text, e.g. &#8220;Lucy on YouTube&#8221;', 'mediacaster') . '</td></tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[url]">' . __('Link URL', 'mediacaster') . '</label></span>
					</th>
					<td class="field"><input id="insertonly[url]" name="insertonly[url]" value="" type="text"></td>
				</tr>
				<tr><td></td><td class="help">' . __('The link URL to which the player should direct users to (e.g. an affiliate link). Only applicable for flv, mp4, m4v, mov and YouTube files.', 'mediacaster') . '</td></tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly-width">' . __('Width x Height', 'mediacaster') . '</label></span>
					</th>
					<td class="field"><input id="insertonly-width" name="insertonly[width]" value="" type="text" size="3" style="width: 40px;"> x <input id="insertonly-height" name="insertonly[height]" value="" type="text" size="3" style="width: 40px;">
					<button type="button" class="button" onclick="return mc.set_default();">' . __('Default', 'mediacaster') . '</button>
					<button type="button" class="button" onclick="return mc.set_16_9();">' . __('16/9', 'mediacaster') . '</button>
					<button type="button" class="button" onclick="return mc.set_4_3();">' . __('4/3', 'mediacaster') . '</button></td>
				</tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[image]">' . __('Preview Image', 'mediacaster') . '</label></span>
					</th>
					<td class="field"><input id="insertonly[image]" name="insertonly[image]" value="" type="text"></td>
				</tr>
				<tr><td></td><td class="help">' . __('The URL of a preview image when the video isn\'t playing.', 'mediacaster') . '</td></tr>
				<tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[autostart]">' . __('Autostart', 'mediacaster') . '</label></span>
					</th>
					<td class="field"><label style="font-weight: normal"><input type="checkbox" name="insertonly[autostart]">&nbsp;' . __('Automatically start the (first) video (NB: bandwidth intensive).', 'mediacaster') . '</label></td>
				</tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[thickbox]">' . __('Thickbox', 'mediacaster') . '</label></span>
					</th>
					<td class="field"><label style="font-weight: normal"><input type="checkbox" name="insertonly[thickbox]">&nbsp;' . __('Open the video in a thickbox window (requires a preview image).', 'mediacaster') . '</label></td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input type="submit" class="button" name="insertonlybutton" value="' . esc_attr(__('Insert into Post', 'mediacaster')) . '" />
					</td>
				</tr>
			</tbody></table>
		';
	} # type_url_form_video()
	
	
	/**
	 * video_send_to_editor_url()
	 *
	 * @param string $html
	 * @param string $src
	 * @param string $title
	 * @return string $html
	 **/

	function video_send_to_editor_url($html, $src, $title) {
		$title = stripslashes($_POST['insertonly']['title']);
		$src = esc_url_raw(stripslashes($_POST['insertonly']['href']));
		
		if ( preg_match("/^https?:\/\/(?:www\.)?youtube.com\//i", $src) ) {
			$v = parse_url($src);
			$v = $v['query'];
			parse_str($v, $v);
			if ( empty($v['v']) ) // invalid video url
				return $html;
			if ( !$title )
				$title = __('YouTube Video');
			$link = !empty($_POST['insertonly']['url'])
				? trim(stripslashes($_POST['insertonly']['url']))
				: '';
			$link = $link
				? ( ' link="' . esc_url_raw($link) . '"' )
				: '';
			$width = !empty($_POST['insertonly']['width'])
				? ( ' width="' . intval($_POST['insertonly']['width']) . '"' )
				: '';
			$height = !empty($_POST['insertonly']['height'])
				? ( ' height="' . intval($_POST['insertonly']['height']) . '"' )
				: '';
			$image = trim(stripslashes($_POST['insertonly']['image']));
			$image = $image
				? ( ' image="' . esc_url_raw($image) . '"' )
				: '';
			$autostart = isset($_POST['insertonly']['autostart'])
				? ' autostart'
				: '';
			$thickbox = isset($_POST['insertonly']['thickbox'])
				? ' thickbox'
				: '';
			$html = '[mc src="' . $src . '"' . $width . $height . ' type="youtube"' . $autostart . $thickbox . $link . $image . ']'
				. $title
				. '[/mc]';
		} elseif ( preg_match("/\b(" . implode('|', mediacaster::get_extensions('video')) . "|rss2?|xml|feed|atom)\b/i", $src) ) {
			if ( !$title )
				$title = basename($src);
			$link = !empty($_POST['insertonly']['url'])
				? trim(stripslashes($_POST['insertonly']['url']))
				: '';
			$link = $link
				? ( ' link="' . esc_url_raw($link) . '"' )
				: '';
			$width = !empty($_POST['insertonly']['width'])
				? ( ' width="' . intval($_POST['insertonly']['width']) . '"' )
				: '';
			$height = !empty($_POST['insertonly']['height'])
				? ( ' height="' . intval($_POST['insertonly']['height']) . '"' )
				: '';
			$autostart = isset($_POST['insertonly']['autostart'])
				? ' autostart'
				: '';
			$thickbox = isset($_POST['insertonly']['thickbox'])
				? ' thickbox'
				: '';
			$html = '[mc src="' . $src . '"' . $width . $height . ' type="video"' . $autostart . $thickbox . $link . ']'
				. $title
				. '[/mc]';
		}
		
		return $html;
	} # video_send_to_editor_url()
	
	
	/**
	 * type_url_form_file()
	 *
	 * @param string $html
	 * @return string $html
	 **/

	function type_url_form_file($html) {
		return '
			<table class="describe"><tbody>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[href]">' . __('Video URL', 'mediacaster') . '</label></span>
						<span class="alignright"><abbr title="required" class="required">*</abbr></span>
					</th>
					<td class="field"><input id="insertonly[href]" name="insertonly[href]" value="" type="text" aria-required="true"></td>
				</tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[title]">' . __('Title', 'mediacaster') . '</label></span>
					</th>
					<td class="field"><input id="insertonly[title]" name="insertonly[title]" value="" type="text"></td>
				</tr>
				<tr><td></td><td class="help">' . __('Link text, e.g. &#8220;Ransom Demands (PDF)&#8221;', 'mediacaster') . '</td></tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[url]">' . __('Link URL', 'mediacaster') . '</label></span>
					</th>
					<td class="field"><input id="insertonly[url]" name="insertonly[url]" value="" type="text"></td>
				</tr>
				<tr>
					<td></td>
					<td>
						<input type="submit" class="button" name="insertonlybutton" value="' . esc_attr(__('Insert into Post', 'mediacaster')) . '" />
					</td>
				</tr>
			</tbody></table>
		';
	} # type_url_form_file()
	
	
	/**
	 * file_send_to_editor_url()
	 *
	 * @param string $html
	 * @param string $src
	 * @param string $title
	 * @return string $html
	 **/

	function file_send_to_editor_url($html, $src, $title) {
		$title = stripslashes($_POST['insertonly']['title']);
		$src = esc_url_raw(stripslashes($_POST['insertonly']['src']));
		
		return "\n"
			. '[mc src="' . $src . '" type="file"]'
			. $title
			. '[/mc]';
	} # file_send_to_editor_url()
} # mediacaster_admin
?>