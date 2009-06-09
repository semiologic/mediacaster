<?php
/*
Plugin Name: Mediacaster
Plugin URI: http://www.semiologic.com/software/mediacaster/
Description: Lets you add podcasts and videos to your site's posts and pages.
Version: 2.0 alpha
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
Text Domain: mediacaster-info
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

if ( get_option('mediacaster') === false )
	mediacaster::init_options();

add_filter('widget_text', 'do_shortcode', 11);
add_shortcode('media', array('mediacaster', 'shortcode'));

add_filter('ext2type', array('mediacaster', 'ext2type'));
add_filter('upload_mimes', array('mediacaster', 'upload_mimes'));

add_filter('get_the_excerpt', array('mediacaster', 'disable'), 0);
add_filter('get_the_excerpt', array('mediacaster', 'enable'), 20);

if ( !is_admin() ) {
	add_action('wp_print_scripts', array('mediacaster', 'scripts'), 0);
	add_action('wp_print_styles', array('mediacaster', 'styles'), 0);
} else {
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
		$types['video'] = array_merge($types['video'], array('flv', 'f4b', 'f4p', 'f4v', '3pg', '3g2'));
		return $types;
	} # ext2type()
	
	
	/**
	 * shortcode()
	 *
	 * @return string $player
	 **/

	function shortcode($args, $content = '') {
		if ( empty($args['href']) ) {
			if ( empty($args['id']) )
				return '';
			
			$attachment = get_post($args['id']);
			if ( !$attachment || $attachment->post_type != 'attachment' )
				return '';
			
			$args['href'] = wp_get_attachment_url($attachment->ID);
			
			if ( !$args['href'])
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
			$args['href'] = esc_url_raw($args['href']);
		}
		
		if ( empty($args['type']) ) {
			if ( preg_match("/^https?:\/\/(?:www\.)?youtube.com\//i", $args['href']) ) {
				$args['type'] = 'youtube';
			} elseif ( preg_match("/\b(rss2?|xml|feed|atom)\b/i", $args['href']) ) {
				$args['type'] = 'audio';
			} else {
				$type = wp_check_filetype($args['href']);
				$args['type'] = $type['ext'];
			}
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
		extract($args, EXTR_SKIP);
		extract(mediacaster::defaults(), EXTR_SKIP);
		static $count = 0;
		
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
		
		$id = 'm' . md5($href . '_' . $count++);
		
		$player = plugin_dir_url(__FILE__) . 'player/player-viral.swf';
		
		$allowfullscreen = 'true';
		$allowscriptaccess = 'always';
		$allownetworking = 'all';
		
		$flashvars = array();
		$flashvars['file'] = $href;
		$flashvars['skin'] = plugin_dir_url(__FILE__) . 'player/kleur.swf';
		
		if ( $image )
			$flashvars['image'] = esc_url_raw($image);
		
		$flashvars['plugins'] = array('quickkeys-1');
		
		if ( $width >= $min_player_width ) {
			$height += 59;
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
params.allownetworking = "$allownetworking";
params.flashvars = "$flashvars";
swfobject.embedSWF("$player", "$id", "$width", "$height", "9.0.0", false, false, params);
</script>
EOS;
		
		return <<<EOS

<div class="media_container"><div class="media" style="width: {$width}px; height: {$height}px;"><object id="$id" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$width" height="$height"><param name="movie" value="$player" /><param name="allowfullscreen" value="$allowfullscreen" /><param name="allowscriptaccess" value="$allowscriptaccess" /><param name="allownetworking" value="$allownetworking" /><param name="flashvars" value="$flashvars" /><embed src="$player" pluginspage="http://www.macromedia.com/go/getflashplayer" width="$width" height="$height" allowfullscreen="$allowfullscreen" allowscriptaccess="$allowscriptaccess" allownetworking="$allownetworking" flashvars="$flashvars" /></object></div></div>

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
		extract($args, EXTR_SKIP);
		extract(mediacaster::defaults(), EXTR_SKIP);
		static $count = 0;
		
		if ( empty($width) ) {
			$width = $player_width;
			$height = $player_height;
		} elseif ( empty($height)) {
			if ( $player_format == '4/3' )
				$height = round($width * 3 / 4);
			else
				$height = round($width * 9 / 16);
		}
		
		if ( $max_player_width && $width > $max_player_width ) {
			$height = round($height * $max_player_width / $width);
			$width = $max_player_width;
		}
		
		preg_match("/\.([^.]+)$/", $file, $ext); 
		$ext = end($ext);
		
		$image = false;
		
		$id = 'm' . md5($file . '_' . $count++);
		
		$player = plugin_dir_url(__FILE__) . 'player/player-viral.swf';
		
		$allowfullscreen = 'true';
		$allowscriptaccess = 'always';
		$allownetworking = 'all';
		
		$flashvars = array();
		$flashvars['file'] = $href;
		$flashvars['skin'] = plugin_dir_url(__FILE__) . 'player/kleur.swf';
		
		if ( $image )
			$flashvars['image'] = esc_url_raw($image);
		
		$flashvars['plugins'] = array('quickkeys-1');

		if ( $width >= $min_player_width ) {
			$height += 59;
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
params.allownetworking = "$allownetworking";
params.flashvars = "$flashvars";
swfobject.embedSWF("$player", "$id", "$width", "$height", "9.0.0", false, false, params);
</script>
EOS;
		
		return <<<EOS

<div class="media_container"><div class="media" style="width: {$width}px; height: {$height}px;"><object id="$id" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$width" height="$height"><param name="movie" value="$player" /><param name="allowfullscreen" value="$allowfullscreen" /><param name="allowscriptaccess" value="$allowscriptaccess" /><param name="allownetworking" value="$allownetworking" /><param name="flashvars" value="$flashvars" /><embed src="$player" pluginspage="http://www.macromedia.com/go/getflashplayer" width="$width" height="$height" allowfullscreen="$allowfullscreen" allowscriptaccess="$allowscriptaccess" allownetworking="$allownetworking" flashvars="$flashvars" /></object></div></div>

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
			$title = basename($href);
			if ( preg_match("/(.+)\.([a-z0-9]+)$/", $title, $_title) )
				$title = $_title[1] . ' (' . strtolower($_title[2]) . ')';
		}
		
		$mime = wp_check_filetype($href);
		$icon = wp_mime_type_icon(wp_ext2type($mime['ext']));
		
		$href = esc_url($href);
		
		return <<<EOS
<div class="media_container">
<a class="no_icon" href="$href" style="background-image: url($icon);">
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
		static $player_width;
		static $player_height;
		static $player_format;
		static $min_player_width = 300;
		static $max_player_width;
		static $cover;
		
		if ( isset($player_format) )
			return compact('player_width', 'player_height', 'player_format', 'min_player_width', 'max_player_width', 'cover');
		
		global $content_width;
		
		$o = get_option('mediacaster');
		
		$player_format = $o['player']['format'];
		$max_player_width = intval($content_width);
		
		if ( $max_player_width )
			$player_width = $max_player_width;
		else
			$player_width = 420;
		
		if ( $player_format == '4/3' )
			$player_height = round($player_width * 3 / 4);
		else
			$player_height = round($player_width * 9 / 16);
		
		$cover = $o['player']['cover'];
		
		return compact('player_width', 'player_height', 'player_format', 'min_player_width', 'max_player_width', 'cover');
	} # defaults()
	
	
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
		$folder = plugin_dir_url(__FILE__);
		
		wp_enqueue_script('swfobject');
	} # scripts()
	
	
	/**
	 * styles()
	 *
	 * @return void
	 **/
	
	function styles() {
		$folder = plugin_dir_url(__FILE__);
		$css = $folder . 'mediacaster.css';
		
		wp_enqueue_style('mediacaster', $css, null, '2.0');
	} # styles()
	
	
	/**
	 * get_enclosures()
	 *
	 * @param bool $podcasts
	 * @return array $enclosures
	 **/

	function get_enclosures($podcasts = false, $post_ID = null) {
		if ( !in_the_loop() && !$post_ID )
			return array();
		
		global $post;
		
		if ( $post_ID )
			$post = get_post($post_ID);
		
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
			if ( $podcasts ) {
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
		
		$podcasts = mediacaster::get_enclosures(true);
		
		if ( !$podcasts )
			return $content;
		
		$out = mediacaster::audio(array('href' => get_option('home') . '?podcasts=' . get_the_ID()));
		
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
		
		$podcasts = mediacaster::get_enclosures(true, $post_ID);
		
		# Reset WP
		$GLOBALS['wp_filter'] = array();
		while ( @ob_end_clean() );
		
		header('Content-Type:text/xml; charset=' . get_option('blog_charset'));

		echo '<?xml version="1.0" encoding="' . get_option('blog_charset') . '"?>' . "\n"
			. '<playlist version="1" xmlns="http://xspf.org/ns/0/">' . "\n";
		
		echo '<trackList>' . "\n";
		
		$site_url = trailingslashit(get_option('home'));
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
		if ( class_exists('podPress_class') || !is_feed() )
		 	return;
		echo 'xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"' . "\n\t";
	} # display_feed_ns()


	/**
	 * display_feed_header()
	 *
	 * @return void
	 **/

	function display_feed_header() {
		if ( class_exists('podPress_class') || !is_feed() )
			return;
		
		$options = get_option('mediacaster');

		$site_url = trailingslashit(site_url());

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
		
		$enclosures = mediacaster::get_enclosures();
		
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

		if ( $add_itunes_tags && !class_exists('podPress_class') && is_feed() ) {
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
		global $wpdb;
		$options = array();

		$options['itunes']['author'] = '';
		$options['itunes']['summary'] = get_option('blogdescription');
		$options['itunes']['explicit'] = 'no';
		$options['itunes']['block'] = 'no';
		$options['itunes']['copyright'] = '';

		$options['player']['format'] = '16/9';
		$options['player']['position'] = 'top';
		$options['player']['cover'] = false;

		update_option('mediacaster', $options);
	} # init_options()


	/**
	 * admin_menu()
	 *
	 * @return void
	 **/

	function admin_menu() {
		add_options_page(
			__('Mediacaster'),
			__('Mediacaster'),
			'manage_options',
			'mediacaster',
			array('mediacaster_admin', 'edit_options')
			);
	} # admin_menu()
} # mediacaster

function mediacaster_admin() {
	include dirname(__FILE__) . '/mediacaster-admin.php';
}

foreach ( array('settings_page_mediacaster',
	'post.php', 'post-new.php', 'page.php', 'page-new.php',
	'media-upload.php', 'upload.php', 'async-upload.php', 'media.php') as $hook )
	add_action("load-$hook", 'mediacaster_admin');
?>