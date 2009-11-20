<?php
/**
 * mediacaster_admin
 *
 * @package Mediacaster
 **/

class mediacaster_admin {
	/**
	 * save_options()
	 *
	 * @return void
	 **/

	function save_options() {
		if ( !$_POST || !current_user_can('manage_options') )
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
		$player['skin'] = in_array($_POST['player']['skin'], array('bekle', 'kleur', 'modieus'))
			? $_POST['player']['skin']
			: 'modius';
		$player['cover'] = $cover;
		
		$itunes = array();
		foreach ( array('author', 'summary', 'copyright') as $var )
			$itunes[$var] = strip_tags(stripslashes($_POST['itunes'][$var]));
		for ( $i = 1; $i <= 3; $i++ )
			$itunes['category'][$i] = strip_tags(stripslashes($_POST['itunes']['category'][$i]));
		$itunes['explicit'] = in_array(ucfirst($_POST['itunes']['explicit']), array('Yes', 'No', 'Clean'))
			? ucfirst($_POST['itunes']['explicit'])
			: 'No';
		$itunes['block'] = in_array(ucfirst($_POST['itunes']['block']), array('Yes', 'No'))
			? ucfirst($_POST['itunes']['block'])
			: 'No';
		
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
		
		$longtail['script'] = false;
		$longtail['channel'] = false;
		$script = stripslashes($_POST['longtail']['script']);
		if ( preg_match("/src=[\"']https?:\/\/www.ltassrv.com\/[^\?\"']+\?d=\d+&s=\d+&c=(\d+)/i", $script, $match) ) {
			if ( strpos($script, 'type="text/javascript"') === false )
				$script = str_replace('<script', '<script type="text/javascript"', $script);
			$longtail['script'] = $script;
			$longtail['channel'] = array_pop($match);
		}
		
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
		
		if ( !$options['longtail']['licensed'] ) {
			$licensed_player = glob(plugin_dir_path(__FILE__) . 'mediaplayer-licensed*/*player*.swf');
			if ( $licensed_player ) {
				$licensed_player = current($licensed_player);
				$options['longtail']['licensed'] = basename(dirname($licensed_player)) . '/' . basename($licensed_player);
				update_option('mediacaster', $options);
			}
		}
		
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
				. '<th scope="row">'
				. __('License Notice', 'mediacaster')
				. '</th>' . "\n"
				. '<td>';
			
			echo '<p>'
				. sprintf(__('LongTailVideo\'s JWPlayer is distributed under a Creative Commons <a href="%s">Attribute, Share Alike, Non-Commercial license</a>.', 'mediacaster'), 'http://creativecommons.org/licenses/by-nc-sa/3.0/')
				. '</p>' . "\n";
			
			global $sem_pro_version;
			if ( get_option('sem_pro_version') || !empty($sem_pro_version) ) {
				echo '<p><strong>'
					. __('Your Semiologic Pro license includes a commercial JWPlayer license, complete with a Premium Skin, for use on your Semiologic Pro sites.', 'mediacaster')
					. '</strong></p>' . "\n";
			} else {
				echo '<p>'
					. __('You need to purchase a commercial license if:', 'mediacaster')
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
				. '</label>'
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
		
		echo '<h3>'
			. __('LongTail AdSolution', 'mediacaster')
			. '</h3>' . "\n";
		
		echo '<p>'
			. sprintf(__('<a href="%s">LongTail AdSolution</a> (LTAS) allows you to insert pre-roll, overlay mid-roll, and post-roll advertisements within your videos.', 'mediacaster'), 'http://go.semiologic.com/ltas')
			. '</p>' . "\n";
		
		echo '<table class="form-table">' . "\n";
		
		echo '<tr>'
			. '<th scope="row">'
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
			. '</td>' . "\n"
			. '</tr>' . "\n"
			. '<th scope="row">'
			. __('Premium Ads', 'mediacaster')
			. '</th>' . "\n"
			. '<td>'
			. '<p>'
			. sprintf(__('To serve Premium Ads (from Video, Scanscout, YuMe, etc.) on your site, you additionally need to get your site explicitly approved. Please contact <a href="%s">LongTailVideo sales</a> for more details.', 'mediacaster'), 'http://go.semiologic.com/ltas')
			. '</p>' . "\n"
			. '<p>'
			. __('To qualify for the latter, you must either (i) own or (ii) license your video content. Additionally, your site cannot have any violent, pornographic or inappropriate content.', 'mediacaster')
			. '<p>'
			. __('Note that premium Advertisers disallow mid-roll ads when the controlbar is inline, as is done in Mediacaster. Use pre- and post- roll ads only, if your site is accepted to serve premium ads.', 'mediacaster')
			. '</p>' . "\n"
			. '</td>' . "\n"
			. '</tr>' . "\n";

		echo '</table>' . "\n";
		
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
			'Yes' => __('Yes', 'mediacaster'),
			'No' => __('No', 'mediacaster'),
			'Clean' => __('Clean', 'mediacaster'),
			) as $key => $answer ) {
			echo '<label>'
				. '<input type="radio" name="itunes[explicit]"'
				. ' value="' . esc_attr($key) . '"'
				. ( ( $key == ucfirst($options['itunes']['explicit']) )
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
			'Yes' => __('Yes', 'mediacaster'),
			'No' => __('No', 'mediacaster'),
			) as $key => $answer ) {
			echo '<label>'
				. '<input type="radio" name="itunes[block]"'
				. ' value="' . esc_attr($key) . '"'
				. ( ( $key == ucfirst($options['itunes']['block']) )
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
	 * @param int $post_id
	 * @return void
	 **/
	
	function save_attachment($post_id) {
		if ( !$_POST || wp_is_post_revision($post_id) )
			return;
		
		$post_id = (int) $post_id;
		if ( !$post_id || !isset($_POST['attachments'][$post_id]['link']) )
			return;
		
		$attachment = $_POST['attachments'][$post_id];
		
		$link = false;
		if ( !empty($attachment['link']) ) {
			$link = $attachment['link'];
			$link = trim(strip_tags(stripslashes($link)));
			if ( $link )
				$link = esc_url_raw($link);
		}
		if ( $link )
			update_post_meta($post_id, '_mc_link', addslashes($link));
		else
			delete_post_meta($post_id, '_mc_link');
		
		if ( !isset($attachment['image']) )
			return;
		
		$image = false;
		if ( !empty($attachment['image']) ) {
			$image = $attachment['image'];
			$image = trim(strip_tags($image));
			if ( $image )
				$image = esc_url_raw($image);
		}
		
		$image_id = false;
		if ( !empty($attachment['image_id']) && intval($attachment['image_id']) ) {
			$image_id = get_post_meta($post_id, '_mc_image_id', true);
			if ( $image_id != $attachment['image_id'] ) {
				$image_id = (int) $attachment['image_id'];
				update_post_meta($post_id, '_mc_image_id', $image_id);
				$meta = get_post_meta($image_id, '_wp_attachment_metadata', true);
				if ( $meta ) {
					update_post_meta($post_id, '_mc_image_width', $meta['width']);
					update_post_meta($post_id, '_mc_image_height', $meta['height']);
				}
			}
			
			$post_parent = !empty($_POST['post_id']) && intval($_POST['post_id'])
				? (int) $_POST['post_id']
				: false;
			$shot = get_post($image_id);
			if ( $post_parent && $shot->post_parent != $post_parent ) {
				$shot->post_parent = $post_parent;
				wp_update_post($shot);
			}
		}
		
		$snapshot = $image_id ? wp_get_attachment_url($image_id) : false;
		
		if ( $image == $snapshot )
			$image = false;
		
		if ( $image ) {
			if ( $image != get_post_meta($post_id, '_mc_image', true) || !get_post_meta($post_id, '_mc_image_size', true) ) {
				$image_size = @getimagesize($image);

				if ( !$image_size )
					$image_size = array();

				update_post_meta($post_id, '_mc_image', addslashes($image));
				update_post_meta($post_id, '_mc_image_size', $image_size);
			}
		} else {
			delete_post_meta($post_id, '_mc_image');
			delete_post_meta($post_id, '_mc_image_size');
		}
		
		$thumbnail = false;
		if ( !empty($attachment['thumbnail']) ) {
			$thumbnail = $attachment['thumbnail'];
			$thumbnail = trim(strip_tags($thumbnail));
			if ( $thumbnail )
				$thumbnail = esc_url_raw($thumbnail);
		}
		
		if ( $thumbnail ) {
			if ( $thumbnail != get_post_meta($post_id, '_mc_thumbnail', true) || !get_post_meta($post_id, '_mc_thumbnail_size', true) ) {
				$thumbnail_size = @getimagesize($thumbnail);
				
				if ( !$thumbnail_size )
					$thumbnail_size = array();

				update_post_meta($post_id, '_mc_thumbnail', addslashes($thumbnail));
				update_post_meta($post_id, '_mc_thumbnail_size', $thumbnail_size);
			}
		} else {
			delete_post_meta($post_id, '_mc_thumbnail');
			delete_post_meta($post_id, '_mc_thumbnail_size');
		}
		
		foreach ( array('width', 'height') as $var ) {
			if ( !empty($attachment[$var]) && intval($attachment[$var]) )
				update_post_meta($post_id, '_mc_' . $var, $attachment[$var]);
			elseif ( isset($attachment[$var]) )
				delete_post_meta($post_id, '_mc_' . $var);
		}

		if ( !empty($attachment['ltas']) )
			update_post_meta($post_id, '_mc_ltas', '1');
		elseif ( isset($attachment['ltas']) )
			delete_post_meta($post_id, '_mc_ltas');
	} # save_attachment()
	
	
	/**
	 * create_widget()
	 *
	 * @param int $post_id
	 * @return void
	 **/

	function create_widget($post_id) {
		if ( !$_POST || empty($_POST['create_widget']) || wp_is_post_revision($post_id) )
			return;
		
		$post_id = (int) $post_id;
		if ( !$post_id || !isset($_POST['attachments'][$post_id]['link']) )
			return;
		
		if ( !isset($_POST['create_widget'][$post_id]) )
			return;
		
		$post = get_post($post_id);
		$attachment = $_POST['attachments'][$post_id];
		
		if ( !$post || !$attachment )
			return;
		
		$widgets = get_option('widget_text', array('_multiwidget'));
		$widget_id = max(array_keys($widgets));
		
		if ( !intval($widget_id) )
			$widget_id = 2;
		else
			$widget_id += 1;
		
		
		$thickbox = !empty($attachment['thickbox'])
			? ' thickbox'
			: '';
		
		$autostart = !empty($attachment['autostart'])
			? ' autostart'
			: '';
		
		if ( $attachment['insert_as'] == 'file' ) {
			$type = ' type="file"';
		} else {
			switch ( $post->post_mime_type ) {
			case 'audio/mpeg':
			case 'audio/mp3':
			case 'audio/aac':
				$type = ' type="audio"';
				break;
			
			case 'video/mpeg':
			case 'video/mp4':
			case 'video/x-flv':
			case 'video/quicktime':
				$type = ' type="video"';
				break;
			
			default:
				$src = get_post_meta($post_id, '_mc_src', true);
				if ( !$src )
					return;
				elseif ( strpos($src, 'youtube.com/') !== false )
					$type = ' type="youtube"';
				else
					$type = ' type="audio"';
			}
		}
		
		$max_width = 370;
		
		switch ( $type ) {
		case ' type="file"':
			$width = false;
			break;
		
		case ' type="audio"';
			$width = $max_width;
			break;
		
		default:
		case ' type="video"':
			$size = get_post_meta($post_id, '_mc_thumbnail_size', true);
			if ( $size ) {
				$width = $size[0];
			} else {
				$width = get_post_meta($post_id, '_mc_width', true);
			}
			
			if ( !$width )
				$width = $max_width;
			break;
		}
		
		if ( $width ) {
			if ( $width > $max_width )
				$width = $max_width;
			$width = ' width="' . $width . '"';
		}
		
		$widgets[$widget_id] = array(
			'title' => sprintf(__('Video: %s', 'mediacaster'), $post->post_title),
			'text' => '[mc id="' . $post_id . '"' . $width . $type . $thickbox . $autostart . ']' . $post->post_title . '[/mc]',
			'filter' => false,
			);
		
		update_option('widget_text', $widgets);
		$sidebars_widgets = get_option('sidebars_widgets');
		if ( !isset($sidebars_widgets['mc']) )
			$sidebars_widgets['mc'] = array();
		update_option('sidebars_widgets', $sidebars_widgets);
	} # create_widget()
	
	
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
			
			$post_fields['post_title'] = array(
				'label' => __('Title', 'mediacaster'),
				'required' => true,
				'input' => 'html',
				'html' => ''
					. '<input type="text" id="mc-title-' . $post->ID . '"'
						. ' name="attachments[' . $post->ID . '][post_title]"'
						. ' value="' . esc_attr($post->post_title) . '"'
						. ' aria-required="true"'
						. ' />'
				);
			unset($post_fields['post_excerpt']);
			unset($post_fields['url']);
			
			$src = esc_url(wp_get_attachment_url($post->ID));
			unset($post_fields['image_url']);
			
			$post_fields = array_merge(array(
				'src' => array(
					'label' => __('File URL', 'mediacaster'),
					'required' => true,
					'input' => 'html',
					'html' => ''
						. '<input type="text" id="mc-src-' . $post->ID . '" readonly="readonly"'
						. ' value="' . $src . '"'
						. ' />'
					),
				), $post_fields);
			
			$link = get_post_meta($post->ID, '_mc_link', true);
			
			$post_fields['link'] = array(
				'label' => __('Link URL', 'mediacaster'),
				'value' => $link,
				'helps' => __('The link URL to which the player should direct users to (e.g. an affiliate link).', 'mediacaster'),
				);
			
			break;
		
		default:
			if ( !preg_match("/^(?:application|text)\//", $post->post_mime_type) )
				break;
			$post_fields['post_title']['required'] = true;
			$post_fields['post_title']['input'] = 'html';
			$post_fields['post_title']['html'] = ''
				. '<input type="text" id="mc-title-' . $post->ID . '"'
					. ' name="attachments[' . $post->ID . '][post_title]"'
					. ' value="' . esc_attr($post->post_title) . '"'
					. ' aria-required="true"'
					. ' />';
			unset($post_fields['post_excerpt']);
			unset($post_fields['url']);
			
			$src = esc_url(wp_get_attachment_url($post->ID));
			unset($post_fields['image_url']);
			
			$post_fields = array_merge(array(
				'src' => array(
					'label' => __('File URL', 'mediacaster'),
					'required' => true,
					'input' => 'html',
					'html' => ''
						. '<input type="text" id="mc-src-' . $post->ID . '" readonly="readonly"'
						. ' value="' . $src . '"'
						. ' />'
					),
				), $post_fields);
		}
		
		switch ( $post->post_mime_type ) {
		case 'video/mpeg':
		case 'video/mp4':
		case 'video/x-flv':
		case 'video/quicktime':
			if ( !in_array($ext, mediacaster::get_extensions('video')) )
				break;
			
			$user = wp_get_current_user();
			
			$image_id = get_post_meta($post->ID, '_mc_image_id', true);
			$image_id = $image_id ? intval($image_id) : '';
			
			$snapshot = $image_id ? wp_get_attachment_url($image_id) : false;
			$snapshot_width = (int) get_post_meta($post->ID, '_mc_image_width', true);
			$snapshot_height = (int) get_post_meta($post->ID, '_mc_image_height', true);
			
			$image = get_post_meta($post->ID, '_mc_image', true);
			$image_width = false;
			$image_height = false;
			
			if ( $image ) {
				$image_size = @getimagesize($image);
				if ( $image_size ) {
					$image_width = (int) $image_size[0];
					$image_height = (int) $image_size[1];
				} else {
					$image = false;
					$image_size = false;
				}
			}
			
			if ( !$image && $snapshot ) {
				$image = $snapshot;
				$image_width = $snapshot_width ? $snapshot_width : false;
				$image_height = $snapshot_height ? $snapshot_height : false;
			}
			
			$width = get_post_meta($post->ID, '_mc_width', true);
			$height = get_post_meta($post->ID, '_mc_height', true);
			
			if ( !( $width && $height ) ) {
				$width = false;
				$height = false;
			}
			
			if ( $image ) {
				$preview = ''
					. '<input type="hidden" id="mc-snapshot-src-' . $post->ID . '"'
						. ' value="' . ( $image ? esc_url($image) : '' ) . '" />' . "\n"
					. '<input type="hidden" id="mc-snapshot-width-' . $post->ID . '"'
						. ' value="' . $snapshot_width . '" />' . "\n"
					. '<input type="hidden" id="mc-snapshot-height-' . $post->ID . '"'
						. ' value="' . $snapshot_height . '" />' . "\n"
					. '<div style="width: 460px; overflow: hidden;"><div id="mc-preview-' . $post->ID . '" align="center" style="clear: both; margin: 0px auto;">'
					. '<img src="' . esc_url($image . '?' . $image_id) . '" width="' . ( $image_width && $image_width <= 460 ? $image_width : 460 ) . '" alt="" />' . "\n"
					. '</div></div>' . "\n";
			} else {
				$preview = ''
					. '<input type="hidden" id="mc-snapshot-src-' . $post->ID . '"'
						. ' value="" />' . "\n"
					. '<input type="hidden" id="mc-snapshot-width-' . $post->ID . '"'
						. ' value="' . $snapshot_width . '" />' . "\n"
					. '<input type="hidden" id="mc-snapshot-height-' . $post->ID . '"'
						. ' value="' . $snapshot_height . '" />' . "\n"
					. '<div style="width: 460px; overflow: hidden;"><div id="mc-preview-' . $post->ID . '" align="center" style="clear: both; margin: 0px auto; display: none;">'
					. '</div></div>' . "\n";
			}
			
			$nonce = wp_create_nonce('snapshot-' . $post->ID);
			
			$file = get_attached_file($post->ID);
			$crossdomain = '';
			if ( preg_match("/https?:\/\//i", $file) ) {
				$site_host = parse_url(get_option('home'));
				$site_host = $site_host['host'];

				$crossdomain = ''
					. '<p class="help" style="width: 460px;">'
					. sprintf(__('Important: To work on third party sites, preview images and the snapshot generator require a <a href="%1$s" target="_blank">crossdomain.xml</a> policy file. <a href="#" onclick="%2$s">Sample file</a>.', 'mediacaster'), 'http://www.adobe.com/devnet/articles/crossdomain_policy_file_spec.html', 'return mc.show_crossdomain(' . $post->ID . ');')
					. '</p>' . "\n"
					. '<div id="mc-crossdomain-' . $post->ID . '" style="display: none;">'
					. '<textarea class="code" rows="7">'
					. esc_html(
						  '<?xml version="1.0"?>' . "\n"
						. '<!DOCTYPE cross-domain-policy SYSTEM "http://www.macromedia.com/xml/dtds/cross-domain-policy.dtd">' . "\n"
						. '<cross-domain-policy>' . "\n"
						. '<allow-access-from domain="' . $site_host . '"/>' . "\n"
						. '</cross-domain-policy>'
						)
					. '</textarea>' . "\n"
					. '<p class="help">'
					. __('Paste the above in a plain text file, and upload it so it\'s available as:', 'mediacaster') . '<br />' . "\n"
					. '<code>' . __('http://your-account-id.your-video-host.com/<strong>crossdomain.xml</strong>', 'mediacaster') . '</code>'
					. '</p>' . "\n"
					. '</div>' . "\n";
			}
			
			$post_fields['snapshot'] = array(
				'label' => __('Snapshot Image', 'mediacaster'),
				'input' => 'html',
				'html' => '<div style="width: 460px; overflow: hidden;">'
						. '<input type="text" id="mc-image-' . $post->ID . '"'
						. ' name="attachments[' . $post->ID . '][image]"'
						. ' onchange="return mc.change_snapshot(' . $post->ID . ');"'
						. ' value="' . ( $image ? esc_url($image) : '' ) . '" /><br />' . "\n"
					. '<input type="hidden" id="mc-image-id-' . $post->ID . '" name="attachments[' . $post->ID . '][image_id]" value="' . $image_id . '" />'
					. '<div class="hide-if-no-js" style="float: right">'
					. '<button type="button" class="button" id="mc-new-snapshot-' . $post->ID . '"'
						. ' onclick="return mc.new_snapshot(' . $post->ID . ', ' . $user->ID . ', \'' . $nonce . '\');">'
						. __('New Snapshot', 'mediacaster') . '</button>'
					. '<button type="button" class="button" id="mc-cancel-snapshot-' . $post->ID . '"'
						. ' style="display: none;"'
						. ' onclick="return mc.cancel_snapshot(' . $post->ID . ');">'
						. __('Cancel Snapshot', 'mediacaster') . '</button>'
						. '</div>' . "\n"
					. '<p class="help">'
						. __('The URL of the preview image for your video.', 'mediacaster')
						. '</p>' . "\n"
					. '</div>' . "\n"
					. $preview
					. $crossdomain
					,
				);
			
			$post_fields['format'] = array(
				'label' => __('Width x Height', 'mediacaster'),
				'input' => 'html',
				'html' => '<p class="help">'
					. '<input id="mc-scale-' . $post->ID . '" type="hidden" value="" />'
					. '<input id="mc-width-' . $post->ID . '"'
						. ' name="attachments[' . $post->ID . '][width]"'
						. ' onfocus="mc.get_scale(' . $post->ID . ');"'
						. ' onblur="mc.set_scale(this, ' . $post->ID . ')";'
						. ' value="' . esc_attr($width) . '" type="text" size="3" style="width: 40px;">'
					. ' x '
					. '<input id="mc-height-' . $post->ID . '"'
						. ' name="attachments[' . $post->ID . '][height]"'
						. ' onfocus="mc.get_scale(' . $post->ID . ');"'
						. ' onblur="mc.set_scale(this, ' . $post->ID . ')";'
						. ' value="' . esc_attr($height) . '" type="text" size="3" style="width: 40px;">'
					. '<span class="hide-if-no-js">'
					. ' '
					. '<button type="button" class="button" title="' . __('4:3', 'mediacaster') . '"'
						. ' onclick="return mc.set_4_3(' . $post->ID . ');">'
						. __('TV', 'mediacaster') . '</button>'
					. '<button type="button" class="button" title="' . __('3:2', 'mediacaster') . '"'
						. ' onclick="return mc.set_3_2(' . $post->ID . ');">'
						. __('Camera', 'mediacaster') . '</button>'
					. '<button type="button" class="button" title="' . __('16:9', 'mediacaster') . '"'
						. ' onclick="return mc.set_16_9(' . $post->ID . ');">'
						. __('HD', 'mediacaster') . '</button>'
					. '<button type="button" class="button" title="' . __('1.85:1', 'mediacaster') . '"'
						. ' onclick="return mc.set_185_1(' . $post->ID . ');">'
						. __('Movie', 'mediacaster') . '</button>'
					. '<button type="button" class="button" title="' . __('1.85:1', 'mediacaster') . '"'
						. ' onclick="return mc.set_240_1(' . $post->ID . ');">'
						. __('Wide', 'mediacaster') . '</button>'
					. '<button type="button" class="button" title="' . __('Fit the available width', 'mediacaster') . '"'
						. ' onclick="return mc.set_max(' . $post->ID . ');">'
						. __('Clear', 'mediacaster') . '</button>'
					. '</span>' . "\n"
					. ' <span id="mc-snapshot-size-' . $post->ID . '" class="help">'
					. ( $image_width && $image_height
						? ( '(' . $image_width . ' x ' . $image_height . ')' )
						: ''
						)
					. '</span>'
					. '</p>'
					. '<p class="help" style="width: 460px;">'
					. __('Your video\'s size and aspect ratio are extracted from its snapshot image; leave these fields empty unless if you wish to specify an arbitrary size or aspect ratio.', 'mediacaster')
					. '</p>' . "\n"
					,
				);
			
			$thumbnail = get_post_meta($post->ID, '_mc_thumbnail', true);
			$thumbnail_size = get_post_meta($post->ID, '_mc_thumbnail_size', true);
			
			$post_fields['thickbox'] = array(
				'label' => __('Thickbox', 'mediacaster'),
				'input' => 'html',
				'html' => '<label style="font-weight: normal">'
					. '<input type="checkbox" id="mc-thickbox-' . $post->ID . '"'
						. ' name="attachments[' . $post->ID . '][thickbox]" checked="checked">&nbsp;'
					. __('Open in a thickbox window (requires a preview image).', 'mediacaster')
					. '</label>'
					,
				);
			
			if ( $thumbnail ) {
				$thumbnail_width = !empty($thumbnail_size[0]) ? (int) $thumbnail_size[0] : 460;
				
				$thumbnail_preview = '<img src="' . esc_url($thumbnail) . '" width="' . $thumbnail_width . '" />';
			} else {
				$thumbnail_preview = '';
			}
			
			$thumbnail_preview = '<div style="width: 460px; overflow: hidden;"><div id="mc-thumbnail-preview-' . $post->ID . '" style="margin: 0px auto;" align="center">' . $thumbnail_preview . '</div></div>' . "\n";
			
			$post_fields['tb_thumbnail'] = array(
				'label' => __('Thumbnail', 'mediacaster'),
				'input' => 'html',
				'html' => ''
					. '<input type="text" id="mc-thumbnail-' . $post->ID . '"'
							. ' name="attachments[' . $post->ID . '][thumbnail]"'
							. ' onchange="return mc.change_thumbnail(' . $post->ID . ');"'
							. ' value="' . ( $thumbnail ? esc_url($thumbnail) : '' )
							. '" />' . "\n"
					. '<p class="help">'
						. __('The URL of the thickbox thumbnail, should it differ from the snapshot image.', 'mediacaster')
						. '</p>' . "\n"
					. $thumbnail_preview
					,
				);
			
			if ( $o['longtail']['channel'] ) {
				$ltas = get_post_meta($post->ID, '_mc_ltas', true);
				
				$post_fields['ltas'] = array(
					'label' => __('Insert Ads', 'mediacaster'),
					'input' => 'html',
					'html' => '<label style="font-weight: normal">'
						. '<input type="checkbox" id="attachments[' . $post->ID . '][ltas]"'
							. ' name="attachments[' . $post->ID . '][ltas]"'
							. checked($ltas, true, false)
							. '>&nbsp;'
						. __('Insert Ads (premium ads require a title and a description).', 'mediacaster')
						. '</label>',
					);
			}
			
		case 'audio/mpeg':
		case 'audio/mp3':
		case 'audio/aac':
		case 'video/3gpp':
			$post_fields['autostart'] = array(
				'label' => __('Autostart', 'mediacaster'),
				'input' => 'html',
				'html' => '<label style="font-weight: normal">'
					. '<input type="checkbox" id="mc-autostart-' . $post->ID . '"'
						. ' name="attachments[' . $post->ID . '][autostart]">&nbsp;'
					. __('Automatically start the (first) media player (bandwidth intensive).', 'mediacaster')
					. '</label>',
				);
			
			$post_fields['insert_as'] = array(
				'label' => __('Insert As', 'mediacaster'),
				'input' => 'html',
				'html' => '<label style="font-weight: normal; margin-right: 15px; display: inline;">'
					. '<input type="radio" id="mc-insert-' . $post->ID . '-player"'
						. ' name="attachments[' . $post->ID . '][insert_as]" value="player" checked="checked">&nbsp;'
					. __('Media Player', 'mediacaster')
					. '</label>'
					. ' '
					. '<label style="font-weight: normal; margin-right: 15px; display: inline;">'
					. '<input type="radio" id="mc-insert-' . $post->ID . '-file"'
						. ' name="attachments[' . $post->ID . '][insert_as]" value="file">&nbsp;'
					. __('Download Link', 'mediacaster')
					. '</label>'
					. ' '
					. '<button type="submit" class="button" name="create_widget[' . $post->ID . ']">'
						. __('Create Widget', 'mediacaster') . '</button>'
					,
				);
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
		if ( empty($post['ID']) && !empty($attachment['src']) ) {
			$src = $attachment['src'];
			$guid = rtrim('ext:' . str_replace(array('?', '&'), '/', $src), '/');
			
			if ( preg_match("/https?:\/\/(?:[^\/]+\.)?youtube\.com\//", $src) || preg_match("/[\/=](rss2?|xml|feed|atom)(\/|&|$)/i", $src) ) {
				$post['errors'][] = __('Invalid Media Type', 'sem-reloaded');
				return $post;
			}
			
			$mime_type = wp_check_filetype($src, null);
			$ext = $mime_type['ext'];
			$mime_type = $mime_type['type'];

			if ( !$mime_type ) {
				$post['errors'] = __('Invalid Media Type', 'sem-reloaded');
				return $post;
			}

			if ( in_array($ext, mediacaster::get_extensions('audio')) )
				$type = 'audio';
			elseif ( in_array($ext, mediacaster::get_extensions('video')) )
				$type = 'video';
			else
				$type = 'file';
			
			global $wpdb;
			$att_id = $wpdb->get_var("
				SELECT	ID
				FROM	$wpdb->posts
				WHERE	guid = '" . $wpdb->escape($guid) . "'
				");
			
			extract($attachment, EXTR_SKIP);
			
			if ( $att_id ) {
				$post = get_post($att_id, ARRAY_A);
				if ( !empty($post_title) ) {
					$post['post_title'] = $post_title;
					$post['post_name'] = $post_title;
				}
				if ( !empty($post_content) )
					$post['post_content'] = $post_content;
			} else {
				$post_date = current_time('mysql');
				$post_date_gmt = current_time('mysql', 1);
				
				$post = array_merge(array(
					'post_title' => $post_title,
					'post_name' => $post_title,
					'post_mime_type' => $mime_type,
					'guid' => $guid,
					'post_parent' => !empty($_POST['post_id']) ? intval($_POST['post_id']) : 0,
					'post_content' => $post_content,
					'post_date' => $post_date,
					'post_date_gmt' => $post_date_gmt,
					), $post);
				
				$att_id = wp_insert_attachment($post, false, $post_parent);
				if ( is_wp_error($att_id) ) {
					$post['errors'][] = __('Failed to insert your external attachment', 'mediacaster');
					return $post;
				}

				update_post_meta($att_id, '_mc_src', $src);
				$post['ID'] = $att_id;
				$_POST['attachments'][$att_id] = $_POST['attachments'][0];
			}
		}
		
		foreach ( array('link', 'image', 'image_id', 'thumbnail', 'width', 'height', 'ltas') as $var ) {
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
		$att = get_post($send_id);
		
		if ( !$attachment['post_title'] )
			$attachment['post_title'] = strip_tags($att->post_title);
		if ( !$attachment['post_title'] )
			$attachment['post_title'] = __('Untitled Media', 'mediacaster');
		
		$file_url = wp_get_attachment_url($att->ID);
		
		if ( !$file_url )
			return $html;
		
		switch ( $att->post_mime_type ) {
		case 'audio/mpeg':
		case 'audio/mp3':
		case 'audio/aac':
			if ( preg_match("/\.([a-z0-9]+)$/i", $file_url, $ext) ) {
				$ext = strtolower(end($ext));
				if ( !preg_match("/\b(?:" . implode('|', mediacaster::get_extensions('audio')) . ")\b/i", $file_url) )
					return $html;
			}
			if ( $attachment['insert_as'] != 'file' )
				$attachment['insert_as'] = 'audio';
			break;
		
		case 'video/mpeg':
		case 'video/mp4':
		case 'video/x-flv':
		case 'video/quicktime':
		case 'video/3gpp':
			if ( preg_match("/\.([a-z0-9]+)$/i", $file_url, $ext) ) {
				$ext = strtolower(end($ext));
				if ( !preg_match("/\b(?:" . implode('|', mediacaster::get_extensions('video')) . ")\b/i", $file_url) )
					return $html;
			}
			if ( $attachment['insert_as'] != 'file' )
				$attachment['insert_as'] = 'video';
			break;
			
		default:
			if ( !preg_match("/^(?:application|text)\//", $att->post_mime_type) )
				return $html;
			$attachment['insert_as'] = 'file';
			break;
		}
		
		switch ( $attachment['insert_as'] ) {
		case 'audio':
			$autostart = !empty($attachment['autostart'])
				? ' autostart'
				: '';
		
			$html = '[mc id="' . $send_id . '" type="audio"' . $autostart . ']'
			 	. $attachment['post_title']
			 	. '[/mc]';
			break;
		
		case 'video':
			$image = !empty($attachment['image']) && trim(stripslashes($attachment['image']));
			$image_id = !empty($attachment['image_id']) && intval($attachment['image_id']);
		
			$autostart = !empty($attachment['autostart'])
				? ' autostart'
				: '';
		
			$thickbox = !empty($attachment['thickbox']) && ( $image || $image_id )
				? ' thickbox'
				: '';
			
			$width = false && !empty($attachment['width']) && intval($attachment['width'])
				? ' width="' . $attachment['width'] . '"'
				: '';
			
			$height = false && !empty($attachment['height']) && intval($attachment['height'])
				? ' height="' . $attachment['height'] . '"'
				: '';
			
			$html = '[mc id="' . $send_id . '" type="video"' . $width . $height . $autostart . $thickbox . ']'
				. $attachment['post_title']
				. '[/mc]';
			break;
		
		case 'file':
		default:
			$html = '[mc id="' . $send_id . '" type="file"]'
				. $attachment['post_title']
				. '[/mc]';
			break;
		}
		
		return $html;
	} # media_send_to_editor()
	
	
	/**
	 * type_url_form()
	 *
	 * @param string $html
	 * @return string $html
	 **/

	function type_url_form($html = null) {
		switch ( current_filter() ) {
		case 'type_url_form_audio':
			$type = 'audio';
			break;
			
		case 'type_url_form_video':
			$type = 'video';
			break;
			
		case 'type_url_form_file':
		default:
			$type = 'file';
			break;
		}
		
		$o = '
			<table class="describe"><tbody>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="mc-src-0">' . __('File URL', 'mediacaster') . '</label></span>
						<span class="alignright"><abbr title="required" class="required">*</abbr></span>
					</th>
					<td class="field"><input id="mc-src-0" name="attachments[0][src]" value="" type="text" aria-required="true" onchange="return mc.set_type();"></td>
				</tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="mc-title-0">' . __('Title', 'mediacaster') . '</label></span>
						<span class="alignright"><abbr title="required" class="required">*</abbr></span>
					</th>
					<td class="field"><input id="mc-title-0" name="attachments[0][post_title]" value="" type="text" aria-required="true"></td>
				</tr>';	
		
		$o .= '
				<tr id="mc-content-row">
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="attachments[0][post_content]">' . __('Description', 'mediacaster') . '</label></span>
					</th>
					<td class="field"><textarea id="attachments[0][post_content]" name="attachments[0][post_content]"></textarea></td>
				</tr>';
		
		
		$o .= '
				<tr id="mc-link-row" ' . ( $type == 'file' ? ' style="display: none;"' : '' ) . '>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="mc-width-0">' . __('Link URL', 'mediacaster') . '</label></span>
					</th>
					<td class="field">'
				. '<input type="text" id="mc-link-0"'
					. ' name="attachments[0][link]"'
					. ' value="" />' . "\n"
				. '<p class="help">'
					. __('The link URL to which the player should direct users to (e.g. an affiliate link).', 'mediacaster')
					. '</p>' . "\n"
					. '</td>
				</tr>';
		
		$o .= '
				<tr id="mc-youtube-row" style="display: none;">
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label>' . __('Link URL', 'mediacaster') . '</label></span>
					</th>
					<td class="field">'
				. '<p class="help">'
					. sprintf(__('Not available, but don\'t miss YouTube\'s <a href="%s" target="_blank">video annotation</a> features.', 'mediacaster'), 'http://www.google.com/support/youtube/bin/answer.py?answer=92710&topic=14354')
					. '</p>' . "\n"
					. '</td>
				</tr>';	
		
		$user = wp_get_current_user();
		$nonce = wp_create_nonce('snapshot-0');
		$site_host = parse_url(get_option('home'));
		$site_host = $site_host['host'];
		
		$o .= '
				<tr id="mc-snapshot-row" ' . ( $type != 'video' ? ' style="display: none;"' : '' ) . '>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label>' . __('Snapshot Image', 'mediacaster') . '</label></span>
					</th>
					<td class="field">'
				. '<div style="width: 460px;">'
				. '<input type="text" id="mc-image-0"'
							. ' name="attachments[0][image]"'
							. ' onchange="return mc.change_snapshot(0);"'
							. ' value="" /><br />' . "\n"
				. '<input type="hidden" id="mc-image-id-0" name="attachments[0][image_id]" value="" />'
				. '<div class="hide-if-no-js" style="float: right">'
				. '<button type="button" class="button" id="mc-new-snapshot-0"'
					. ' onclick="return mc.new_snapshot(0, ' . $user->ID . ', \'' . $nonce . '\');">'
					. __('New Snapshot', 'mediacaster') . '</button>'
				. '<button type="button" class="button" id="mc-cancel-snapshot-0"'
					. ' style="display: none;"'
					. ' onclick="return mc.cancel_snapshot(0);">'
					. __('Cancel Snapshot', 'mediacaster') . '</button>'
					. '</div>' . "\n"
				. '</div>' . "\n"
				. '<p class="help">'
					. __('The URL of the preview image for your video.', 'mediacaster')
					. '</p>' . "\n"
			. '<input type="hidden" id="mc-snapshot-src-0" value="" />' . "\n"
			. '<input type="hidden" id="mc-snapshot-width-0"'
				. ' value="" />' . "\n"
			. '<input type="hidden" id="mc-snapshot-height-0"'
				. ' value="" />' . "\n"
			. '<div style="width: 460px; overflow: hidden;"><div id="mc-preview-0" align="center" style="clear: both; display: none; margin: 0px auto;"></div></div>'
			. '<p class="help" style="width: 460px;">'
			. sprintf(__('Important: To work on third party sites, preview images and the snapshot generator require a <a href="%1$s" target="_blank">crossdomain.xml</a> policy file. <a href="#" onclick="%2$s">Sample file</a>.', 'mediacaster'), 'http://www.adobe.com/devnet/articles/crossdomain_policy_file_spec.html', 'return mc.show_crossdomain(0);')
			. '</p>' . "\n"
			. '<div id="mc-crossdomain-0" style="display: none;">'
			. '<textarea class="code" rows="7">'
			. esc_html(
				  '<?xml version="1.0"?>' . "\n"
				. '<!DOCTYPE cross-domain-policy SYSTEM "http://www.macromedia.com/xml/dtds/cross-domain-policy.dtd">' . "\n"
				. '<cross-domain-policy>' . "\n"
				. '<allow-access-from domain="' . $site_host . '"/>' . "\n"
				. '</cross-domain-policy>'
				)
			. '</textarea>' . "\n"
			. '<p class="help">'
			. __('Paste the above in a plain text file, and upload it so it\'s available as:', 'mediacaster') . '<br />' . "\n"
			. '<code>' . __('http://your-account-id.your-video-host.com/<strong>crossdomain.xml</strong>', 'mediacaster') . '</code>'
			. '</p>' . "\n"
			. '</div>' . "\n"
					. '</td>
				</tr>';
			
			
		$o .= '
				<tr id="mc-format-row" ' . ( $type != 'video' ? ' style="display: none;"' : '' ) . '>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="mc-width-0">' . __('Width x Height', 'mediacaster') . '</label></span>
					</th>
					<td class="field">'
				. '<p class="help">'
				. '<input id="mc-scale-0" type="hidden" value="" />'
				. '<input id="mc-width-0"'
					. ' name="attachments[0][width]"'
					. ' onfocus="mc.get_scale(0);"'
					. ' onblur="mc.set_scale(this, 0)";'
					. ' value="" type="text" size="3" style="width: 40px;">'
				. ' x '
				. '<input id="mc-height-0"'
					. ' name="attachments[0][height]"'
					. ' onfocus="mc.get_scale(0);"'
					. ' onblur="mc.set_scale(this, 0)";'
					. ' value="" type="text" size="3" style="width: 40px;">'
				. '<span class="hide-if-no-js">'
				. ' '
				. '<button type="button" class="button"'
					. ' onclick="return mc.set_4_3(0);" title="' . __('4:3', 'mediacaster') . '">'
					. __('TV', 'mediacaster') . '</button>'
				. '<button type="button" class="button"'
					. ' onclick="return mc.set_3_2(0);" title="' . __('3:2', 'mediacaster') . '">'
					. __('Camera', 'mediacaster') . '</button>'
				. '<button type="button" class="button"'
					. ' onclick="return mc.set_16_9(0);" title="' . __('16:9', 'mediacaster') . '">'
					. __('HD', 'mediacaster') . '</button>'
				. '<button type="button" class="button" title="' . __('1.85:1', 'mediacaster') . '"'
					. ' onclick="return mc.set_185_1(0);">'
					. __('Movie', 'mediacaster') . '</button>'
				. '<button type="button" class="button" title="' . __('1.85:1', 'mediacaster') . '"'
					. ' onclick="return mc.set_240_1(0);">'
					. __('Wide', 'mediacaster') . '</button>'
				. '<button type="button" class="button" title="' . __('Fit the available width', 'mediacaster') . '"'
					. ' onclick="return mc.set_max(0);">'
					. __('Clear', 'mediacaster') . '</button>'
				. '</span>' . "\n"
				. ' <span id="mc-snapshot-size-0"></span>'
				. '</p>'
				. '<p class="help" style="width: 460px;">'
				. __('Your video\'s size and aspect ratio are extracted from its snapshot image; leave these fields empty unless you wish to specify an arbitrary size or aspect ratio.', 'mediacaster')
				. '</p>' . "\n"
					. '</td>
				</tr>';
			
		$o .= '
				<tr id="mc-thickbox-row" ' . ( $type != 'video' ? ' style="display: none;"' : '' ) . '>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="mc-thickbox-0">' . __('Thickbox', 'mediacaster') . '</label></span>
					</th>
					<td class="field">'
					. '<label style="font-weight: normal;"><input type="checkbox" id="mc-thickbox-0" name="attachments[0][thickbox]" checked="checked" />&nbsp;' . __('Open in a thickbox window (requires a preview image).', 'mediacaster') . '</label>'
					. '</td>
				</tr>';
			
		$o .= '
				<tr id="mc-thumbnail-row" ' . ( $type != 'video' ? ' style="display: none;"' : '' ) . '>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="mc-thumbnail-0">' . __('Thumbnail', 'mediacaster') . '</label></span>
					</th>
					<td class="field">'
					. '<input type="text" id="mc-thumbnail-0"'
								. ' name="attachments[0][thumbnail]"'
								. ' onchange="return mc.change_thumbnail(0);"'
								. ' value="" /><br />' . "\n"
					. '<p class="help">'
						. __('The URL of the thickbox thumbnail, should it differ from the snapshot image.', 'mediacaster')
						. '</p>' . "\n"
					. '</td>
				</tr>';
			
		$ops = get_option('mediacaster');
		if ( $ops['longtail']['channel'] ) {
			$o .= '
				<tr id="mc-ltas-row" ' . ( $type != 'video' ? ' style="display: none;"' : '' ) . '>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="mc-ltas-0">' . __('Insert Ads', 'mediacaster') . '</label></span>
					</th>
					<td class="field">'
					. '<label style="font-weight: normal">'
						. '<input type="checkbox" id="mc-ltas-0"'
							. ' name="attachments[0][ltas]"'
							. '>&nbsp;'
						. __('Insert Ads (premium ads require a title and a description).', 'mediacaster')
						. '</label>'
					. '</td>
				</tr>';
		}
			
		$o .= '
				<tr id="mc-autostart-row" ' . ( $type == 'file' ? ' style="display: none;"' : '' ) . '>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="mc-autostart-0">' . __('Autostart', 'mediacaster') . '</label></span>
					</th>
					<td class="field">'
				. '<label style="font-weight: normal;"><input type="checkbox" id="mc-autostart-0" name="attachments[0][autostart]" />&nbsp;' . __('Automatically start the (first) media player (bandwidth intensive).', 'mediacaster') . '</label>'
					. '</td>
				</tr>';
			
		$o .= '
				<tr id="mc-player-row" ' . ( $type == 'file' ? ' style="display: none;"' : '' ) . '>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label>' . __('Insert As', 'mediacaster') . '</label></span>
					</th>
					<td class="field">'
				. '<label style="font-weight: normal; margin-right: 15px; display: inline;">'
				. '<input type="radio" id="mc-insert-0-player"'
					. ' name="attachments[0][insert_as]" value="player" checked="checked">&nbsp;'
				. __('Media Player', 'mediacaster')
				. '</label>'
				. ' '
				. '<label style="font-weight: normal; margin-right: 15px; display: inline;">'
				. '<input type="radio" id="mc-insert-0-file"'
					. ' name="attachments[0][insert_as]" value="file">&nbsp;'
				. __('Download Link', 'mediacaster')
				. '</label>'
				. '</td>
				</tr>';
		
		$o .= '
				<tr>
					<td></td>
					<td>';
			
		if ( !empty($_GET['post_id']) && intval($_GET['post_id']) > 0 ) {
			$o .= '
							<input type="submit" class="button" name="insertonlybutton" value="' . esc_attr(__('Insert into Post', 'mediacaster')) . '" />';
		}
		
		$o .= '
							<input id="mc-save-attachment" type="submit" class="button" name="save" value="' . esc_attr(__('Save Attachment', 'mediacaster')) . '" />
						</td>
					</tr>
			</tbody></table>';
		
		return $o;
	} # type_url_form()
	
	
	/**
	 * send_to_editor_url()
	 *
	 * @param string $html
	 * @param string $src
	 * @param string $title
	 * @return string $html
	 **/

	function send_to_editor_url($html, $src, $title) {
		$attachment = $_POST['attachments'][0];
		
		$attachment['post_content'] = wp_filter_post_kses($attachment['post_content']);
		$attachment = stripslashes_deep($attachment);
		$attachment['post_title'] = trim(strip_tags($attachment['post_title']));
		$attachment['src'] = trim(strip_tags($attachment['src']));
		if ( !$attachment['src'] )
			return '';
		$attachment['src'] = esc_url_raw($attachment['src']);
		
		$post_title = $attachment['post_title'];
		$post_content = $attachment['post_content'];
		$src = $attachment['src'];
		
		$post_id = !empty($_POST['post_id']) ? intval($_POST['post_id']) : false;
		$post = $post_id ? get_post($post_id) : false;
		
		$post_date = current_time('mysql');
		$post_date_gmt = current_time('mysql', 1);
		$post_parent = $post ? $post->ID : 0;
		
		$guid = rtrim('ext:' . str_replace(array('?', '&'), '/', $src), '/');
		
		if ( preg_match("/https?:\/\/(?:[^\/]+\.)?youtube\.com\//", $src) ) {
			if ( !$post_title )
				$post_title = __('YouTube Video', 'mediacaster');
			
			$width = !empty($attachment['width']) && intval($attachment['width'])
				? ( ' width="' . $attachment['width'] . '"' )
				: '';
			
			$height = !empty($attachment['height']) && intval($attachment['height'])
				? ( ' height="' . $attachment['height'] . '"' )
				: '';
			
			return ''
				. '[mc src="' . $src . '" type="youtube"' . $width . $height . ']'
				. $post_title
				. '[/mc]';
		} elseif ( preg_match("/[\/=](rss2?|xml|feed|atom)(\/|&|$)/i", $src) ) {
			if ( !$post_title )
				$post_title = __('MP3 Playlist', 'mediacaster');
			
			$autostart = !empty($attachment['autostart'])
				? ' autostart'
				: '';
			
			return ''
				. '[mc src="' . $src . '" type="audio"' . $autostart . ']'
				. $post_title
				. '[/mc]';
		} else {
			$mime_type = wp_check_filetype($src, null);
			$ext = $mime_type['ext'];
			$mime_type = $mime_type['type'];
			
			if ( !$mime_type ) {
				if ( !$post_title )
					$post_title = __('Untitled Media', 'mediacaster');
				
				return '<a href="' . esc_url($src) . '">'
					. $post_title
					. '</a>';
			}
			
			if ( in_array($ext, mediacaster::get_extensions('audio')) )
				$type = 'audio';
			elseif ( in_array($ext, mediacaster::get_extensions('video')) )
				$type = 'video';
			else
				$type = 'file';
		}
		
		global $wpdb;
		$att_id = $wpdb->get_var("
			SELECT	ID
			FROM	$wpdb->posts
			WHERE	guid = '" . $wpdb->escape($guid) . "'
			");
		
		if ( !$att_id ) {
			if ( !$post_title )
				$post_title = __('Untitled Media', 'mediacaster');
			
			$data = array(
				'post_title' => $post_title,
				'post_name' => $post_title,
				'post_mime_type' => $mime_type,
				'guid' => $guid,
				'post_parent' => $post_parent,
				'post_content' => $post_content,
				'post_date' => $post_date,
				'post_date_gmt' => $post_date_gmt,
				);
			
			$att_id = wp_insert_attachment($data, false, $post_parent);
			if ( is_wp_error($att_id) )
				die(-1);
			
			#$_POST['attachments'][$att_id] = $_POST['attachments'][0];
			update_post_meta($att_id, '_mc_src', $src);
			
			if ( in_array($type, array('audio', 'video')) ) {
				$link = false;
				if ( !empty($attachment['link']) ) {
					$link = $attachment['link'];
					$link = trim(strip_tags($link));
					if ( $link )
						$link = esc_url_raw($link);
				}
				
				if ( $link )
					update_post_meta($att_id, '_mc_link', addslashes($link));
				else
					delete_post_meta($att_id, '_mc_link');
				
				$image = false;
				if ( !empty($attachment['image']) ) {
					$image = $attachment['image'];
					$image = trim(strip_tags($image));
					if ( $image )
						$image = esc_url_raw($image);
				}
				
				$image_id = false;
				if ( !empty($attachment['image_id']) && intval($attachment['image_id']) ) {
					$image_id = get_post_meta($att_id, '_mc_image_id', true);
					if ( $image_id != $attachment['image_id'] ) {
						$image_id = (int) $attachment['image_id'];
						update_post_meta($att_id, '_mc_image_id', $image_id);
						$meta = get_post_meta($image_id, '_wp_attachment_metadata', true);
						if ( $meta ) {
							update_post_meta($att_id, '_mc_image_width', $meta['width']);
							update_post_meta($att_id, '_mc_image_height', $meta['height']);
						}
					}
					
					$shot = get_post($image_id);
					if ( $post_parent && $shot->post_parent != $post_parent ) {
						$shot->post_parent = $post_parent;
						wp_update_post($shot);
					}
				}
				
				$snapshot = $image_id ? wp_get_attachment_url($image_id) : false;
				
				if ( $image == $snapshot )
					$image = false;
				
				if ( $image ) {
					if ( $image != get_post_meta($att_id, '_mc_image', true) || !get_post_meta($att_id, '_mc_image_size', true) ) {
						$image_size = @getimagesize($image);
					
						if ( !$image_size )
							$image_size = array();
					
						update_post_meta($att_id, '_mc_image', addslashes($image));
						update_post_meta($att_id, '_mc_image_size', $image_size);
					}
				} else {
					delete_post_meta($att_id, '_mc_image');
					delete_post_meta($att_id, '_mc_image_size');
				}
				
				$thumbnail = false;
				if ( !empty($attachment['thumbnail']) ) {
					$thumbnail = $attachment['thumbnail'];
					$thumbnail = trim(strip_tags($thumbnail));
					if ( $thumbnail )
						$thumbnail = esc_url_raw($thumbnail);
				}
				
				if ( $thumbnail ) {
					if ( $thumbnail != get_post_meta($att_id, '_mc_thumbnail', true) || !get_post_meta($att_id, '_mc_thumbnail_size', true) ) {
						$thumbnail_size = @getimagesize($thumbnail);
					
						if ( !$thumbnail_size )
							$thumbnail_size = array();
					
						update_post_meta($att_id, '_mc_thumbnail', addslashes($thumbnail));
						update_post_meta($att_id, '_mc_thumbnail_size', $thumbnail_size);
					}
				} else {
					delete_post_meta($att_id, '_mc_thumbnail');
					delete_post_meta($att_id, '_mc_thumbnail_size');
				}
				
				foreach ( array('width', 'height') as $var ) {
					if ( !empty($attachment[$var]) && intval($attachment[$var]) )
						update_post_meta($att_id, '_mc_' . $var, $attachment[$var]);
					elseif ( isset($attachment[$var]) )
						delete_post_meta($att_id, '_mc_' . $var);
				}

				if ( !empty($attachment['ltas']) )
					update_post_meta($att_id, '_mc_ltas', '1');
				elseif ( isset($attachment['ltas']) )
					delete_post_meta($att_id, '_mc_ltas');
			}
		}
		
		return mediacaster_admin::media_send_to_editor('', $att_id, $attachment);
	} # send_to_editor_url()
	
	
	/**
	 * admin_scripts()
	 *
	 * @return void
	 **/

	function admin_scripts() {
		global $parent_file;
		global $wp_scripts;
		if ( $parent_file != 'upload.php' && !$wp_scripts->query('swfupload-handlers') )
			return;
		
		$folder = plugin_dir_url(__FILE__);
		wp_enqueue_script('swfobject');
		wp_enqueue_script('mediacaster_admin', $folder . 'js/admin.js', array('jquery-ui-sortable'), '20091002', true);
		add_action('admin_print_footer_scripts', array('mediacaster_admin', 'footer_scripts'), 30);
	} # admin_scripts()
	
	
	/**
	 * footer_scripts()
	 *
	 * @return void
	 **/
	
	function footer_scripts() {
		global $content_width;
		global $sem_options;
		$o = get_option('mediacaster');
		
		$default_width = !empty($content_width) && intval($content_width) ? (int) $content_width : 420;
		$max_width = $default_width;
		
		if ( get_option('template') == 'sem-reloaded' ) {
			switch ( $sem_options['active_layout'] ) {
			case 'm':
				$max_width = 550;
				break;
			case 'mm':
			case 'ms':
			case 'sm':
				$max_width = 650;
				break;
			default:
				$max_width = 800;
				break;
			}
		}
		
		$media_player = esc_url_raw(plugin_dir_url(__FILE__)
			. ( $o['longtail']['licensed']
				? $o['longtail']['licensed']
				: 'mediaplayer/player.swf'
				));
		$site_url = site_url();
		
		echo <<<EOS

<script type="text/javascript">
jQuery(document).ready(function() {
	mc.default_width = $default_width;
	mc.max_width = $max_width;
	mc.media_player = '$media_player';
	mc.site_url = '$site_url';
});
</script>

EOS;
	} # footer_scripts()
	
	
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
		if ( !$user || !$user->has_cap('upload_files') || $post->ID && !$user->has_cap('edit_post', $post->ID) )
			die(-1);
		
		if ( wp_verify_nonce($nonce, 'snapshot-' . ( $attachment->ID ? $attachment->ID : '0' )) !== 1 )
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
			));
		
		$new_file = $details['file'];
		$url = $details['url'];
		
		$title = sprintf(__('%s Snapshot', 'mediacaster'), $attachment->ID ? $attachment->post_title : __('Video', 'mediacaster'));
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
			update_post_meta($attachment->ID, '_mc_image_id', $snapshot_id);
			update_post_meta($attachment->ID, '_mc_image_width', $meta['width']);
			update_post_meta($attachment->ID, '_mc_image_height', $meta['height']);
			delete_post_meta($attachment->ID, '_mc_image');
			delete_post_meta($attachment->ID, '_mc_image_size');
			delete_post_meta($attachment->ID, '_mc_width');
			delete_post_meta($attachment->ID, '_mc_height');
		}
		
		die($url . '?' . $snapshot_id . '.' . $meta['width'] . '.' . $meta['height']);
	} # create_snapshot()
	
	
	/**
	 * post_upload_ui()
	 *
	 * @return void
	 **/
	
	function post_upload_ui() {
		echo '<h3>'
			. __('Add Media From URL', 'mediacaster')
			. '</h3>' . "\n";
		
		echo mediacaster_admin::type_url_form();
		
		echo '<h3>'
			. __('Or Upload Files', 'mediacaster')
			. '</h3>' . "\n";
	} # post_upload_ui()
} # mediacaster_admin

add_action('save_post', array('mediacaster_admin', 'save_entry'));
add_action('add_attachment', array('mediacaster_admin', 'save_attachment'));
add_action('edit_attachment', array('mediacaster_admin', 'save_attachment'));
add_action('add_attachment', array('mediacaster_admin', 'create_widget'), 50);
add_action('edit_attachment', array('mediacaster_admin', 'create_widget'), 50);

add_action('settings_page_mediacaster', array('mediacaster_admin', 'save_options'), 0);

add_filter('attachment_fields_to_edit', array('mediacaster_admin', 'attachment_fields_to_edit'), 20, 2);
add_filter('attachment_fields_to_save', array('mediacaster_admin', 'attachment_fields_to_save'), 20, 2);
add_filter('media_send_to_editor', array('mediacaster_admin', 'media_send_to_editor'), 20, 3);

add_filter('type_url_form_audio', array('mediacaster_admin', 'type_url_form'), 20);
add_filter('type_url_form_video', array('mediacaster_admin', 'type_url_form'), 20);
add_filter('type_url_form_file', array('mediacaster_admin', 'type_url_form'), 20);
add_filter('audio_send_to_editor_url', array('mediacaster_admin', 'send_to_editor_url'), 20, 3);
add_filter('video_send_to_editor_url', array('mediacaster_admin', 'send_to_editor_url'), 20, 3);
add_filter('file_send_to_editor_url', array('mediacaster_admin', 'send_to_editor_url'), 20, 3);

add_action('admin_print_scripts', array('mediacaster_admin', 'admin_scripts'), 15);
?>