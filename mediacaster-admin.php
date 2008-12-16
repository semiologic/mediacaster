<?php

class mediacaster_admin
{
	#
	# init()
	#

	function init()
	{
		add_action('admin_menu', array('mediacaster_admin', 'add_admin_page'));
		add_action('admin_menu', array('mediacaster_admin', 'add_meta_boxes'), 20);
		
		add_action('save_post', array('mediacaster_admin', 'update_path'), 20);
		add_action('save_post', array('mediacaster_admin', 'save_media'), 30);
		
		add_action('admin_head', array('mediacaster_admin', 'display_js_files'), 0);
		add_filter('admin_footer', array('mediacaster_admin', 'quicktag'));
		add_filter('mce_external_plugins', array('mediacaster_admin', 'editor_plugin'), 5);
		add_filter('mce_buttons', array('mediacaster_admin', 'editor_button'));
		
		if ( get_option('mediacaster') === false )
		{
			$options = mediacaster::regen_options();
		}
	} # init()


	#
	# add_meta_boxes()
	#

	function add_meta_boxes()
	{
		add_meta_box('mediacaster', 'Mediacaster', array('mediacaster_admin', 'display_media'), 'post', 'normal');
		add_meta_box('mediacaster', 'Mediacaster', array('mediacaster_admin', 'display_media'), 'page', 'normal');
	} # add_meta_boxes()


	#
	# update_path()
	#

	function update_path($post_ID)
	{
		$old = get_post_meta($post_ID, '_mediacaster_path', true);
		$new = mediacaster::get_path($post_ID);

		if ( $old && $old != $new )
		{
			mediacaster_admin::create_path(dirname($new));

			@rename(ABSPATH . $old, ABSPATH . $new);
			#die();
		}

		delete_post_meta($post_ID, '_mediacaster_path');
		add_post_meta($post_ID, '_mediacaster_path', $new, true);

		return $post_ID;
	} # update_path()


	#
	# save_media()
	#

	function save_media($post_ID)
	{
		global $wpdb;
		
		$path = mediacaster::get_path($post_ID);
		mediacaster_admin::create_path($path);
		#var_dump($path);
		#die;

		if ( current_user_can('upload_files') )
		{
			foreach ( array_keys((array) $_POST['delete_media']) as $key )
			{
				$key = stripslashes(html_entity_decode(urldecode($key)));

				$ext = pathinfo($key, PATHINFO_EXTENSION);

				@unlink(ABSPATH . $path . $key);
				unset($_POST['update_media'][$key]);

				$post_content = $wpdb->get_var("
						SELECT	post_content
						FROM	$wpdb->posts
						WHERE	ID = " . intval($post_ID) . "
						");
				
				$post_content = str_replace('[media:' . $key . ']', '', $post_content);
				
				$wpdb->query("
						UPDATE	$wpdb->posts
						SET		post_content = '" . $wpdb->escape($post_content) . "'
						WHERE	ID = " . intval($post_ID) . "
						");

				if ( in_array(strtolower($ext), array('flv', 'swf', 'mov', 'mp4', 'm4v', 'm4a')) )
				{
					$image = basename($key, '.' . $ext);

					if ( defined('GLOB_BRACE') )
					{
						if ( $image = glob(ABSPATH . $path . $image . '.{jpg,jpeg,png}', GLOB_BRACE) )
						{
							$image = current($image);
							@unlink($image);
						}
					}
					else
					{
						if ( $image = glob(ABSPATH . $path . $image . '.jpg') )
						{
							$image = current($image);
							@unlink($image);
						}
					}
				}
			}

			foreach ( (array) $_POST['update_media'] as $old => $new )
			{
				$old = stripslashes(html_entity_decode(urldecode($old)));
				$new = strip_tags(stripslashes($new));
				$new = str_replace(array("<", ">", "&", "%", "/"), "", $new);
				$new = preg_replace("/\s+/", " ", $new);
				
				if ( $old != $new )
				{
					@rename(ABSPATH . $path . $old, ABSPATH . $path . $new);
					
					$post_content = $wpdb->get_var("
							SELECT	post_content
							FROM	$wpdb->posts
							WHERE	ID = " . intval($post_ID) . "
							");
					
					$post_content = str_replace('[media:' . $old . ']', '[media:' . $new . ']', $post_content);
					
					$wpdb->query("
							UPDATE	$wpdb->posts
							SET		post_content = '" . $wpdb->escape($post_content) . "'
							WHERE	ID = " . intval($post_ID) . "
							");

					$ext = pathinfo($old, PATHINFO_EXTENSION);

					if ( in_array(strtolower($ext), array('flv', 'swf', 'mov', 'mp4', 'm4v', 'm4a')) )
					{
						$old_name = basename($old, '.' . $ext);
						$new_name = basename($new, '.' . $ext);

						if ( defined('GLOB_BRACE') )
						{
							if ( $image = glob(ABSPATH . $path . $old_name . '.{jpg,jpeg,png}', GLOB_BRACE) )
							{
								$image = current($image);

								$ext = pathinfo($image, PATHINFO_EXTENSION);
								$ext = strtolower($ext);

								$old_name = basename($image, '.' . $ext);

								@rename(ABSPATH . $path . $old_name . '.' . $ext, ABSPATH . $path . $new_name . '.' . $ext);
							}
						}
						else
						{
							if ( $image = glob(ABSPATH . $path . $old_name . '.jpg') )
							{
								$image = current($image);

								$ext = pathinfo($image, PATHINFO_EXTENSION);
								$ext = strtolower($ext);

								$old_name = basename($image, '.' . $ext);

								@rename(ABSPATH . $path . $old_name . '.' . $ext, ABSPATH . $path . $new_name . '.' . $ext);
							}
						}
					}
				}
			}

			if ( $_FILES['new_media'] )
			{
				$tmp_name = $_FILES['new_media']['tmp_name'];
				$new_name = strip_tags(stripslashes($_FILES['new_media']['name']));
				$new_name = str_replace(array("<", ">", "&", "%", "/"), "", $new_name);
				$new_name = preg_replace("/\s+/", " ", $new_name);
				$new_name = ABSPATH . $path . $new_name;
				
				$ext = pathinfo($new_name, PATHINFO_EXTENSION);
				$new_name = str_replace('.' . $ext, '.' . strtolower($ext), $new_name);
				$ext = strtolower($ext);

				if ( in_array(
						$ext,
						array(
							'jpg', 'jpeg', 'png',
							'mp3', 'm4a',
							'mp4', 'm4v', 'mov', 'flv', 'swf',
							'pdf', 'zip', 'gz'
							)
						)
					)
				{
					@move_uploaded_file($tmp_name, $new_name);
					@chmod($new_name, 0666);
				}
			}

			#echo '<pre>';
			#var_dump($_POST['update_media']);
			#var_dump($_FILES['new_media']);
			#echo '</pre>';
			#die;
		}

		return $post_ID;
	} # save_media()


	#
	# display_media()
	#

	function display_media()
	{
		$post_ID = isset($GLOBALS['post_ID']) ? $GLOBALS['post_ID'] : $GLOBALS['temp_ID'];

		#echo '<pre>';
		#var_dump($post_ID);
		#echo '</pre>';

		if ( $post_ID > 0 )
		{
			echo '<p>'
				. __('To attach media to this entry, either use the file uploader below or drop files into the following folder using ftp software:')
				. '</p>';

			$path = mediacaster::get_path($post_ID);

			echo '<p style="margin-left: 2em;">[WordPressFolder]<strong>/' . $path . '</strong></p>';

			$files = mediacaster::get_files($path);

			$cover = mediacaster::get_cover($path);

			if ( $files || strpos($cover, $path) !== false )
			{
				echo '<p>'
					. __('Media files currently include:')
					. '</p>';

				foreach ( (array) $files as $key => $file )
				{
					$name = $key;
					$key = str_replace(
							array("\\", "'"),
							array("\\\\", "\\'"),
							htmlentities($key)
							);
					$key = urlencode($key);
					
					$ext = pathinfo($name, PATHINFO_EXTENSION);
					
					if ( in_array($ext, array('flv', 'swf', 'mov', 'mp4', 'm4a', 'm4v')) )
					{
						$file_name = basename($name, '.' . $ext);
						
						if ( defined('GLOB_BRACE') )
						{
							if ( $img_cover = glob(ABSPATH . $path . $file_name . '.{jpg,jpeg,png}', GLOB_BRACE) )
							{
								$img_cover = current($img_cover);
							}
							else
							{
								$img_cover = dirname(__FILE__) . '/tinymce/images/video.gif';
							}
						}
						else
						{
							if ( $img_cover = glob(ABSPATH . $path . $file_name . '.jpg') )
							{
								$img_cover = current($img_cover);
							}
							else
							{
								$img_cover = dirname(__FILE__) . '/tinymce/images/video.gif';
							}
						}
					}
					else
					{
						$img_cover = false;
					}

					echo '<div style="margin: 1em 0px;">'
						. ( $img_cover
							? ( '<img src="'
								. str_replace(ABSPATH, trailingslashit(get_option('siteurl')), $img_cover)
								. '" />' . '<br />'
								)
							: ''
							)
						. '<input type="text" tabindex="4" style="width: 320px;"'
							. ' name=update_media[' . $key . ']'
							. ' value="' . htmlentities($name) . '"'
							. ( !current_user_can('upload_files') ? ' disabled="disabled"' : '' )
							. ' />'
						. '&nbsp;'
						. '<label>'
							. '<input type="checkbox" tabindex="4"'
								. ' name=delete_media[' . $key . ']'
								. ( !current_user_can('upload_files') ? ' disabled="disabled"' : '' )
								. ' />'
							. '&nbsp;'
							. __('Delete')
							. '</label>'
						. '</div>' . "\n";
				}

				if ( strpos($cover, $path) !== false )
				{
					$key = basename($cover);
					$key = str_replace(
							array("\\", "'"),
							array("\\\\", "\\'"),
							htmlentities($key)
							);
					$key = urlencode($key);

					echo '<div style="margin: 1em 0px;">'
						. '<img src="'
							. trailingslashit(get_option('siteurl')) . $cover
							. '" />' . '<br />'
						. '<input type="text" style="width: 320px;" tabindex="4"'
							. ' value="Entry-specific mp3 playlist cover"'
							. ' disabled="disabled"'
							. ' />'
						. '&nbsp;'
						. '<label>'
							. '<input type="checkbox" tabindex="4"'
								. ' name=delete_media[' . $key . ']'
								. ( !current_user_can('upload_files') ? ' disabled="disabled"' : '' )
								. ' />'
							. '&nbsp;'
							. __('Delete')
							. '</label>'
						. '</div>';
				}
				
				if ( current_user_can('upload_files') )
				{
					echo '<p>'
						.  '<input type="button" class="button" tabindex="4"'
						. ' value="' . __('Save Changes') . '"'
						. ' onclick="return form.save.click();"'
						. ' />'
						. '</p>';
				}
			}
			else
			{
				mediacaster_admin::create_path($path);
			}
		}

		if ( current_user_can('upload_files') )
		{
			echo '<p>'
				. __('Enter a file to add new media (this can take a while if the file is large)') . ':'
				. '</p>';

			echo '<ul>'
				. '<li>'
					. '<input type="file" style="width: 400px;" tabindex="4"'
					. ' id="new_media" name="new_media"'
					. ' />'
					. ' '
					. '<input type="button"'
					. ' value="' . __('Upload') . '"'
					. ' onclick="return form.save.click();"'
					. ' />'
				. '</li>'
				. '</ul>';

			echo '<p>'
				. __('Tips') . ':'
				. '</p>'
				. '<ul>'
				. '<li>'
				. __('Supported formats include .mp3, .flv, .swf, .m4a, .mp4, .m4v, .mov, .pdf, .zip and .gz.')
				. '</li>'
				. '<li>'
				. __('Upload a .jpg or .png image named after your video to use it as the cover for that video. <i>e.g.</i> myvideo.jpg for myvideo.swf or myvideo.mov.')
				. '</li>'
				. '<li>'
				. __('Upload a cover.jpg or cover.png image if you with to override the default cover for your podcast playlist.')
				. '</li>'
				. '<li>'
				. __('Your media folder must be writable by the server for any of this to work at all.')
				. '</li>'
				. '<li>'
				. __('Maximum file size is 32M. If large files won\'t upload on your server, have your host increase its upload_max_filesize parameter.')
				. '</li>'
				. '<li>'
				. __('If you\'re uploading <a href="http://go.semiologic.com/camtasia">Camtasia</a> videos, upload <em>only</em> the video file (swf, flv, mov...). The other files created by Camtasia are for use in a standalone web page.')
				. '</li>'
				. '</ul>';

			if ( !defined('GLOB_BRACE') )
			{
				echo '<p>' . __('Notice: GLOB_BRACE is an undefined constant on your server. Non .jpg images will be ignored.') . '</p>';
			}
		}
	} # display_media()


	#
	# create_path()
	#

	function create_path($path)
	{
		if ( $path )
		{
			if ( !file_exists(ABSPATH . $path) )
			{
				$parent = dirname($path);

				mediacaster_admin::create_path($parent);

				if ( is_writable(ABSPATH . $parent) )
				{
					@mkdir(ABSPATH . $path);
					@chmod(ABSPATH . $path, 0777);
				}
			}
		}
	} # create_path()


	#
	# add_admin_page()
	#

	function add_admin_page()
	{
		add_options_page(
			__('Mediacaster'),
			__('Mediacaster'),
			'manage_options',
			__FILE__,
			array('mediacaster_admin', 'display_admin_page')
			);
	} # add_admin_page()


	#
	# strip_tags_rec()
	#

	function strip_tags_rec($input)
	{
		if ( is_array($input) )
		{
			foreach ( array_keys($input) as $key )
			{
				$input[$key] = mediacaster_admin::strip_tags_rec($input[$key]);
			}
		}
		else
		{
			$input = strip_tags($input);
		}

		return $input;
	} # strip_tags_rec()


	#
	# update_options()
	#

	function update_options()
	{
		check_admin_referer('mediacaster');

		if ( isset($_POST['delete_cover']) )
		{
			if ( defined('GLOB_BRACE') )
			{
				if ( $cover = glob(ABSPATH . 'media/cover{,-*}.{jpg,jpeg,png}', GLOB_BRACE) )
				{
					$cover = current($cover);
					@unlink($cover);
				}
			}
			else
			{
				if ( $cover = glob(ABSPATH . 'media/cover-*.jpg') )
				{
					$cover = current($cover);
					@unlink($cover);
				}
			}
		}

		if ( isset($_POST['delete_itunes']) )
		{
			$options = get_option('mediacaster');

			$itunes_image = ABSPATH . 'wp-content/itunes/' . $options['itunes']['image']['name'];

			@unlink($itunes_image);
		}

		$options = $_POST['mediacaster'];

		$options = mediacaster_admin::strip_tags_rec($options);
		
		$options['player']['center'] = isset($options['player']['center']);

		if ( @ $_FILES['mediacaster']['name']['itunes']['image']['new'] )
		{
			$name =& $_FILES['mediacaster']['name']['itunes']['image']['new'];
			$tmp_name =& $_FILES['mediacaster']['tmp_name']['itunes']['image']['new'];
			
			$name = strip_tags(stripslashes($name));

			$ext = pathinfo($name, PATHINFO_EXTENSION);

			if ( !in_array(strtolower($ext), array('jpg', 'jpeg', 'png')) )
			{
				echo '<div class="error">'
					. "<p>"
						. "<strong>"
						. __('Invalid File Type.')
						. "</strong>"
					. "</p>\n"
					. "</div>\n";
			}
			else
			{
				$options['itunes']['image']['counter'] = $options['itunes']['image']['counter'] + 1;
				$options['itunes']['image']['name'] = $options['itunes']['image']['counter'] . '_' . $name;

				$new_name = ABSPATH . 'wp-content/itunes/' . $options['itunes']['image']['name'];

				@mkdir(ABSPATH . 'wp-content/itunes');
				@chmod(ABSPATH . 'wp-content/itunes', 0777);

				@move_uploaded_file($tmp_name, $new_name);
				@chmod($new_name, 0666);
			}
		}

		if ( @ $_FILES['new_cover']['name'] )
		{
			$name =& $_FILES['new_cover']['name'];
			$tmp_name =& $_FILES['new_cover']['tmp_name'];
			
			$name = strip_tags(stripslashes($name));

			$ext = pathinfo($name, PATHINFO_EXTENSION);

			if ( !in_array(strtolower($ext), array('jpg', 'jpeg', 'png')) )
			{
				echo '<div class="error">'
					. "<p>"
						. "<strong>"
						. __('Invalid File Type.')
						. "</strong>"
					. "</p>\n"
					. "</div>\n";
			}
			else
			{
				if ( defined('GLOB_BRACE') )
				{
					if ( $cover = glob(ABSPATH . 'media/cover{,-*}.{jpg,jpeg,png}', GLOB_BRACE) )
					{
						$cover = current($cover);
						@unlink($cover);
					}
				}
				else
				{
					if ( $cover = glob(ABSPATH . 'media/cover-*.jpg') )
					{
						$cover = current($cover);
						@unlink($cover);
					}
				}

				$ext = pathinfo($name, PATHINFO_EXTENSION);

				$entropy = get_option('sem_entropy');

				$entropy = intval($entropy) + 1;

				update_option('sem_entropy', $entropy);

				$new_name = ABSPATH . 'media/cover-' . $entropy . '.' . $ext;

				@move_uploaded_file($tmp_name, $new_name);
				@chmod($new_name, 0666);
			}
		}

		#echo '<pre>';
		#var_dump($options);
		#echo '</pre>';

		update_option('mediacaster', $options);
	} # update_options()


	#
	# display_admin_page()
	#

	function display_admin_page()
	{
		echo '<form enctype="multipart/form-data" method="post" action="">' . "\n";

		echo '<input type="hidden" name="MAX_FILE_SIZE" value="8000000">' . "\n";

		if ( $_POST['update_mediacaster_options'] )
		{
			echo '<div class="updated">' . "\n"
				. '<p>'
					. '<strong>'
					. __('Settings saved.')
					. '</strong>'
				. '</p>' . "\n"
				. '</div>' . "\n";

			mediacaster_admin::update_options();
		}

		$options = get_option('mediacaster');
		#$options = false;

		if ( $options == false )
		{
			$options = mediacaster::regen_options();
		}

		$site_url = trailingslashit(get_option('siteurl'));

		echo '<div class="wrap">' . "\n"
			. '<h2>'. __('Mediacaster Settings') . '</h2>' . "\n"
			. '<input type="hidden" name="update_mediacaster_options" value="1" />' . "\n";

		if ( function_exists('wp_nonce_field') ) wp_nonce_field('mediacaster');

		echo '<h3>'
				. __('Media Player')
				. '</h3>' . "\n";

		echo '<table class="form-table">';
		
		echo '<tr valign="top">'
			. '<th scope="row">'
			. __('Player Position')
			. '</th>'
			. '<td>'
			. '<label for="mediacaster[player][position][top]">'
			. '<input type="radio"'
				. ' id="mediacaster[player][position][top]" name="mediacaster[player][position]"'
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
			. '<label for="mediacaster[player][position][bottom]">'
			. '<input type="radio"'
				. ' id="mediacaster[player][position][bottom]" name="mediacaster[player][position]"'
				. ' value="bottom"'
				. ( $options['player']['position'] == 'bottom'
					? ' checked="checked"'
					: ''
					)
				. ' />'
			. '&nbsp;'
			. __('Bottom')
			. '</label>'
			. ' '
			. '<label for="mediacaster[player][center]">'
			. '<input type="checkbox"'
				. ' id="mediacaster[player][center]" name="mediacaster[player][center]"'
				. ( $options['player']['center']
					? ' checked="checked"'
					: ''
					)
				. ' />'
			. '&nbsp;'
			. __('Center-Aligned')
			. '</label>'
			. '</td>'
			. '</tr>' . "\n";

		echo '<tr valign="top">'
			. '<th scope="row">'
			. '<label for="mediacaster[player][width]">'
				. __('Player Width x Height') . ':'
			. '</label>'
			. '</th>'
			. '<td>'
			. '<input type="text"'
				. ' id="mediacaster[player][width]" name="mediacaster[player][width]"'
				. ' value="'
					. ( $options['player']['width']
						? $options['player']['width']
						: 320
						)
					 . '"'
				. ' />' . "\n"
			. ' x '
			. '<input type="text"'
				. ' id="mediacaster[player][height]" name="mediacaster[player][height]"'
				. ' value="'
					. ( ( isset($options['player']['height']) && $options['player']['height'] )
						? $options['player']['height']
						: intval($options['player']['width'] * 240 / 320 )
						)
					 . '"'
				. ' />' . "\n"
			. '</td>' . "\n"
			. '</tr>';


		if ( defined('GLOB_BRACE') )
		{
			if ( $cover = glob(ABSPATH . 'media/cover{,-*}.{jpg,jpeg,png}', GLOB_BRACE) )
			{
				$cover = current($cover);
			}
		}
		else
		{
			if ( $cover = glob(ABSPATH . 'media/cover-*.jpg') )
			{
				$cover = current($cover);
			}
		}

		echo '<tr valign="top">'
			. '<th scope="row">'
				. __('MP3 Playlist Cover') . ':'
			. '</th>' . "\n"
			. '<td>';

		if ( file_exists($cover) )
		{
			echo '<div style="margin-botton: 6px;">';
			
			echo '<img src="'
					. str_replace(ABSPATH, $site_url, $cover)
					. '"'
				. ' />' . "\n"
				. '<br />' . "\n";
				
			if ( is_writable($cover) )
			{
				echo '<label for="delete_cover">'
					. '<input type="checkbox"'
						. ' id="delete_cover" name="delete_cover"'
						. ' style="text-align: left; width: auto;"'
						. ' />'
					. '&nbsp;'
					. __('Delete')
					. '</label>';
			}
			else
			{
				echo __('This cover is not writable by the server.');
			}
			
			echo '</div>';
		}

		echo '<div style="margin-botton: 6px;">'
			. '<label for="new_cover">'
				. __('New Image (jpg or png)') . ':'
			. '</label>'
			. '<br />' . "\n"
			. '<input type="file" style="width: 480px;"'
				. ' id="new_cover" name="new_cover"'
				. ' />'
			. '</div>' . "\n";

		if ( !defined('GLOB_BRACE') )
		{
			echo '<p>' . __('Notice: GLOB_BRACE is an undefined constant on your server. Non .jpg images will be ignored.') . '</p>';
		}
		
		echo '</td>'
			. '</tr>';
			
		echo '</table>';

		echo '<p class="submit">'
			. '<input type="submit"'
				. ' value="' . attribute_escape(__('Save Changes')) . '"'
				. ' />'
			. '</p>' . "\n";


		echo '<h3>'
				. __('Enclosures')
				. '</h3>' . "\n";

		echo '<table class="form-table">';
		
		echo '<tr valign="top">'
			. '<th scope="row">'
			. '<p>'
			. __('Preferences')
			. '</p>'
			. '</th>'
			. '<td>';
		
		echo '<p>'
			. __('Media files you include using Mediacaster will get listed in your site\'s RSS feed as enclosures (the term itself is blogging jargon). This lets feed readers and various devices (e.g. an iPod) know media files are attached, and process them accordingly.')
			. '</p>';

		echo '<p>'
			. '<input type="radio"'
				. ' id="mediacaster[enclosures][none]" name="mediacaster[enclosures]"'
				. ' value=""'
				. ( $options['enclosures'] == ''
					? ' checked="checked"'
					: ''
					)
				. ' />' . "\n"
				. '<label for="mediacaster[enclosures][none]">'
				. __('List enclosures in machine readable format only, for use in RSS readers and iPods.')
				. '</label>'
			. '</p>' . "\n";

		echo '<p>'
			. '<input type="radio"'
				. ' id="mediacaster[enclosures][all]" name="mediacaster[enclosures]"'
				. ' value="all"'
				. ( $options['enclosures'] == 'all'
					? ' checked="checked"'
					: ''
					)
				. ' />' . "\n"
				. '<label for="mediacaster[enclosures][all]">'
				. __('List enclosures in machine readable format, and as download links in human readable format at the end of each post.')
				. '</label>'
			. '</p>' . "\n";
		
		echo '</td>'
			. '</tr>';

		if ( !$options['captions']['enclosures'] )
		{
			$options['captions']['enclosures'] = __('Enclosures');
		}

		echo '<tr valign="top">'
			. '<th scope="row">'
			. '<label for="mediacaster[captions][enclosures]">'
			. __('Enclosure Caption')
			. '</label>'
			. '</th>'
			. '<td>'
			. '<input type="text" style="width: 480px;"'
				. ' id="mediacaster[captions][enclosures]" name="mediacaster[captions][enclosures]"'
				. ' value="' . attribute_escape($options['captions']['enclosures']) . '"'
				. ' />' . "\n"
			. '</td>'
			. '</tr>' . "\n";
		
		echo '</table>';

		echo '<p class="submit">'
			. '<input type="submit"'
				. ' value="' . attribute_escape(__('Save Changes')) . '"'
				. ' />'
			. '</p>' . "\n";


		echo '<h3>'
			. __('iTunes')
			. '</h3>' . "\n";

		if ( class_exists('podPress_class') )
		{
			echo '<p>'
				. __('PodPress was detected. Configure itunes-related fields in your PodPress options')
				. '</p>' . "\n";
		}
		else
		{
			echo '<table class="form-table">';
			
			echo '<tr valign="top">'
				. '<th scope="row">'
				. '<label for="mediacaster[itunes][author]">'
					. __('Author')
					. '</label>'
				. '</th>'
				. '<td>'
				. '<input type="text" style="width: 480px;"'
					. ' id="mediacaster[itunes][author]" name="mediacaster[itunes][author]"'
					. ' value="' . attribute_escape($options['itunes']['author']) . '"'
					. ' />' . "\n"
				. '</td>'
				. '</tr>' . "\n";


			echo '<tr valign="top">'
				. '<th scope="row">'
				. '<label for="mediacaster[itunes][summary]">'
					. __('Summary') . ':'
					. '</label>'
				. '</th>'
				. '<td>'
				. '<textarea style="width: 480px; height: 40px;"'
					. ' id="mediacaster[itunes][summary]" name="mediacaster[itunes][summary]"'
					. ' >' . "\n"
					. $options['itunes']['summary']
					. '</textarea>' . "\n"
				. '</td>'
				. '</tr>' . "\n";


			echo '<tr valign="rop">'
				. '<th scope="row">'
					. __('Categories')
				. '</th>'
				. '<td>';

			for ( $i = 1; $i <= 3; $i++ )
			{
				echo '<select style="width: 480px;"'
						. ' id="mediacaster[itunes][category][' . $i . ']" name="mediacaster[itunes][category][' . $i . ']"'
						. ' >' . "\n"
					. '<option value="">' . __('Select...') . '</option>' . "\n";

				foreach ( mediacaster_admin::get_itunes_categories() as $category )
				{
					$category = $category;

					echo '<option'
						. ' value="' . attribute_escape($category) . '"'
						. ( ( $category == $options['itunes']['category'][$i] )
							? ' selected="selected"'
							: ''
							)
						. '>'
						. attribute_escape($category)
						. '</option>' . "\n";
				}
				echo '</select>'
				 	. '<br />'. "\n";
			}

			echo '</td>'
			 	. '</tr>' . "\n";


			echo '<tr valign="top">'
				. '<th scope="row">'
					. __('Itunes Cover') . ':'
				. '</th>'
				. '<td>'
				. '<input type="hidden"'
					. ' id="mediacaster[itunes][image][counter]" name="mediacaster[itunes][image][counter]"'
					. ' value="' . intval($options['itunes']['image']['counter']) . '"'
					. ' />' . "\n"
				. '<input type="hidden"'
					. ' id="mediacaster[itunes][image][name]" name="mediacaster[itunes][image][name]"'
					. ' value="' . attribute_escape($options['itunes']['image']['name']) . '"'
					. ' />' . "\n";
			
			if ( file_exists(ABSPATH . 'wp-content/itunes/' . $options['itunes']['image']['name']) )
			{
				echo '<div style="margin-bottom: 6px;">';

				echo '<img src="'
							. $site_url
							. 'wp-content/itunes/'
							. attribute_escape($options['itunes']['image']['name'])
							. '"'
						. ' />' . '<br />' . "\n";
				
				if ( is_writable(ABSPATH . 'wp-content/itunes/' . $options['itunes']['image']['name']) )
				{
					echo '<label for="delete_itunes">'
						. '<input type="checkbox"'
							. ' id="delete_itunes" name="delete_itunes"'
							. ' style="text-align: left; width: auto;"'
							. ' />'
						. '&nbsp;'
						. __('Delete')
						. '</label>';
				}
				elseif ( file_exists(ABSPATH . 'wp-content/itunes/' . $options['itunes']['image']['name']) )
				{
					echo __('This cover is not writable by the server.');
				}
				
				echo '</div>';
			}

			echo '<label for="mediacaster[itunes][image][new]">'
					. __('New Image (jpg or png)') . ':'
					. '</label>'
				. '<br />' . "\n"
				. '<input type="file" style="width: 480px;"'
					. ' id="mediacaster[itunes][image][new]" name="mediacaster[itunes][image][new]"'
					. ' />' . "\n";
			
			echo '</td>'
				. '</tr>';
			

			echo '<tr valign="top">'
				. '<th scope="row">'
					. '<label for="mediacaster[itunes][explicit]">'
					. __('Explicit') . ':'
					. '</label>'
				. '</th>'
				. '<td>'
				. '<select style="width: 480px;"'
					. ' id="mediacaster[itunes][explicit]" name="mediacaster[itunes][explicit]"'
					. ' >' . "\n";

			foreach ( array('Yes', 'No', 'Clean') as $answer )
			{
				$answer = attribute_escape($answer);

				echo '<option'
					. ' value="' . $answer . '"'
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
					. '<label for="mediacaster[itunes][block]">'
					. __('Block iTunes') . ':'
					. '</label>'
				. '</th>'
				. '<td>'
				. '<select style="width: 480px;"'
					. ' id="mediacaster[itunes][block]" name="mediacaster[itunes][block]"'
					. ' >' . "\n";

			foreach ( array('Yes', 'No') as $answer )
			{
				$answer = attribute_escape($answer);

				echo '<option'
					. ' value="' . $answer . '"'
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
				. '<label for="mediacaster[itunes][copyright]">'
					. __('Copyright') . ':'
					. '</label>'
				. '</th>'
				. '<td>'
				. '<textarea style="width: 480px; height: 40px;"'
					. ' id="mediacaster[itunes][copyright]" name="mediacaster[itunes][copyright]"'
					. ' >' . "\n"
					. $options['itunes']['copyright']
					. '</textarea>' . "\n"
				. '</td>'
				. '</tr>' . "\n";
				
			echo '</table>';

			echo '<p class="submit">'
				. '<input type="submit"'
					. ' value="' . attribute_escape(__('Save Changes')) . '"'
					. ' />'
				. '</p>' . "\n";;
		}

		echo '</div>' . "\n";

		echo '</form>' . "\n";
	} # display_admin_page()


	#
	# get_itunes_categories()
	#

	function get_itunes_categories()
	{
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
	
	
	#
	# quicktag()
	#

	function quicktag()
	{
		if ( !$GLOBALS['editing'] ) return;

?><script type="text/javascript">
if ( document.getElementById('quicktags') )
{
	function mediacasterAddMedia(elt)
	{
		if ( elt.value == 'media:url' )
		{
			var url = prompt('<?php echo __('Enter the url of a media file'); ?>', 'http://');
		
			if ( url && url != 'http://' )
			{
				edInsertContent(edCanvas, '[media:' + url + ']');
			}
		}
		else if ( elt.value != '' )
		{
			edInsertContent(edCanvas, '[media:' + elt.value + ']');
		}

		elt.selectedIndex = 0;
	} // mediacasterAddMedia()

	var mediacasterQTButton = '<select class="ed_button" style="width: 100px;" onchange="return mediacasterAddMedia(this);">';

	mediacasterQTButton += '<option value="" selected="selected"><?php echo __('Mediacaster'); ?><\/option>';
	mediacasterQTButton += '<option value="media:url"><?php echo __('Enter a url'); ?><\/option>';

	var i;
	var label;
	var value;

	for ( i = 0; i < mediacasterFiles.length; i++ )
	{
		label = new String(mediacasterFiles[i].label);
		value = new String(mediacasterFiles[i].value);
		value = value.replace("\"", "&quot;");
	
		mediacasterQTButton += '<option value="' + value + '">' + label + '<\/option>';
	}

	mediacasterQTButton += '<\/select>';

	document.getElementById('ed_toolbar').innerHTML += mediacasterQTButton;
} // end if
</script>
<?php
	} # quicktag()


	#
	# display_js_files()
	#

	function display_js_files()
	{
		if ( !$GLOBALS['editing'] ) return;

		global $post;

		$path = mediacaster::get_path($post);
		$files = mediacaster::get_files($path);

		$i = 0;
		$js_options = array();

		foreach ( array_keys($files) as $file )
		{
			$js_option = "mediacasterFiles['"
				. $i++
				. "']"
				. "= {"
				. "label: '" . str_replace(
						array("\\", "'"),
						array("\\\\", "\\'"),
						preg_replace("/\.([^.]+)$/U", " ($1)", $file)
					) . "', "
				. "value: '" . str_replace(
						array("\\", "'"),
						array("\\\\", "\\'"),
						$file
					) . "'"
				. "};";
			#var_dump($js_option);
			$js_options[] = $js_option;
		}
?><script type="text/javascript">
var mediacasterFiles = new Array();
<?php echo implode("\n", $js_options) . "\n"; ?>
document.mediacasterFiles = mediacasterFiles;
//alert(document.mediacasterFiles);
</script>
<?php
	} # display_js_files()


	#
	# editor_button()
	#
	
	function editor_button($buttons)
	{
		if ( !empty($buttons) )
		{
			$buttons[] = '|';
		}
		
		$buttons[] = 'mediacaster';
		
		return $buttons;
	} # editor_button()
	

	#
	# editor_plugin()
	#

	function editor_plugin($plugin_array)
	{
		if ( get_user_option('rich_editing') == 'true')
		{
			$path = plugin_basename(__FILE__);

			$plugin = trailingslashit(get_option('siteurl'))
				. 'wp-content/plugins/'
				. ( strpos($path, '/') !== false
					? ( dirname($path) . '/' )
					: ''
					)
				. 'tinymce/editor_plugin.js';
				
			$plugin_array['mediacaster'] = $plugin;
		}

		return $plugin_array;
	} # editor_plugin()
} # mediacaster_admin

mediacaster_admin::init();





if ( !function_exists('ob_multipart_entry_form') ) :
#
# ob_multipart_entry_form_callback()
#

function ob_multipart_entry_form_callback($buffer)
{
	$buffer = str_replace(
		'<form name="post"',
		'<form enctype="multipart/form-data" name="post"',
		$buffer
		);

	return $buffer;
} # ob_multipart_entry_form_callback()


#
# ob_multipart_entry_form()
#

function ob_multipart_entry_form()
{
	if ( $GLOBALS['editing'] )
	{
		ob_start('ob_multipart_entry_form_callback');
	}
} # ob_multipart_entry_form()

add_action('admin_head', 'ob_multipart_entry_form');


#
# add_file_max_size()
#

function add_file_max_size()
{
	echo  "\n" . '<input type="hidden" name="MAX_FILE_SIZE" value="32000000" />' . "\n";
}

add_action('edit_form_advanced', 'add_file_max_size');
add_action('edit_page_form', 'add_file_max_size');
endif;
?>