<?php
/**
 * mediacaster_admin
 *
 * @package Mediacaster
 **/

add_action('settings_page_mediacaster', array('mediacaster_admin', 'save_options'), 0);

add_filter('upload_mimes', array('mediacaster_admin', 'upload_mimes'));
add_filter('attachment_fields_to_edit', array('mediacaster_admin', 'attachment_fields_to_edit'), 20, 2);
add_filter('media_send_to_editor', array('mediacaster_admin', 'media_send_to_editor'), 20, 3);

add_filter('type_url_form_audio', array('mediacaster_admin', 'type_url_form_audio'));
add_filter('audio_send_to_editor_url', array('mediacaster_admin', 'audio_send_to_editor_url'), 10, 3);

add_filter('type_url_form_video', array('mediacaster_admin', 'type_url_form_video'));
add_filter('video_send_to_editor_url', array('mediacaster_admin', 'video_send_to_editor_url'), 10, 3);

class mediacaster_admin {
	/**
	 * strip_tags_rec()
	 *
	 * @return void
	 **/

	function strip_tags_rec($input) {
		if ( is_array($input) ) {
			$input = array_map(array('mediacaster_admin', 'strip_tags_rec'), $input);
		} else {
			$input = strip_tags($input);
		}

		return $input;
	} # strip_tags_rec()

	
	/**
	 * save_options()
	 *
	 * @return void
	 **/

	function save_options() {
		if ( !$_POST )
			return;
		
		check_admin_referer('mediacaster');

		if ( isset($_POST['delete_cover']) ) {
			if ( defined('GLOB_BRACE') ) {
				if ( $cover = glob(ABSPATH . 'media/cover{,-*}.{jpg,jpeg,png}', GLOB_BRACE) ) {
					$cover = current($cover);
					@unlink($cover);
				}
			} else {
				if ( $cover = glob(ABSPATH . 'media/cover-*.jpg') ) {
					$cover = current($cover);
					@unlink($cover);
				}
			}
		}

		$options = $_POST['mediacaster'];

		$options = mediacaster_admin::strip_tags_rec($options);

		if ( @ $_FILES['new_cover']['name'] ) {
			$name =& $_FILES['new_cover']['name'];
			$tmp_name =& $_FILES['new_cover']['tmp_name'];
			
			$name = strip_tags(stripslashes($name));

			preg_match("/\.([^.]+)$/", $name, $ext); 
			$ext = end($ext);
			
			if ( !in_array(strtolower($ext), array('jpg', 'jpeg', 'png')) ) {
				echo '<div class="error">'
					. "<p>"
						. "<strong>"
						. __('Invalid File Type.')
						. "</strong>"
					. "</p>\n"
					. "</div>\n";
			} else {
				if ( defined('GLOB_BRACE') ) {
					if ( $cover = glob(ABSPATH . 'media/cover{,-*}.{jpg,jpeg,png}', GLOB_BRACE) ) {
						$cover = current($cover);
						@unlink($cover);
					}
				} else {
					if ( $cover = glob(ABSPATH . 'media/cover-*.jpg') ) {
						$cover = current($cover);
						@unlink($cover);
					}
				}
				
				preg_match("/\.([^.]+)$/", $name, $ext); 
				$ext = end($ext);
				
				$entropy = intval(get_option('sem_entropy')) + 1;
				update_option('sem_entropy', $entropy);

				$new_name = ABSPATH . 'media/cover-' . $entropy . '.' . $ext;

				@move_uploaded_file($tmp_name, $new_name);
				$stat = stat(dirname($new_name));
				$perms = $stat['mode'] & 0000666;
				@chmod($new_name, $perms);
			}
		}
		
		update_option('mediacaster', $options);
		
		echo '<div class="updated">' . "\n"
			. '<p>'
				. '<strong>'
				. __('Settings saved.')
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
		echo '<form enctype="multipart/form-data" method="post" action="">' . "\n";

		$bytes = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );

		echo  "\n" . '<input type="hidden" name="MAX_FILE_SIZE" value="' . esc_attr($bytes) .'" />' . "\n";

		$options = get_option('mediacaster');
		
		$site_url = trailingslashit(site_url());
		
		echo '<div class="wrap">' . "\n"
			. '<h2>'. __('Mediacaster Settings') . '</h2>' . "\n";

		wp_nonce_field('mediacaster');

		echo '<h3>'
				. __('Media Player')
				. '</h3>' . "\n";

		echo '<table class="form-table">';
		
		echo '<tr valign="top">'
			. '<th scope="row">'
			. __('Player Position')
			. '</th>'
			. '<td>'
			. '<label for="mediacaster-player-position-top">'
			. '<input type="radio"'
				. ' id="mediacaster-player-position-top" name="mediacaster[player][position]"'
				. ' value="top"'
				. ( $options['player']['position'] != 'bottom'
					? ' checked="checked"'
					: ''
					)
				. ' />'
			. '&nbsp;'
			. __('Top')
			. '</label>'
			. ' '
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
			. __('Bottom')
			. '</label>'
			. '</td>'
			. '</tr>' . "\n";

		echo '<tr valign="top">'
			. '<th scope="row">'
			. __('Video Player Format')
			. '</th>'
			. '<td>'
			. '<label for="mediacaster-player-format-16-9">'
			. '<input type="radio"'
				. ' id="mediacaster-player-format-16-9" name="mediacaster[player][format]"'
				. ' value="16/9"'
				. ( $options['player']['format'] != '4/3'
					? ' checked="checked"'
					: ''
					)
				. ' />'
			. '&nbsp;'
			. __('16/9')
			. '</label>'
			. ' '
			. '<label for="mediacaster-player-format-4-3">'
			. '<input type="radio"'
				. ' id="mediacaster-player-format-4-3" name="mediacaster[player][format]"'
				. ' value="4/3"'
				. ( $options['player']['format'] == '4/3'
					? ' checked="checked"'
					: ''
					)
				. ' />'
			. '&nbsp;'
			. __('4/3')
			. '</label>'
			. '</td>' . "\n"
			. '</tr>';

		$cover = mediacaster::get_cover();

		echo '<tr valign="top">'
			. '<th scope="row">'
				. __('MP3 Playlist Cover')
			. '</th>' . "\n"
			. '<td>';
		
		if ( $cover ) {
			$cover = ABSPATH . $cover;
			echo '<div style="margin-botton: 6px;">';
			
			echo '<img src="' . esc_url(str_replace(ABSPATH, $site_url, $cover)) . '" />' . "\n"
				. '<br />' . "\n";
				
			if ( is_writable($cover) ) {
				echo '<label for="delete_cover">'
					. '<input type="checkbox"'
						. ' id="delete_cover" name="delete_cover"'
						. ' style="text-align: left; width: auto;"'
						. ' />'
					. '&nbsp;'
					. __('Delete')
					. '</label>';
			} else {
				echo __('This cover is not writable by the server.');
			}
			
			echo '</div>';
		}

		echo '<div style="margin-botton: 6px;">'
			. '<label for="new_cover">'
				. __('New Image (jpg or png)') . ':'
			. '</label>'
			. '<br />' . "\n"
			. '<input type="file" id="new_cover" name="new_cover" />'
			. '</div>' . "\n";

		if ( !defined('GLOB_BRACE') ) {
			echo '<p>' . __('Notice: GLOB_BRACE is an undefined constant on your server. Non .jpg images will be ignored.') . '</p>';
		}
		
		echo '</td>'
			. '</tr>';
		
		echo '</table>';

		echo '<p class="submit">'
			. '<input type="submit"'
				. ' value="' . esc_attr(__('Save Changes')) . '"'
				. ' />'
			. '</p>' . "\n";


		echo '<h3>'
			. __('iTunes')
			. '</h3>' . "\n";

		if ( class_exists('podPress_class') ) {
			echo '<p>'
				. __('PodPress was detected. Configure itunes-related fields in your PodPress options')
				. '</p>' . "\n";
		} else {
			echo '<table class="form-table">';
			
			echo '<tr valign="top">'
				. '<th scope="row">'
				. '<label for="mediacaster-itunes-author">'
					. __('Author')
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
					. __('Summary') . ':'
					. '</label>'
				. '</th>'
				. '<td>'
				. '<textarea class="widefat" cols="58" rows="3"'
					. ' id="mediacaster-itunes-summary" name="mediacaster[itunes][summary]"'
					. ' >' . "\n"
					. $options['itunes']['summary']
					. '</textarea>' . "\n"
				. '</td>'
				. '</tr>' . "\n";


			echo '<tr valign="top">'
				. '<th scope="row">'
					. __('Categories')
				. '</th>'
				. '<td>';

			for ( $i = 1; $i <= 3; $i++ ) {
				echo '<select class="widefat"'
						. ' name="mediacaster[itunes][category][' . $i . ']"'
						. ' >' . "\n"
					. '<option value="">' . __('Select...') . '</option>' . "\n";

				foreach ( mediacaster_admin::get_itunes_categories() as $category ) {
					$category = $category;

					echo '<option'
						. ' value="' . esc_attr($category) . '"'
						. ( ( $category == $options['itunes']['category'][$i] )
							? ' selected="selected"'
							: ''
							)
						. '>'
						. esc_attr($category)
						. '</option>' . "\n";
				} echo '</select>'
				 	. '<br />'. "\n";
			}

			echo '</td>'
			 	. '</tr>' . "\n";
			

			echo '<tr valign="top">'
				. '<th scope="row">'
					. '<label for="mediacaster-itunes-explicit">'
					. __('Explicit') . ':'
					. '</label>'
				. '</th>'
				. '<td>'
				. '<select class="widefat"'
					. ' id="mediacaster-itunes-explicit" name="mediacaster[itunes][explicit]"'
					. ' >' . "\n";

			foreach ( array('Yes', 'No', 'Clean') as $answer ) {
				echo '<option'
					. ' value="' . esc_attr($answer) . '"'
					. ( ( $answer == $options['itunes']['explicit'] )
						? ' selected="selected"'
						: ''
						)
					. '>'
					. $answer
					. '</option>' . "\n";
			}

			echo '</select>' . "\n"
				. '</td>'
				. '</tr>' . "\n";


			echo '<tr valign="top">'
				. '<th scope="row">'
					. '<label for="mediacaster-itunes-block">'
					. __('Block iTunes') . ':'
					. '</label>'
				. '</th>'
				. '<td>'
				. '<select class="widefat"'
					. ' id="mediacaster-itunes-block" name="mediacaster[itunes][block]"'
					. ' >' . "\n";

			foreach ( array('Yes', 'No') as $answer ) {
				echo '<option'
					. ' value="' . esc_attr($answer) . '"'
					. ( ( $answer == $options['itunes']['block'] )
						? ' selected="selected"'
						: ''
						)
					. '>'
					. $answer
					. '</option>' . "\n";
			}

			echo '</select>' . "\n"
				. '</td>'
				. '</tr>' . "\n";

			echo '<tr valign="top">'
				. '<th scope="row">'
				. '<label for="mediacaster-itunes-copyright">'
					. __('Copyright') . ':'
					. '</label>'
				. '</th>'
				. '<td>'
				. '<textarea class="widefat" cols="58" rows="2"'
					. ' id="mediacaster-itunes-copyright" name="mediacaster[itunes][copyright]"'
					. ' >' . "\n"
					. $options['itunes']['copyright']
					. '</textarea>' . "\n"
				. '</td>'
				. '</tr>' . "\n";
				
			echo '</table>';

			echo '<p class="submit">'
				. '<input type="submit"'
					. ' value="' . esc_attr(__('Save Changes')) . '"'
					. ' />'
				. '</p>' . "\n";;
		}

		echo '</div>' . "\n";

		echo '</form>' . "\n";
	} # edit_options()


	/**
	 * get_itunes_categories()
	 *
	 * @return void
	 **/

	function get_itunes_categories() {
		return array(
			'Arts',
			'Arts / Design',
			'Arts / Fashion & Beauty',
			'Arts / Food',
			'Arts / Literature',
			'Arts / Performing Arts',
			'Arts / Visual Arts',

			'Business',
			'Business / Business News',
			'Business / Careers',
			'Business / Investing',
			'Business / Management & Marketing',
			'Business / Shopping',

			'Comedy',

			'Education',
			'Education / Education Technology',
			'Education / Higher Education',
			'Education / K-12',
			'Education / Language Courses',
			'Education / Training',

			'Games & Hobbies',
			'Games & Hobbies / Automotive',
			'Games & Hobbies / Aviation',
			'Games & Hobbies / Hobbies',
			'Games & Hobbies / Other Games',
			'Games & Hobbies / Video Games',

			'Government & Organizations',
			'Government & Organizations / Local',
			'Government & Organizations / National',
			'Government & Organizations / Non-Profit',
			'Government & Organizations / Regional',

			'Health',
			'Health / Alternative Health',
			'Health / Fitness & Nutrition',
			'Health / Self-Help',
			'Health / Sexuality',

			'Kids & Family',

			'Music',

			'News & Politics',

			'Religion & Spirituality',
			'Religion & Spirituality / Buddhism',
			'Religion & Spirituality / Christianity',
			'Religion & Spirituality / Hinduism',
			'Religion & Spirituality / Islam',
			'Religion & Spirituality / Judaism',
			'Religion & Spirituality / Other',
			'Religion & Spirituality / Spirituality',

			'Science & Medicine',
			'Science & Medicine / Medicine',
			'Science & Medicine / Natural Sciences',
			'Science & Medicine / Social Sciences',

			'Society & Culture',
			'Society & Culture / History',
			'Society & Culture / Personal Journals',
			'Society & Culture / Philosophy',
			'Society & Culture / Places & Travel',

			'Sports & Recreation',
			'Sports & Recreation / Amateur',
			'Sports & Recreation / College & High School',
			'Sports & Recreation / Outdoor',
			'Sports & Recreation / Professional',

			'Technology',
			'Technology / Gadgets',
			'Technology / Tech News',
			'Technology / Podcasting',
			'Technology / Software How-To',

			'TV & Film',
		);
	} # get_itunes_categories()
	
	
	/**
	 * upload_mimes()
	 *
	 * @param array $mines
	 * @return array $mines
	 **/

	function upload_mimes($mimes) {
		if ( !isset($mimes['flv']) )
			$mimes['flv'] = 'video/x-flv';
		return $mimes;
	} # upload_mimes()
	
	
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
		
		switch ( $post->post_mime_type ) {
		case 'audio/mpeg':
		case 'video/mpeg':
		case 'video/x-flv':
			unset($post_fields['post_excerpt']);
			if ( !in_array($ext, array('mp3', 'mp4', 'flv')) ) {
				unset($post_fields['url']);
				break;
			}
			
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
			$post_fields['url']['helps'] = 'The link URL to which the player should direct users to (e.g. an affiliate link).';
			break;
		
		case 'video/quicktime':
			unset($post_fields['post_excerpt']);
			unset($post_fields['url']);
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
		
		$add_link = !empty($attachment['url'])
			&& !preg_match("/^" . preg_quote(get_option('home'), '/') . "$/ix", $attachment['url']);
		
		if ( $add_link )
			$link = ' link="' . esc_url_raw($attachment['url']) . '"';
		else
			$link = '';
		
		switch ( $post->post_mime_type ) {
		case 'audio/mpeg':
			if ( $ext == 'mp3' )
				$html = '[media id="' . $send_id . '" type="mp3"' . $link . ']'
					. $attachment['post_title'] . '[/media]';
			else
				$html = '[media id="' . $send_id . '" type="m4a"]'
					. $attachment['post_title'] . '[/media]';
			break;
		
		case 'video/mpeg':
			if ( $ext == 'mp4' )
				$html = '[media id="' . $send_id . '" type="mp4"' . $link . ']'
					. $attachment['post_title'] . '[/media]';
			else
				$html = '[media id="' . $send_id . '" type="m4v"]'
					. $attachment['post_title'] . '[/media]';
			break;
		
		case 'video/x-flv':
			$html = '[media id="' . $send_id . '" type="flv"' . $link . ']'
				. $attachment['post_title'] . '[/media]';
			break;
		
		case 'video/quicktime':
			$html = '[media id="' . $send_id . '" type="' . $ext . '"]'
				. $attachment['post_title'] . '[/media]';
			break;
		
		default:
			if ( !preg_match("/^(?:application|text)\//", $post->post_mime_type) )
				break;
			$html = '[media id="' . $send_id . '" type="' . $ext . '"]'
				. $attachment['post_title'] . '[/media]';
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
						<span class="alignleft"><label for="insertonly[href]">' . __('Audio File URL') . '</label></span>
						<span class="alignright"><abbr title="required" class="required">*</abbr></span>
					</th>
					<td class="field"><input id="insertonly[href]" name="insertonly[href]" value="" type="text" aria-required="true"></td>
				</tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[title]">' . __('Title') . '</label></span>
						<span class="alignright"><abbr title="required" class="required">*</abbr></span>
					</th>
					<td class="field"><input id="insertonly[title]" name="insertonly[title]" value="" type="text" aria-required="true"></td>
				</tr>
				<tr><td></td><td class="help">' . __('Link text, e.g. &#8220;Still Alive by Jonathan Coulton&#8221;') . '</td></tr>
				<tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[url]">' . __('Link URL') . '</label></span>
					</th>
					<td class="field"><input id="insertonly[url]" name="insertonly[url]" value="" type="text"></td>
				</tr>
				<tr><td></td><td class="help">' . __('The link URL to which the player should direct users to (e.g. an affiliate link). (Applies to mp3 files and playlists only.)') . '</td></tr>
				<tr>
					<td></td>
					<td>
						<input type="submit" class="button" name="insertonlybutton" value="' . esc_attr__('Insert into Post') . '" />
					</td>
				</tr>
			</tbody></table>
		';
	} # type_url_form_audio()
	
	
	/**
	 * audio_send_to_editor_url()
	 *
	 * @param string $html
	 * @param string $href
	 * @param string $title
	 * @return string $html
	 **/

	function audio_send_to_editor_url($html, $href, $title) {
		$title = stripslashes($_POST['insertonly']['title']);
		$href = esc_url_raw(stripslashes($_POST['insertonly']['href']));
		if ( !$title )
			$title = basename($href, '.' . $ext);
		
		if ( preg_match("/\bm4a\b/i", $href) ) {
			$html = '[media href="' . $href . '" type="m4a"]' . $title . '[/media]';
		} elseif ( preg_match("/\b(mp3|rss2?|xml)\b/i", $href) ) {
			$link = trim(stripslashes($_POST['insertonly']['url']));
			$link = $link ? ( ' link="' . esc_url_raw($link) . '"' ) : '';
			$html = '[media href="' . $href . '" type="mp3"' . $link . ']' . $title . '[/media]';
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
			return '
			<table class="describe"><tbody>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[href]">' . __('Video URL') . '</label></span>
						<span class="alignright"><abbr title="required" class="required">*</abbr></span>
					</th>
					<td class="field"><input id="insertonly[href]" name="insertonly[href]" value="" type="text" aria-required="true"></td>
				</tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[title]">' . __('Title') . '</label></span>
						<span class="alignright"><abbr title="required" class="required">*</abbr></span>
					</th>
					<td class="field"><input id="insertonly[title]" name="insertonly[title]" value="" type="text" aria-required="true"></td>
				</tr>
				<tr><td></td><td class="help">' . __('Link text, e.g. &#8220;Lucy on YouTube&#8220;') . '</td></tr>
				<tr>
					<th valign="top" scope="row" class="label">
						<span class="alignleft"><label for="insertonly[url]">' . __('Link URL') . '</label></span>
					</th>
					<td class="field"><input id="insertonly[url]" name="insertonly[url]" value="" type="text"></td>
				</tr>
				<tr><td></td><td class="help">' . __('The link URL to which the player should direct users to (e.g. an affiliate link). (Applies to flv and mp4 files only.)') . '</td></tr>
				<tr>
					<td></td>
					<td>
						<input type="submit" class="button" name="insertonlybutton" value="' . esc_attr__('Insert into Post') . '" />
					</td>
				</tr>
			</tbody></table>
		';
	} # type_url_form_video()
	
	
	/**
	 * video_send_to_editor_url()
	 *
	 * @param string $html
	 * @param string $href
	 * @param string $title
	 * @return string $html
	 **/

	function video_send_to_editor_url($html, $href, $title) {
		$title = stripslashes($_POST['insertonly']['title']);
		$href = esc_url_raw(stripslashes($_POST['insertonly']['href']));
		if ( !$title )
			$title = basename($href, '.' . $ext);
		
		if ( preg_match("/^https?:\/\/(?:www\.)youtube.com\//i", $href) ) {
			$href = parse_url($href);
			$v = $href['query'];
			parse_str($v, $v);
			if ( empty($v['v']) )
				return $html;
			$html = '[media href="' . $href . '" type="youtube"]' . $title . '[/media]';
		} elseif ( preg_match("/\bm4v\b/i", $href) ) {
			$html = '[media href="' . $href . '" type="m4v"]' . $title . '[/media]';
		} elseif ( preg_match("/\b(mp4|rss2?|xml)\b/i", $href) ) {
			$link = trim(stripslashes($_POST['insertonly']['url']));
			$link = $link ? ( ' link="' . esc_url_raw($link) . '"' ) : '';
			$html = '[media href="' . $href . '" type="mp4"' . $link . ']' . $title . '[/media]';
		}
		
		return $html;
	} # video_send_to_editor_url()
	
	
	/**
	 * file_send_to_editor_url()
	 *
	 * @param string $html
	 * @param string $href
	 * @param string $title
	 * @return string $html
	 **/

	function file_send_to_editor_url($html, $href, $title) {
		$title = stripslashes($_POST['insertonly']['title']);
		$href = esc_url_raw(stripslashes($_POST['insertonly']['href']));
		
		if ( preg_match("/\.[a-z0-9]+$/i", $href, $ext) ) {
			$ext = end($ext);
			$type = ' type="' . strtolower($ext) . '"';
			if ( !$title )
				$title = basename($href, '.' . $ext);
		} else {
			$type = '';
			if ( !$title )
				$title = basename($href);
		}
		
		$html = '[media href="' . $href . '"' . $type . ']' . $title . '[/media]';
		
		return $html = '[media href="' . $href . '" type=""]' . $title . '[/media]';
	} # file_send_to_editor_url()
} # mediacaster_admin
?>