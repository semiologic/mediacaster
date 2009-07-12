<?php
/*
Plugin Name: Mediacaster
Plugin URI: http://www.semiologic.com/software/mediacaster/
Description: Lets you add podcasts, videos, and formatted download links in your site's posts and pages.
Version: 2.0 beta
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
Text Domain: mediacaster
Domain Path: /lang
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts (http://www.mesoconcepts.com), and is distributed under the terms of the GPL license, v.2.

http://www.opensource.org/licenses/gpl-2.0.php
*/


/**
 * mediacaster
 *
 * @package Mediacaster
 **/

# playlists:
add_filter('the_content', array('mediacaster', 'podcasts'), 1000);
add_action('template_redirect', array('mediacaster', 'catch_playlist'), 0);

add_action('rss2_ns', array('mediacaster', 'display_feed_ns'));
add_action('atom_ns', array('mediacaster', 'display_feed_ns'));

add_action('rss2_head', array('mediacaster', 'display_feed_header'));
add_action('atom_head', array('mediacaster', 'display_feed_header'));

add_action('rss2_item', array('mediacaster', 'display_feed_enclosures'));
add_action('atom_entry', array('mediacaster', 'display_feed_enclosures'));

$ops = get_option('mediacaster');
if ( $ops === false )
	mediacaster::init_options();
elseif ( !isset($ops['version']) && !defined('DOING_CRON') && is_admin() )
	add_action('init', array('mediacaster', 'upgrade'), 1000);

add_filter('widget_text', 'do_shortcode', 11);
add_shortcode('mc', array('mediacaster', 'shortcode'));

add_filter('ext2type', array('mediacaster', 'ext2type'));
add_filter('upload_mimes', array('mediacaster', 'upload_mimes'));

add_filter('get_the_excerpt', array('mediacaster', 'disable'), 0);
add_filter('get_the_excerpt', array('mediacaster', 'enable'), 20);

if ( !is_admin() ) {
	add_action('wp_print_scripts', array('mediacaster', 'scripts'), 0);
	add_action('wp_print_styles', array('mediacaster', 'styles'), 0);
} else {
	add_action('admin_print_scripts-media-upload.php', array('mediacaster', 'scripts'), 0);
	add_action('admin_menu', array('mediacaster', 'admin_menu'));
}

class mediacaster {
	/**
	 * upload_mimes()
	 *
	 * @param array $mines
	 * @return array $mines
	 **/

	function upload_mimes($mimes) {
		if ( !isset($mimes['flv|f4b|f4p|f4v']) )
			$mimes['flv|f4b|f4p|f4v'] = 'video/x-flv';
		if ( !isset($mimes['aac']) )
			$mimes['aac'] = 'audio/aac';
		if ( !isset($mimes['3gp|3g2']) )
			$mimes['3gp|3g2'] = 'video/3gpp';
		return $mimes;
	} # upload_mimes()
	
	
	/**
	 * ext2type()
	 *
	 * @param array $types
	 * @return array $types
	 **/

	function ext2type($types) {
		$types['video'] = array_merge($types['video'], array('flv', 'f4b', 'f4p', 'f4v'));
		$types['audio'] = array_merge($types['audio'], array('3pg', '3g2'));
		return $types;
	} # ext2type()
	
	
	/**
	 * shortcode()
	 *
	 * @return string $player
	 **/

	function shortcode($args, $content = '') {
		if ( empty($args['src']) ) {
			if ( empty($args['id']) )
				return '';
			
			$attachment = get_post($args['id']);
			if ( !$attachment || $attachment->post_type != 'attachment' )
				return '';
			
			$args['src'] = str_replace(' ', rawurlencode(' '), wp_get_attachment_url($attachment->ID));
			
			if ( !$args['src'])
				return '';
			
			if ( $attachment->post_parent && preg_match("/^audio\//i", $attachment->post_mime_type) ) {
				$enclosed = get_post_meta($attachment->post_parent, '_mc_enclosed');
				if ( $enclosed )
					$enclosed = array_map('intval', $enclosed);
				else
					$enclosed = array();
				if ( !in_array($attachment->post_parent, $enclosed) )
					add_post_meta($attachment->post_parent, '_mc_enclosed', $attachment->ID);
			}
		} else {
			$args['src'] = esc_url_raw(str_replace(' ', rawurlencode(' '), $args['src']));
		}
		
		if ( empty($args['type']) ) {
			if ( preg_match("/^https?:\/\/(?:www\.)?youtube.com\//i", $args['src']) ) {
				$args['type'] = 'youtube';
			} elseif ( preg_match("/\b(rss2?|xml|feed|atom)\b/i", $args['src']) ) {
				$args['type'] = 'audio';
			} else {
				$type = wp_check_filetype($args['src']);
				$args['type'] = $type['ext'];
			}
		}
		
		foreach ( array('width', 'height') as $arg ) {
			if ( isset($args[$arg]) && !is_numeric($args[$arg]) )
				unset($args[$arg]);
		}
			
		
		switch ( $args['type'] ) {
		case 'mp3':
		case 'm4a':
		case 'aac':
		case 'audio':
			return mediacaster::audio($args, $content);
		
		case 'flv':
		case 'f4b':
		case 'f4p':
		case 'f4v':
		case 'mp4':
		case 'm4v':
		case 'mov':
		case '3gp':
		case '3g2':
		case 'youtube':
		case 'video':
			return mediacaster::video($args, $content);
		
		default:
			return mediacaster::file($args, $content);
		}
	} # shortcode()
	
	
	/**
	 * audio()
	 *
	 * @param array $args
	 * @param string $content
	 * @return string $player
	 **/

	function audio($args, $content = '') {
		static $count = 0;
		extract($args, EXTR_SKIP);
		$defaults = mediacaster::defaults();
		foreach ( array('width', 'height') as $arg ) {
			if ( isset($$arg) )
				unset($defaults[$arg]);
		}
		extract($defaults);
		extract(mediacaster::get_skin($skin));
		
		if ( $cover ) {
			$image = WP_CONTENT_URL . $cover;
			$cover_size = getimagesize(WP_CONTENT_DIR . $cover);
			$width = $cover_size[0];
			$height = $cover_size[1];
			
			if ( $max_player_width && $width > $max_player_width ) {
				$height = round($height * $max_player_width / $width);
				$width = $player_width;
			}
		} else {
			$image = false;
			if ( empty($width) )
				$width = min($player_width, $min_player_width);
			$height = 0;
		}
		
		$id = 'm' . md5($src . '_' . $count++);
		
		$player = plugin_dir_url(__FILE__) . 'mediaplayer/player.swf';
		
		$allowfullscreen = 'true';
		$allowscriptaccess = 'always';
		
		$flashvars = array();
		$flashvars['file'] = esc_url_raw($src);
		$flashvars['skin'] = plugin_dir_url(__FILE__) . 'skins/' . $skin . '.swf';
		$flashvars['quality'] = 'true';
		
		if ( $image )
			$flashvars['image'] = esc_url_raw($image);
		
		$flashvars['plugins'] = array('quickkeys-1');
		
		if ( method_exists('google_analytics', 'get_options') && !current_user_can('publish_posts') && !current_user_can('publish_pages') ) {
			$uacct = google_analytics::get_options();
			if ( $uacct['uacct'] ) {
				$flashvars['plugins'][] = 'gapro-1';
				$flashvars['gapro.accountid'] = $uacct['uacct'];
			}
		}
		
		if ( $width >= $min_player_width ) {
			$height += $skin_height;
			if ( $link )
				$flashvars['link'] = esc_url_raw($link);
		} else {
			$width = max($width, 50);
			$height = max($height, 50);
			$flashvars['controlbar'] = 'none';
		}
		
		$flashvars = apply_filters('mediacaster_audio', $flashvars);
		$flashvars['plugins'] = implode(',', $flashvars['plugins']);
		$flashvars = http_build_query($flashvars, null, '&');
		
		$script = '';
		
		if ( !is_feed() )
			$script = <<<EOS
<script type="text/javascript">
var params = {};
params.allowfullscreen = "$allowfullscreen";
params.allowscriptaccess = "$allowscriptaccess";
params.flashvars = "$flashvars";
swfobject.embedSWF("$player", "$id", "$width", "$height", "9.0.0", false, false, params);
</script>
EOS;
		
		return <<<EOS

<div class="media_container"><div class="media" style="width: {$width}px; height: {$height}px;"><object id="$id" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$width" height="$height"><param name="movie" value="$player" /><param name="allowfullscreen" value="$allowfullscreen" /><param name="allowscriptaccess" value="$allowscriptaccess" /><param name="flashvars" value="$flashvars" /><embed src="$player" pluginspage="http://www.macromedia.com/go/getflashplayer" width="$width" height="$height" allowfullscreen="$allowfullscreen" allowscriptaccess="$allowscriptaccess" flashvars="$flashvars" /></object></div></div>

$script

EOS;
	} # audio()
	
	
	/**
	 * video()
	 *
	 * @param array $args
	 * @param string $content
	 * @return string $player
	 **/

	function video($args, $content = '') {
		static $count = 0;
		extract($args, EXTR_SKIP);
		$defaults = mediacaster::defaults();
		foreach ( array('width', 'height') as $arg ) {
			if ( isset($$arg) )
				unset($defaults[$arg]);
		}
		extract($defaults);
		extract(mediacaster::get_skin($skin));
		
		if ( empty($width) ) {
			$width = $player_width;
			$height = $player_height;
		} elseif ( empty($height)) {
			$height = round($width * 9 / 16);
		}
		
		if ( $max_player_width && $width > $max_player_width ) {
			$height = round($height * $max_player_width / $width);
			$width = $max_player_width;
		}
		
		$image = false;
		
		$id = 'm' . md5($src . '_' . $count++);
		
		$player = plugin_dir_url(__FILE__) . 'mediaplayer/player.swf';
		
		$allowfullscreen = 'true';
		$allowscriptaccess = 'always';
		
		$flashvars = array();
		$flashvars['file'] = esc_url_raw($src);
		$flashvars['skin'] = plugin_dir_url(__FILE__) . 'skins/' . $skin . '.swf';
		$flashvars['quality'] = 'true';
		
		if ( $image )
			$flashvars['image'] = esc_url_raw($image);
		
		$flashvars['plugins'] = array('quickkeys-1');
		
		if ( method_exists('google_analytics', 'get_options') && !current_user_can('publish_posts') && !current_user_can('publish_pages') ) {
			$uacct = google_analytics::get_options();
			if ( $uacct['uacct'] ) {
				$flashvars['plugins'][] = 'gapro-1';
				$flashvars['gapro.accountid'] = $uacct['uacct'];
			}
		}
		
		if ( $width >= $min_player_width) {
			$height += $skin_height;
			if ( $link )
				$flashvars['link'] = esc_url_raw($link);
		} else {
			$width = max($width, 50);
			$height = max($height, 50);
			$flashvars['controlbar'] = 'none';
		}
		
		$flashvars = apply_filters('mediacaster_video', $flashvars);
		$flashvars['plugins'] = implode(',', $flashvars['plugins']);
		$flashvars = http_build_query($flashvars, null, '&');
		
		$script = '';
		
		if ( !is_feed() )
			$script = <<<EOS
<script type="text/javascript">
var params = {};
params.allowfullscreen = "$allowfullscreen";
params.allowscriptaccess = "$allowscriptaccess";
params.flashvars = "$flashvars";
swfobject.embedSWF("$player", "$id", "$width", "$height", "9.0.0", false, false, params);
</script>
EOS;
		
		return <<<EOS

<div class="media_container"><div class="media" style="width: {$width}px; height: {$height}px;"><object id="$id" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$width" height="$height"><param name="movie" value="$player" /><param name="allowfullscreen" value="$allowfullscreen" /><param name="allowscriptaccess" value="$allowscriptaccess" /><param name="flashvars" value="$flashvars" /><embed src="$player" pluginspage="http://www.macromedia.com/go/getflashplayer" width="$width" height="$height" allowfullscreen="$allowfullscreen" allowscriptaccess="$allowscriptaccess" flashvars="$flashvars" /></object></div></div>

$script

EOS;
	} # video()
	
	
	/**
	 * file()
	 *
	 * @param array $args
	 * @param string $content
	 * @return string $download
	 **/

	function file($args, $content = '') {
		extract($args, EXTR_SKIP);
		
		$title = trim($content);
		if ( !$title ) {
			$title = basename($src);
			if ( preg_match("/(.+)\.([a-z0-9]+)$/", $title, $_title) )
				$title = $_title[1] . ' (' . strtolower($_title[2]) . ')';
		}
		
		$mime = wp_check_filetype($src);
		$icon = wp_mime_type_icon(wp_ext2type($mime['ext']));
		
		$src = esc_url($src);
		
		$title = apply_filters('mediacaster_file', $title, $args);
		
		return <<<EOS
<div class="media_container">
<a href="$src" class="no_icon" style="background-image: url($icon);">
$title
</a>
</div>
EOS;
	} # file()
	
	
	/**
	 * defaults()
	 *
	 * @return void
	 **/

	function defaults() {
		static $defaults;
		
		if ( isset($defaults) )
			return $defaults;
		
		global $content_width;
		
		$o = get_option('mediacaster');
		
		$min_player_width = 300;
		$max_player_width = intval($content_width);
		
		if ( $max_player_width )
			$player_width = $max_player_width;
		else
			$player_width = 420;
		
		$player_height = round($player_width * 9 / 16);
		
		$cover = $o['player']['cover'];
		$skin = $o['player']['skin'];
		
		$defaults = compact('player_width', 'player_height', 'min_player_width', 'max_player_width', 'cover', 'skin');
		
		return $defaults;
	} # defaults()
	
	
	/**
	 * get_skin
	 *
	 * @param string $skin
	 * @return array $skin_details
	 **/

	function get_skin($skin) {
		$skin_details = array(
			'bekle' => array(
				'skin' => 'bekle',
				'skin_height' => 59,
				),
			'kleur' => array(
				'skin' => 'kleur',
				'skin_height' => 59,
				),
			'modieus' => array(
				'skin' => 'modieus',
				'skin_height' => 31,
				),
			);
		
		return isset($skin_details[$skin]) ? $skin_details[$skin] : $skin_details['modieus'];
	} # get_skin()
	
	
	/**
	 * disable()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/

	function disable($in = null) {
		remove_filter('the_content', array('mediacaster', 'podcasts'), 1000);
		
		return $in;
	} # disable()
	
	
	/**
	 * enable()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/
	
	function enable($in = null) {
		add_filter('the_content', array('mediacaster', 'podcasts'), 1000);
		
		return $in;
	} # enable()
	
	
	/**
	 * scripts()
	 *
	 * @return void
	 **/

	function scripts() {
		wp_enqueue_script('swfobject');
	} # scripts()
	
	
	/**
	 * styles()
	 *
	 * @return void
	 **/
	
	function styles() {
		$folder = plugin_dir_url(__FILE__);
		$css = $folder . 'css/mediacaster.css';
		
		wp_enqueue_style('mediacaster', $css, null, '2.0');
	} # styles()
	
	
	/**
	 * get_enclosures()
	 *
	 * @param int $post_ID
	 * @param bool $playlist
	 * @return array $enclosures
	 **/

	function get_enclosures($post_ID, $playlist = false) {
		if ( !$post_ID )
			return array();
		
		$post = get_post($post_ID);
		
		if ( !$post )
			return array();
		
		$enclosures = get_children(array(
			'post_parent' => $post->ID,
			'post_type' => 'attachment',
			'order_by' => 'menu_order ID',
			));
		
		if ( !$enclosures )
			return array();
		
		$enclosed = get_post_meta($post->ID, '_mc_enclosed');
		if ( $enclosed )
			$enclosed = array_map('intval', $enclosed);
		else
			$enclosed = array();
		
		foreach ( $enclosures as $key => $enclosure ) {
			if ( $playlist ) {
				if ( !in_array($enclosure->post_mime_type, array('audio/mpeg', 'audio/aac'))
					|| in_array((int) $enclosure->ID, $enclosed) )
					unset($enclosures[$key]);
			} else {
				if ( preg_match("/^image\//i", $post->post_mime_type) )
					unset($enclosures[$key]);
			}
		}
		
		return $enclosures;
	} # get_enclosures()
	
	
	/**
	 * podcasts()
	 *
	 * @param string $content
	 * @return string $content
	 **/
	
	function podcasts($content) {
		if ( !in_the_loop() )
			return $content;
		
		$o = get_option('mediacaster');
		
		if ( $o['player']['position'] == 'none' )
			return $content;
		
		$podcasts = mediacaster::get_enclosures(get_the_ID(), true);
		
		if ( !$podcasts )
			return $content;
		
		$out = mediacaster::audio(array('src' => get_option('home') . '?podcasts=' . get_the_ID()));
		
		if ( $options['player']['position'] != 'bottom' )
			$content = $out . $content;
		else
			$content = $content . $out;
		
		return $content;
	} # podcasts()
	
	
	/**
	 * catch_playlist()
	 *
	 * @return void
	 **/

	function catch_playlist() {
		if ( isset($_GET['podcasts']) && intval($_GET['podcasts']) ) {
			mediacaster::display_playlist_xml($_GET['podcasts'], 'audio');
			die;
		}
	} # catch_playlist()


	/**
	 * display_playlist_xml()
	 *
	 * @param int $post_ID
	 * @param string $type
	 * @return void
	 **/

	function display_playlist_xml($post_ID, $type = 'audio') {
		$post_ID = intval($post_ID);
		
		$podcasts = mediacaster::get_enclosures($post_ID, true);
		
		# Reset WP
		$GLOBALS['wp_filter'] = array();
		while ( @ob_end_clean() );
		
		header('Content-Type:text/xml; charset=' . get_option('blog_charset'));

		echo '<?xml version="1.0" encoding="' . get_option('blog_charset') . '"?>' . "\n"
			. '<playlist version="1" xmlns="http://xspf.org/ns/0/">' . "\n";
		
		echo '<trackList>' . "\n";
		
		$o = get_option('mediacaster');
		$cover = $o['player']['cover'];
		
		foreach ( $podcasts as $podcast ) {
			echo '<track>' . "\n";
			
			echo '<title>'
				. $podcast->post_title
				. '</title>' . "\n";
			
			if ( $cover ) {
				echo '<image>'
					. WP_CONTENT_URL . $cover
					. '</image>' . "\n";
			}

			echo '<location>'
				. wp_get_attachment_url($podcast->ID)
				. '</location>' . "\n";

			echo '</track>' . "\n";
		}

		echo '</trackList>' . "\n";
		
		echo '</playlist>' . "\n";
	} # display_playlist_xml()


	/**
	 * display_feed_ns()
	 *
	 * @return void
	 **/

	function display_feed_ns() {
		if ( !is_feed() )
		 	return;
		echo 'xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"' . "\n\t";
	} # display_feed_ns()


	/**
	 * display_feed_header()
	 *
	 * @return void
	 **/

	function display_feed_header() {
		if ( !is_feed() )
			return;
		
		$options = get_option('mediacaster');

		echo "\n\t\t"
			. '<copyright>' . apply_filters('the_excerpt_rss', $options['itunes']['copyright']) . '</copyright>' . "\n\t\t"
			. '<itunes:author>' . apply_filters('the_excerpt_rss', $options['itunes']['author']) . '</itunes:author>' . "\n\t\t"
			. '<itunes:summary>' . apply_filters('the_excerpt_rss', $options['itunes']['summary']) . '</itunes:summary>' . "\n\t\t"
			. '<itunes:explicit>' . apply_filters('the_excerpt_rss', $options['itunes']['explicit']) . '</itunes:explicit>' . "\n\t\t"
			. '<itunes:block>' . apply_filters('the_excerpt_rss', $options['itunes']['block']) . '</itunes:block>' . "\n\t\t"
			;
		
		$cover = $options['player']['cover'];
		
		if ( $cover ) {
			echo '<itunes:image href="' . esc_url(WP_CONTENT_URL . $cover) . '" />' . "\n\t\t"
				;
		}

		if ( $options['itunes']['category'] ) {
			foreach ( (array) $options['itunes']['category'] as $category ) {
				$cats = split('/', $category);

				$cat = array_pop($cats);

				$cat = trim($cat);

				if ( $cat ) {
					$category = '<itunes:category text="' . apply_filters('the_excerpt_rss', $cat) . '" />' . "\n\t\t";

					if ( $cat = array_pop($cats) ) {
						$cat = trim($cat);

						$category = '<itunes:category text="' . apply_filters('the_excerpt_rss', $cat) . '">' . "\n\t\t\t"
							. $category
							. '</itunes:category>' . "\n\t\t";
					}

					echo $category;
				}
			}
		}

		echo "\n";
	} # display_feed_header()


	/**
	 * display_feed_enclosures()
	 *
	 * @return void
	 **/

	function display_feed_enclosures() {
		if ( !in_the_loop() )
			return;
		else
			$post_ID = get_the_ID();
		
		$enclosures = mediacaster::get_enclosures($post_ID);
		
		if ( !$enclosures )
			return;
		
		$add_itunes_tags = false;
		
		foreach ( $enclosures as $enclosure ) {
			$file = get_attached_file($enclosure->ID);
			$file_url = esc_url(wp_get_attachment_url($enclosure->ID));
			$size = @filesize($file);
			
			echo "\n\t\t"
				. '<enclosure' . ' url="' .  $file_url . '" length="' . $size . '" type="' . $mime . '" />';
			
			$add_itunes_tags = true;
		}

		if ( $add_itunes_tags && is_feed() ) {
			$author = get_the_author();

			$summary = get_post_meta($post_ID, '_description', true);
			if ( !$summary ) {
				$summary = get_the_excerpt();
			}

			$keywords = get_post_meta($post_ID, '_keywords', true);
			if ( !$keywords ) {
				$keywords = array();

				if ( $cats = get_the_category($post_ID) )
					foreach ( $cats as $cat )
						$keywords[] = $cat->name;

				if ( $tags = get_the_tags($post_ID) )
					foreach ( $tags as $tag )
						$keywords[] = $tag->name;

				$keywords = array_unique($keywords);
				$keywords = implode(', ', $keywords);
			}

			foreach ( array(
					'author',
					'summary',
					'keywords'
					) as $field ) {
				$$field = strip_tags($$field);
				$$field = preg_replace("/\s+/", " ", $$field);
				$$field = trim($$field);
			}

			echo "\n\t\t"
				. '<itunes:author>' . apply_filters('the_excerpt_rss', $author) . '</itunes:author>' . "\n\t\t"
				. '<itunes:summary>' . apply_filters('the_excerpt_rss', $summary) . '</itunes:summary>' . "\n\t\t"
				. '<itunes:keywords>' . apply_filters('the_excerpt_rss', $keywords) . '</itunes:keywords>' . "\n\t\t"
				;
		}

		echo "\n";
	} # display_feed_enclosures()


	/**
	 * init_options()
	 *
	 * @return void
	 **/

	function init_options() {
		$options = array();

		$options['player']['position'] = 'top';
		$options['player']['cover'] = false;
		
		$options['itunes']['author'] = '';
		$options['itunes']['summary'] = get_option('blogdescription');
		$options['itunes']['explicit'] = 'no';
		$options['itunes']['block'] = 'no';
		$options['itunes']['copyright'] = '';

		$options['version'] = 2;

		update_option('mediacaster', $options);
	} # init_options()


	/**
	 * admin_menu()
	 *
	 * @return void
	 **/

	function admin_menu() {
		add_options_page(
			__('Mediacaster', 'mediacaster'),
			__('Mediacaster', 'mediacaster'),
			'manage_options',
			'mediacaster',
			array('mediacaster_admin', 'edit_options')
			);
	} # admin_menu()
	
	
	/**
	 * upgrade()
	 *
	 * @return void
	 **/

	function upgrade() {
		$ops = get_option('mediacaster');
		
		if ( isset($ops['version']) )
			return;
		
		$ops['version'] = '2.0';
		
		if ( !isset($ops['player']['skin']) )
			$ops['player']['skin'] = 'modieus';
		
		if ( !isset($ops['player']['position']) )
			$ops['player']['position'] = 'top';
		
		if ( !isset($ops['player']['cover']) )
			$ops['player']['cover'] = false;
		
		if ( !isset($ops['longtail']) )
			$ops['longtail'] = array(
				'licensed' => false,
				'pub_id' => '',
				'ad_flow' => array(),
				);
		
		# prevent concurrent upgrades
		update_option('mediacaster', $ops);
		
		$ignore_user_abort = ignore_user_abort(true);
		$current_user = wp_get_current_user();
		set_time_limit(600);
		
		global $wpdb;
		
		$posts = $wpdb->get_results("
			SELECT	posts.*,
					meta_value as media_path
			FROM	$wpdb->posts as posts
			JOIN	$wpdb->postmeta as postmeta
			ON		postmeta.post_id = posts.ID
			AND		postmeta.meta_key = '_mediacaster_path'
			ORDER BY meta_value DESC, ID DESC
			");
		
		foreach ( $posts as $i => $post ) {
			$files[$i] = glob(ABSPATH . $post->media_path . '*');
			$empty = true;
			
			if ( $files[$i] ) {
				$files[$i] = array_map('basename', $files[$i]);
				foreach ( $files[$i] as $j => $file ) {
					unset($files[$i][$j]);
					if ( preg_match("/^(.+)\.([^.]+)$/", $file, $match) ) {
						$ext = array_pop($match);
						$name = array_pop($match);
						if ( in_array($ext, array('jpg', 'jpeg', 'png') ) && preg_match("/^cover-/", $name) ) {
							if ( is_writable(ABSPATH . $post->media_path . $file) )
								unlink(ABSPATH . $post->media_path . $file);
							else
								$empty = false;
						} else {
							$files[$i][$name][] = $ext;
							$empty = false;
						}
					} else {
						$empty = false;
					}
				}
			}
			
			if ( !$files[$i] ) {
				if ( is_writable(ABSPATH . $post->media_path) && $empty )
					rmdir(ABSPATH . $post->media_path);
			}
		}
		
		$site_url = trailingslashit(get_option('siteurl'));
		$format = '';
		
		if ( isset($ops['player']['width']) ) {
			$format .= ' width="' . intval($ops['player']['width']) . '"';
			unset($ops['player']['width']);
		}
			
		if ( isset($ops['player']['height']) ) {
			$format .= ' height="' . intval($ops['player']['height']) . '"';
			unset($ops['player']['height']);
		}
		
		foreach ( $posts as $i => $post ) {
			if ( $files[$i] )
				uksort($files[$i], 'strnatcasecmp');
			
			$found_one = (bool) $files;
			$audios = array();
			$videos = array();
			$images = array();
			$attachments = array();
			$folder_url = $site_url . $post->media_path;
			
			# transform <!--podcast#file-->, <!--media#file--> and <!--videocast#file--> into [media:file]
			$post->post_content = preg_replace(
				"/
					(?:<p>\s*)?				# maybe a paragraph tag
					(?:<br\s*\/>\s*)*		# and a couple br tags
					<!--\s*(?:
						media
						|
						podcast
						|
						videocast
						)\s*
						\#([^>#]*)			# the media file
						(?:\#[^>]*)?		# and some junk
					\s*
					-->
					(?:\s*<br\s*\/>)*		# maybe a couple of br tags
					(?:<\/p>\s*)?			# and a close paragraph tag
				/ix",
				"[media:$1]",
				$post->post_content);

			# transform <flv href="file" /> into [media:file]
			$post->post_content = preg_replace(
				"/
					(?:<p>)?
					<flv\s+
					[^>]*
					href=\"([^\">]*)\"
					[^>]*
					>
					(?:<\/flv>)?
					(?:<\/p>)?
				/ix",
				"[media:$1]",
				$post->post_content);
			
			# split files into audios and videos
			foreach ( $files[$i] as $name => $exts ) {
				foreach ( $exts as $j => $ext ) {
					if ( in_array($ext, array('jpg', 'jpeg', 'png')) ) {
						$images[$name] = "$name.$ext";
					} elseif ( in_array($ext, array('mp3', 'm4a')) ) {
						$audios[] = "$name.$ext";
					} elseif ( in_array($ext, array('flv', 'swf', 'mp4', 'm4v', 'mov')) ) {
						$videos[$name][$ext] = "$name.$ext";
					} else {
						$attachments[] = "$name.$ext";
					}
				}
			}
			
			# process inline attachments
			foreach ( $attachments as $j => $attachment ) {
				$find = preg_quote($attachment);
				$repl = '[mc src="' . $folder_url . rawurlencode($attachment) . '" type="file"/]';
				
				$post->post_content = preg_replace(
					"/\[media:\s*($find)\s*\]/",
					$repl,
					$post->post_content);
			}
			
			# process inline audios
			foreach ( $audios as $j => $audio ) {
				$find = preg_quote($audio);
				$repl = '[mc src="' . $folder_url . rawurlencode($audio) . '" type="audio"/]';
				
				unset($found);
				
				$post->post_content = preg_replace(
					"/\[(?:audio|video|media):\s*($find)\s*\]/",
					$repl,
					$post->post_content, -1, $found);
				
				unset($audios[$j]);
				if ( !$found )
					$audios[$audio] = $repl;
			}
			
			# process inline videos
			$_videos = array();
			foreach ( $videos as $name => $_video ) {
				foreach ( $_video as $video ) {
					if ( isset($images[$name]) ) {
						$_videos[$video] = $images[$name];
					} else {
						$_videos[$video] = false;
					}
				}
			}
			
			$videos = $_videos;
			
			foreach ( $videos as $video => $image ) {
				$find = preg_quote($video);
				$repl = ( '[mc src="' . $folder_url . rawurlencode($video) . '"' )
					. ( $image
					? ( ' image="' . $folder_url . rawurlencode($image) . '"' )
					: ''
					)
					. $format . ' type="video"/]';
				
				$post->post_content = preg_replace(
					"/\[(?:audio|video|media):\s*$find\s*\]/",
					$repl,
					$post->post_content, -1, $found);
				
				if ( $found )
					unset($videos[$video]);
				else
					$videos[$video] = $repl;
			}
			
			# insert remaining videos
			foreach ( $videos as $file => $repl ) {
				if ( $ops['position'] == 'bottom' )
					$post->post_content .= "\n\n$repl";
				else
					$post->post_content = "$repl\n\n$post->post_content";
			}
			
			# insert remaining audios
			foreach ( $audios as $file => $repl ) {
				if ( $ops['position'] == 'bottom' )
					$post->post_content .= "\n\n$repl";
				else
					$post->post_content = "$repl\n\n$post->post_content";
			}
			
			# process external urls
			$post->post_content = preg_replace_callback(
				"/\[(?:audio|video|media):\s*(.+)\s*\]/",
				array('mediacaster', 'upgrade_callback'),
				$post->post_content, -1, $found);
			
			$found_one |= $found;
			
			if ( $found_one ) {
				wp_set_current_user($post->post_author);
				wp_update_post($post);
			}
			
			delete_post_meta($post->ID, '_mediacaster_path');
		}
		
		wp_set_current_user($current_user->ID);
		ignore_user_abort($ignore_user_abort);
		
		# unset version in case anything went wrong
		if ( $posts )
			unset($ops['version']);
		update_option('mediacaster', $ops);
	} # upgrade()
	
	
	/**
	 * upgrade_callback
	 *
	 * @param array $match
	 * @return string $out
	 **/

	function upgrade_callback($match) {
		static $i = 0;
		$file = array_pop($match);
		
		if ( preg_match("/^https?:\/\/[^\/]*\byoutube\.com\//", $file) ) {
			return "[mc src=\"$file\" type=\"video\"/]";
		} elseif ( preg_match("/^https?:\/\/video\.google\.com\//", $file) ) {
			$file = parse_url($file);
			$file = $file['query'];
			parse_str($file, $file);
			$file = $file['docid'];
			$file = preg_replace("/[^a-z0-9_-]/i", '', $file);
			
			if ( !$file )
				return '';
			
			$ops = get_option('mediacaster');
			
			$width = intval($ops['player']['width']);
			$height = intval($ops['player']['height']);
			$height += 26;
			
			$player = 'http://video.google.com/googleplayer.swf?docId=' . $file;
			$id = md5($i++ . $player);
			
			return <<<EOS

<div class="media_container"><div class="media" style="width: {$width}px; height: {$height}px;"><object id="$id" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$width" height="$height"><param name="movie" value="$player" /><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="true" /><embed src="$player" pluginspage="http://www.macromedia.com/go/getflashplayer" width="$width" height="$height" allowfullscreen="true" allowscriptaccess="true" /></object></div></div>

EOS;
		} else {
			return '[mc src="' . str_replace(' ', rawurlencode(' '), $file) . '"/]';
		}
	} # upgrade_callback()
} # mediacaster

function mediacaster_admin() {
	include dirname(__FILE__) . '/mediacaster-admin.php';
}

foreach ( array('settings_page_mediacaster',
	'post.php', 'post-new.php', 'page.php', 'page-new.php',
	'media-upload.php', 'upload.php', 'async-upload.php', 'media.php') as $hook )
	add_action("load-$hook", 'mediacaster_admin');
?>