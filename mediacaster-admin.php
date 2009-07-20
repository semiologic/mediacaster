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

add_action('admin_print_scripts', array('mediacaster_admin', 'admin_scripts'), 15);

class mediacaster_admin {
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
	 * save_attachment()
	 *
	 * @return void
	 **/
	
	function save_attachment($post_id) {
		$post_id = (int) $post_id;
		if ( !$post_id || !isset($_POST['attachments'][$post_id]['image']) )
			return;
		
		$attachment = $_POST['attachments'][$post_id];
		
		if ( !get_post_meta($post_id, '_mc_image_id', true) ) {
			delete_post_meta($post_id, '_mc_width');
			delete_post_meta($post_id, '_mc_height');
		}
	} # save_attachment()
	
	
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
		
		$player = array();
		$player['position'] = in_array($_POST['player']['position'], array('top', 'bottom', 'none'))
			? $_POST['player']['position']
			: 'top';
		$player['skin'] = in_array($_POST['player']['skin'], array('bekle', 'kleur', 'metarby10', 'modieus', 'silverywhite'))
			? $_POST['player']['skin']
			: 'modius';
		$player['cover'] = $cover;
		
		$itunes = array();
		foreach ( array('author', 'summary', 'copyright') as $var )
			$itunes[$var] = strip_tags(stripslashes($_POST['itunes'][$var]));
		for ( $i = 1; $i <= 3; $i++ )
			$itunes['category'][$i] = strip_tags(stripslashes($_POST['itunes']['category'][$i]));
		$itunes['explicit'] = in_array($_POST['itunes']['explicit'], array('yes', 'no', 'clean'))
			? $_POST['itunes']['explicit']
			: 'no';
		$itunes['block'] = in_array($_POST['itunes']['block'], array('yes', 'no'))
			? $_POST['itunes']['block']
			: 'no';
		
		$longtail = array();
		
		$longtail['agree'] = !empty($old_ops['longtail']['agree'])
			? true
			: isset($_POST['longtail']['agree']);
		
		$licensed_player = glob(plugin_dir_path(__FILE__) . 'mediaplayer-licensed*/*player*.swf');
		if ( $licensed_player ) {
			$licensed_player = current($licensed_player);
			$longtail['licensed'] = basename(dirname($licensed_player)) . '/' . basename($licensed_player);
		} else {
			$longtail['licensed'] = false;
		}
		
		/* todo: ltas */
		//*
		$script = stripslashes($_POST['longtail']['script']);
		if ( preg_match("/src=[\"']https?:\/\/www.ltassrv.com\/serve\/api5.4.asp\?d=\d+&s=\d+&c=(\d+)/i", $script, $match) ) {
			$longtail['script'] = $script;
			$longtail['channel'] = array_pop($match);
		} else {
			$longtail['script'] = false;
			$longtail['channel'] = false;
		}
		//*/	
		//$longtail['script'] = false;
		//$longtail['channel'] = false;
		
		$options = compact('player', 'itunes', 'longtail', 'version');
		update_option('mediacaster', $options);
		
		echo '<div class="updated fade">' . "\n"
			. '<p>'
				. '<strong>'
				. __('Settings saved.', 'mediacaster')
				. '</strong>'
			. '</p>' . "\n"
			. ( $longtail['licensed'] && empty($old_ops['longtail']['licensed'])
				? ( '<p>'
					. sprintf(__('Licensed Player successfully detected (%s). Thank you for supporting LongTail Video!', 'mediacaster'), $longtail['licensed'])
					. '</p>' . "\n"
					)
				: ''
				)
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

		$bytes = apply_filters('import_upload_size_limit', wp_max_upload_size());
		
		echo '<input type="hidden" name="MAX_FILE_SIZE" value="' . esc_attr($bytes) .'" />' . "\n";

		wp_nonce_field('mediacaster');
		
		screen_icon();
		
		echo '<h2>'. __('Mediacaster Settings', 'mediacaster') . '</h2>' . "\n";
		
		if ( empty($options['longtail']['agree']) ) {
			echo '<h3>'
				. __('License Notice', 'mediacaster')
				. '</h3>' . "\n";
			
			echo '<table class="form-table">' . "\n"
				. '<tr>'
				. '<th>' . __('License Notice', 'mediacaster') . '</th>' . "\n"
				. '<td>';
			
			echo '<p>'
				. sprintf(__('LongTailVideo\'s JWPlayer is distributed under a Creative Commons <a href="%s">Attribute, Share Alike, Non-Commercial license</a>.'), 'http://creativecommons.org/licenses/by-nc-sa/3.0/')
				. '</p>' . "\n";
			
			global $sem_pro_version;
			if ( get_option('sem_pro_version') || !empty($sem_pro_version) ) {
				echo '<p><strong>'
					. __('Your Semiologic Pro license includes a commercial JWPlayer license, complete with a Premium Skin, for use on your Semiologic Pro sites.', 'mediacaster')
					. '</strong></p>' . "\n";
			} else {
				echo '<p>'
					. __('You need to purchase a commercial license if:')
					. '</p>' . "\n";

				echo '<ul class="ul-disc">' . "\n";

				echo '<li>'
					. __('Your site serves any ads (AdSense, display banners, etc.)', 'mediacaster')
					. '</li>' . "\n";

				echo '<li>'
					. __('You want to remove the JWPlayer\'s attribution (eliminate the right-click link)', 'mediacaster')
					. '</li>' . "\n";

				echo '<li>'
					. __('You are a corporation (governmental or nonprofit use is free)', 'mediacaster')
					. '</li>' . "\n";

				echo '</ul>' . "\n";
			}
			
			echo '<p>'
				. '<label>'
				. '<input type="checkbox" name="longtail[agree]" />'
				. '&nbsp;'
				. __('I have read the license and agree to its terms.', 'mediacaster')
				. '</p>' . "\n";
			
			echo '</td>'
				. '</tr>' . "\n"
				. '</table>' . "\n";
			
			echo '<p class="submit">'
				. '<input type="submit"'
					. ' value="' . esc_attr(__('Save Changes', 'mediacaster')) . '"'
					. ' />'
				. '</p>' . "\n";
		}
		
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
				. '<input type="radio" name="player[skin]"'
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
				. ' id="mediacaster-player-position-top" name="player[position]"'
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
				. ' id="mediacaster-player-position-bottom" name="player[position]"'
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
				. ' id="mediacaster-player-position-none" name="player[position]"'
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
		
		/*
		echo '<h3>'
			. __('LongTail AdSolution', 'mediacaster')
			. '</h3>' . "\n";
		
		echo '<p>'
			. sprintf(__('<a href="%s">LongTail AdSolution</a> allows to you insert pre-roll, overlay mid- and post-roll advertisements within your Videos.', 'mediacaster'), 'http://go.semiologic.com/ltas')
			. '</p>' . "\n";
		
		echo '<table class="form-table">' . "\n";
		
		echo '<tr>'
			. '<th>'
			. __('LTAS Script', 'mediacaster')
			. '</th>' . "\n"
			. '<td>'
			. '<textarea name="longtail[script]" class="widefat code" cols="58" rows="3"'
				. ( !current_user_can('unfiltered_html')
					? ' disabled="disabled"'
					: ''
					)
				. '>'
			. ( $options['longtail']['script']
				? esc_html($options['longtail']['script'])
				: esc_html('<script language="JavaScript" src="http://www.ltassrv.com/serve/api5.4.asp?d=XXXX&s=XXXX&c=XXXX&v=1"></script>')
				)
			. '</textarea>' . "\n"
			. '<p>'
			. sprintf(__('You will find the needed LongTail AdSolution script after <a href="%1$s">signing up</a>, and logging into the <a href="%2$s">LTAS Dashboard</a>.', 'mediacaster'), 'http://go.semiologic.com/ltas', 'http://dashboard.longtailvideo.com/default.aspx')
			. '</p>' . "\n"
			. '<p>'
			. __('Once logged in, browse Setup / Channel Setup. Create a channel if needed. Once it\'s approved, click Implement. Choose JW 4.4 or later as the player, and click get code. Copy the script, paste it into the above field, and ignore the remaining steps.', 'mediacaster')
			. '</p>' . "\n"
			. '<p>'
			. sprintf(__('<strong>Important</strong>: To serve Premium Ads (from Video, Scanscout, YuMe, etc.) on your site, you additionally need to get your site explicitly approved. Please contact <a href="%s">LongTailVideo sales</a> for more details.', 'mediacaster'), 'http://go.semiologic.com/ltas')
			. '</p>' . "\n"
			. '<p>'
			. __('To qualify for the latter, you must either (i) own or (ii) license your video content. Additionally, your site cannot have any violent, pornographic or inappropriate content.', 'mediacaster')
			. '</td>' . "\n"
			. '</tr>' . "\n";
		
		echo '</table>' . "\n";
		*/
		
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
				. ' id="mediacaster-itunes-author" name="itunes[author]"'
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
				. ' id="mediacaster-itunes-summary" name="itunes[summary]"'
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
			echo '<select name="itunes[category][' . $i . ']">' . "\n"
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
				. '<input type="radio" name="itunes[explicit]"'
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
				. '<input type="radio" name="itunes[block]"'
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
				. ' id="mediacaster-itunes-copyright" name="itunes[copyright]"'
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
			static $player;
			
			if ( !isset($scripts) ) {
				$player = plugin_dir_url(__FILE__) . 'mediaplayer/player.swf';
				$site_url = site_url();
				$user = wp_get_current_user();
				
				$scripts = <<<EOS
<script type="text/javascript">
var mc = {
	i: 0,
	player: null,
	interval: null,
	post_id: null,
	
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
	},
	
	take_snapshot: function(post_id, nonce) {
		var s_id, s_width, s_height, s_src;
		
		do {
			s_id = 'mc-snapshot-' + Math.floor(Math.random() * 10000);
		} while ( document.getElementById(s_id) );
		
		s_width = 460;
		s_height = 345;
		
		jQuery("#mc-preview-" + post_id).hide().html('<div id="' + s_id + '"></div>').fadeIn('slow');
		jQuery("#mc-new-snapshot-" + post_id).hide();
		jQuery("#mc-cancel-snapshot-" + post_id).show();
		
		var params = {};
		params.allowfullscreen = 'false';
		params.allowscriptaccess = 'true';
		
		var flashvars = {};
		flashvars.file = jQuery("#mc-src-" + post_id).val();
		flashvars.controlbar = 'over';
		flashvars.plugins = 'quickkeys-1,snapshot-1';
		flashvars['snapshot.script'] = '$site_url/mc-snapshot.' + post_id + '.' + $user->ID + '.' + nonce + '.php';
		flashvars.id = s_id;
		
		var attributes = {};
		attributes.id = s_id;
		attributes.name = s_id;

		if ( mc.post_id )
			mc.cancel_snapshot(mc.post_id);
		if ( mc.interval )
			clearInterval(mc.interval);
		mc.player = null;
		
		swfobject.embedSWF("$player", s_id, s_width, s_height, '9.0.0', false, flashvars, params, attributes);
		
		mc.post_id = post_id;
		mc.player = document.getElementById(s_id);
		mc.interval = setInterval('mc.catch_snapshot();', 100);
	},
	
	catch_snapshot: function() {
		var p = mc.player;
		
		if ( !p || typeof p.getConfig != 'function' || p.getConfig()['state'] != 'IDLE' )
			return;
		
		var img = p.getConfig()['image'];
		
		if ( !img )
			return;
		
		if ( mc.interval )
			clearInterval(mc.interval);
		mc.interval = null;
		mc.player = null;
		
		jQuery("#media-item-" + jQuery("#mc-image-id-" + mc.post_id).val()).fadeOut('fast').html('');
		
		jQuery("#mc-image-" + mc.post_id).val('');
		jQuery("#mc-image-id-" + mc.post_id).val(img.replace(/.*\?/, ''));
		jQuery("#mc-preview-src-" + mc.post_id).val(img);
		jQuery("#mc-preview-" + mc.post_id).hide().html('<img src="' + img + '" width="460" alt="" />').fadeIn('slow');
		
		mc.post_id = null;
	},
	
	cancel_snapshot: function(post_id) {
		if ( mc.interval )
			clearInterval(mc.interval);
		mc.interval = null;
		mc.player = null;
		mc.post_id = null;
		
		var img = jQuery("#mc-preview-src-" + post_id).val();
		
		if ( img )
			jQuery("#mc-preview-" + post_id).hide().html('<img src="' + img + '" width="460" alt="" />').fadeIn('slow');
		else
			jQuery("#mc-preview-" + post_id).hide().html('').fadeIn('slow');
		
		jQuery("#mc-cancel-snapshot-" + post_id).hide();
		jQuery("#mc-new-snapshot-" + post_id).show();
	}
};

</script>
EOS;
			} else {
				$scripts = false;
			}
			
			$image_id = get_post_meta($post->ID, '_mc_image_id', true);
			$image_id = $image_id ? intval($image_id) : '';
			
			$src = esc_url(wp_get_attachment_url($post->ID));
			
			$preview = $image_id
				? esc_url(wp_get_attachment_url($image_id) . '?' . $image_id)
				: false;
			
			if ( $preview ) {
				$preview = '<input type="hidden" id="mc-preview-src-' . $post->ID . '"'
					. ' value="' . $preview . '" />' . "\n"
					. '<div id="mc-preview-' . $post->ID . '">'
					. '<img src="' . $preview . '" alt="" width="460" style="float: none; display: block; margin-left: auto; margin-right: auto;" />' . "\n"
					. '</div>' . "\n";
			} else {
				$preview = '<input type="hidden" id="mc-preview-src-' . $post->ID . '" value="" />' . "\n"
					. '<div id="mc-preview-' . $post->ID . '"></div>' . "\n";
			}
			
			$nonce = wp_create_nonce('snapshot-' . $post->ID);
			
			$post_fields['format'] = array(
				'label' => __('Width x Height', 'mediacaster'),
				'input' => 'html',
				'html' => $scripts
					. '<input id="attachments-width-' . $post->ID . '"'
						. ' name="attachments[' . $post->ID . '][width]"'
						. ' value="" type="text" size="3" style="width: 40px;">'
					. ' x '
					. '<input id="attachments-height-' . $post->ID . '"'
						. ' name="attachments[' . $post->ID . '][height]"'
						. ' value="" type="text" size="3" style="width: 40px;">'
					. ' '
					. '<button type="button" class="button"'
						. ' onclick="return mc.set_max(' . $post->ID . ');">'
						. __('Default', 'mediacaster') . '</button>'
					. '<button type="button" class="button"'
						. ' onclick="return mc.set_16_9(' . $post->ID . ');">'
						. __('16:9', 'mediacaster') . '</button>'
						. '<button type="button" class="button"'
						. ' onclick="return mc.set_4_3(' . $post->ID . ');">'
						. __('4:3', 'mediacaster') . '</button>'
						. ' '
						. __('(optional)', 'mediacaster'),
				);
			
			$post_fields['image'] = array(
				'label' => __('Preview Image', 'mediacaster'),
				'input' => 'html',
				'html' => '<input type="text" id="attachments[' . $post->ID . '][image]"'
						. ' name="attachments[' . $post->ID . '][image]"'
						. ' value="" /><br />' . "\n"
					. '<div class="hide-if-no-js" style="float: right">'
					. '<input type="hidden" id="mc-src-' . $post->ID . '" value="' . $src . '" />'
					. '<input type="hidden" id="mc-image-id-' . $post->ID . '" value="' . $image_id . '" />'
					. '<button type="button" class="button" id="mc-new-snapshot-' . $post->ID . '"'
						. ' onclick="return mc.take_snapshot(' . $post->ID . ', \'' . $nonce . '\');">'
						. __('New Snapshot', 'mediacaster') . '</button>'
					. '<button type="button" class="button" id="mc-cancel-snapshot-' . $post->ID . '"'
						. ' style="display: none;"'
						. ' onclick="return mc.cancel_snapshot(' . $post->ID . ');">'
						. __('Cancel Snapshot', 'mediacaster') . '</button>'
						. '</div>' . "\n"
					. '<p class="help">'
						. __('The URL of a preview image when the video isn\'t playing.', 'mediacaster')
						. '</p>' . "\n"
					. $preview,
				);
			
			$post_fields['autostart'] = array(
				'label' => __('Autostart', 'mediacaster'),
				'input' => 'html',
				'html' => '<label style="font-weight: normal">'
					. '<input type="checkbox" id="attachments[' . $post->ID . '][autostart]"'
						. ' name="attachments[' . $post->ID . '][autostart]">&nbsp;'
					. __('Automatically start the (first) media player (NB: bandwidth intensive).', 'mediacaster')
					. '</label>',
				);
			
			$post_fields['thickbox'] = array(
				'label' => __('Thickbox', 'mediacaster'),
				'input' => 'html',
				'html' => '<label style="font-weight: normal">'
					. '<input type="checkbox" id="attachments[' . $post->ID . '][thickbox]"'
						. ' name="attachments[' . $post->ID . '][thickbox]" checked="checked">&nbsp;'
					. __('Open the video in a thickbox window (requires a preview image).', 'mediacaster')
					. '</label>',
				);
			
			/* todo: ltas */
			//*
			if ( $o['longtail']['channel'] ) {
				$post_fields['ltas'] = array(
					'label' => __('Insert Ads', 'mediacaster'),
					'input' => 'html',
					'html' => '<label style="font-weight: normal">'
						. '<input type="checkbox" id="attachments[' . $post->ID . '][ltas]"'
							. ' name="attachments[' . $post->ID . '][ltas]" checked="checked">&nbsp;'
						. __('Insert Ads (requires a title and a description for premium ads).', 'mediacaster')
						. '</label>',
					);
			}
			//*/
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
			
			$post_fields['autostart'] = array(
				'label' => __('Autostart', 'mediacaster'),
				'input' => 'html',
				'html' => '<label style="font-weight: normal">'
					. '<input type="checkbox" id="attachments[' . $post->ID . '][autostart]"'
						. ' name="attachments[' . $post->ID . '][autostart]">&nbsp;'
					. __('Automatically start the (first) media player (NB: bandwidth intensive).', 'mediacaster')
					. '</label>',
				);
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
			
			$thickbox = !empty($attachment['thickbox']) && $image
				? ' thickbox'
				: '';
			
			/* todo: ltas */
			//*
			$ltas = !empty($attachment['ltas'])
				&& trim(strip_tags($post->post_title)) && trim(strip_tags($post->post_content))
				? ' ltas'
				: '';
			//*/
			//$ltas = '';
			
			$html = '[mc id="' . $send_id . '"' . $width . $height . ' type="video"' . $autostart . $thickbox . $link . $image . $ltas . ']'
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
					<button type="button" class="button" onclick="return mc.set_16_9();">' . __('16:9', 'mediacaster') . '</button>
					<button type="button" class="button" onclick="return mc.set_4_3();">' . __('4:3', 'mediacaster') . '</button></td>
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
	
	
	/**
	 * admin_scripts()
	 *
	 * @return void
	 **/

	function admin_scripts() {
		global $wp_scripts;
		if ( !is_a($wp_scripts, 'WP_Scripts') || !$wp_scripts->query('admin-gallery', 'queue') )
			return;
		
		$folder = plugin_dir_url(__FILE__);
		wp_enqueue_script('swfobject');
		wp_enqueue_script('mediacaster_admin', $folder . 'js/admin.js', array('jquery-ui-sortable'), '2.0', true);
	} # admin_scripts()
	
	
	/**
	 * create_snapshot()
	 *
	 * @param int $post_id
	 * @param int $user_id
	 * @param string $nonce
	 * @return void
	 **/
	
	function create_snapshot($post_id, $user_id, $nonce) {
		status_header(200);
		header('Content-Type: text/plain; Charset: UTF-8');
		#die(WP_CONTENT_DIR . '/test.jpg');
		
		$post_id = (int) $post_id;
		$user_id = (int) $user_id;
		
		if ( !$user_id )
			die(-1);
		
		$attachment = get_post($post_id);
		if ( !$attachment || !$attachment->ID ) {
			$post = false;
			$attachment = false;
		} elseif ( $attachment->post_type != 'attachment' ) {
			$post = wp_clone($attachment);
			$attachment = false;
		} else {
			$post = get_post($attachment->post_parent);
		}
		
		if ( $post && $post->ID )
			$_POST['post_id'] = $post->ID; // for the uploads folder plugin
		
		$user = wp_set_current_user($user_id);
		if ( !$user || !$user->has_cap('upload_files') || $post->ID && !$user->has_cap('edit_post') )
			die(-1);
		
		if ( wp_verify_nonce($nonce, 'snapshot' . ( $attachment->ID ? "-$attachment->ID" : '' )) !== 1 )
			die(-1);
		
		$time = current_time('mysql');
		if ( substr($post->post_date, 0, 4) > 0 )
			$time = $post->post_date;
		$uploads = wp_upload_dir($time);
		
		if ( !empty($uploads['error']) )
			die(-1);
		
		$ext = 'jpg';
		$type = 'image/jpeg';
		
		if ( $attachment->ID ) {
			$file_name = basename(wp_get_attachment_url($attachment->ID));
			$file_name = preg_replace("/\.[^.]+$/", '.jpg', $file_name);
		} else {
			$file_name = 'snapshot.jpg';
		}
		
		$file_name = wp_unique_filename($uploads['path'], $file_name);
		$url = $uploads['url'] . '/' . $file_name;
		
		$new_file = $uploads['path'] . '/' . $file_name;
		$tmp_file = wp_tempnam();
		
		$fp = fopen($tmp_file, 'wb');
		fwrite($fp, file_get_contents('php://input'));
		fclose($fp);
		
		if ( @rename($tmp_file, $new_file) === false ) {
			@unlink($tmp_file);
			die(-1);
		}
		
		$stat = stat(dirname($new_file));
		$perms = $stat['mode'] & 0000666;
		@chmod($new_file, $perms);
		
		$details = apply_filters('wp_handle_upload', array(
			'file' => $new_file,
			'url' => $url,
			'type' => $type,
			));
		
		$new_file = $details['file'];
		$url = $details['url'];
		$type = $details['type'];
		
		$title = __('Video Snapshot', 'mediacaster');
		$content = '';
		
		$post_date = current_time('mysql');
		$post_date_gmt = current_time('mysql', 1);
		$post_parent = $post ? $post->ID : 0;
		
		$snapshot = array(
			'post_mime_type' => $type,
			'guid' => $url,
			'post_parent' => $post_parent,
			'post_title' => $title,
			'post_name' => $title,
			'post_content' => $content,
			'post_date' => $post_date,
			'post_date_gmt' => $post_date_gmt,
			);
		
		$snapshot_id = wp_insert_attachment($snapshot, $new_file, $post_parent);
		if ( is_wp_error($snapshot_id) )
			die(-1);
		
		$meta = wp_generate_attachment_metadata($snapshot_id, $new_file);
		wp_update_attachment_metadata($snapshot_id, $meta);
		
		if ( $attachment && $attachment->ID ) {
			$old_snapshot_id = get_post_meta($attachment->ID, '_mc_image_id', true);
			if ( $old_snapshot_id )
				wp_delete_attachment($old_snapshot_id);
			update_post_meta($attachment->ID, '_mc_image_id', $snapshot_id);
			update_post_meta($attachment->ID, '_mc_width', $meta['width']);
			update_post_meta($attachment->ID, '_mc_height', $meta['height']);
			delete_post_meta($attachment->ID, '_mc_image');
		}
		
		die("$url?$snapshot_id");
	} # create_snapshot()
} # mediacaster_admin
?>