<?php
/*
Plugin Name: Mediacaster
Plugin URI: http://www.semiologic.com/software/mediacaster/
Description: Lets you add podcasts and videos to your site's posts and pages.
Version: 1.6 beta
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

# audio:
add_filter('the_content', array('mediacaster', 'display_players'), 0);

# playlists:
add_filter('the_content', array('mediacaster', 'display_playlist'), 1000);
add_action('template_redirect', array('mediacaster', 'catch_playlist'), 0);

add_action('rss2_ns', array('mediacaster', 'display_feed_ns'));
add_action('atom_ns', array('mediacaster', 'display_feed_ns'));

add_action('rss2_head', array('mediacaster', 'display_feed_header'));
add_action('atom_head', array('mediacaster', 'display_feed_header'));

add_action('rss2_item', array('mediacaster', 'display_feed_enclosures'));
add_action('atom_entry', array('mediacaster', 'display_feed_enclosures'));

if ( !is_admin() ) {
	add_action('wp_print_scripts', array('mediacaster', 'scripts'));
	add_action('wp_print_styles', array('mediacaster', 'styles'));
} else {
	add_action('admin_menu', array('mediacaster', 'admin_menu'));
	add_action('admin_menu', array('mediacaster', 'meta_boxes'), 20);
}

add_filter('get_the_excerpt', array('mediacaster', 'disable'), 0);
add_filter('get_the_excerpt', array('mediacaster', 'enable'), 20);

if ( get_option('mediacaster') === false )
	mediacaster::init_options();

add_action('init', array('mediacaster', 'init'));

class mediacaster {
	/**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		global $player_width;
		global $player_height;
		global $content_width;
		
		$o = get_option('mediacaster');
		
		if ( !isset($player_width) ) {
			if ( isset($content_width) )
				$player_width = $content_width;
			else
				$player_width = 420;
		}
		
		if ( !isset($player_height) ) {
			if ( $o['player']['format'] == '16/9' )
				$player_height = round($player_width * 9 / 16);
			else
				$player_height = round($player_width * 3 / 4);
		}
	} # init()
	
	
	/**
	 * disable()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/

	function disable($in = null) {
		remove_filter('the_content', array('mediacaster', 'display_players'), 0);
		remove_filter('the_content', array('mediacaster', 'display_playlist'), 1000);
		
		return $in;
	} # disable()
	
	
	/**
	 * enable()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/
	
	function enable($in = null) {
		add_filter('the_content', array('mediacaster', 'display_players'), 0);
		add_filter('the_content', array('mediacaster', 'display_playlist'), 1000);
		
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
		wp_enqueue_script('qtobject', $folder . 'js/qtobject.js', false, '1.0.2');
	} # scripts()
	
	
	/**
	 * styles()
	 *
	 * @return void
	 **/
	
	function styles() {
		$folder = plugin_dir_url(__FILE__);
		$css = $folder . 'mediacaster.css';
		
		wp_enqueue_style('mediacaster', $css, null, '1.6');
	} # styles()
	
	
	/**
	 * display_players()
	 *
	 * @param string $buffer
	 * @return string $buffer
	 **/

	function display_players($buffer) {
		$o = get_option('mediacaster');
		
		if ( !isset($o['compat']) || isset($o['compat']) && $o['compat'] )
			$buffer = mediacaster::compat($buffer);
		
		$buffer = preg_replace_callback("/
			(?:<p(?:\s[^>]*)?>)?
			\[(?:audio|video|media)\s*:
			([^\]]*)
			\]
			(?:<\s*\/\s*p\s*>)?
			/ix",
			array('mediacaster', 'display_player_callback'),
			$buffer
			);
		
		return $buffer;
	} # display_players()

	
	/**
	 * compat()
	 *
	 * @param string $buffer
	 * @return string $buffer
	 **/

	function compat($buffer) {
		# transform <!--podcast#file-->, <!--media#file--> and <!--videocast#file--> into [media:file]
		
		$buffer = preg_replace(
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
			$buffer
			);

		# transform <flv href="file" /> into [media:file]

		$buffer = preg_replace(
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
			$buffer
			);

		return $buffer;
	} # compat()


	/**
	 * display_player_callback()
	 *
	 * @param array $input regex match
	 * @return string $output
	 **/

	function display_player_callback($input) {
		$file = $input[1];
		
		if ( preg_match("/^https?:\/\/(?:www\.)youtube\.com\/watch\?/ix", $file, $match) )
			return mediacaster::display_youtube($file);
		
		preg_match("/\.([^.]+)$/", $file, $ext); 
		$ext = end($ext);
		
		switch ( strtolower($ext) ) {
		case 'pdf':
		case 'zip':
		case 'gz':
			return mediacaster::display_file($file);
			break;
			
		case 'mov':
		case 'm4v':
		case 'm4a':
			return mediacaster::display_quicktime($file);
			break;

		case 'flv':
		case 'mp4':
			return mediacaster::display_video($file);
			break;

		case 'mp3':
		default:
			return mediacaster::display_audio($file);
			break;
		}
	} # display_player_callback()


	/**
	 * display_playlist()
	 *
	 * @param string $content
	 * @return string $content
	 **/

	function display_playlist($content) {
		if ( !in_the_loop() )
			return $content;
		
		global $wpdb;
		$post_ID = get_the_ID();
		
		if ( $post_ID <= 0 )
			return;

		$out = '';

		$path = mediacaster::get_path($post_ID);

		$files = mediacaster::get_files($path, $post_ID);

		foreach ( array('flash_audios', 'flash_videos', 'qt_audios', 'qt_videos') as $var )
			$$var = mediacaster::extract_podcasts($files, $post_ID, $var);

		if ( $flash_audios ) {
			$site_url = trailingslashit(site_url());

			# cover
			$cover = mediacaster::get_cover();

			if ( $cover ) {
				$cover_size = getimagesize(ABSPATH . $cover);

				$cover_width = $cover_size[0];
				$cover_height = $cover_size[1];

				$mp3_width = $cover_width;

				$height = $height + $cover_height;
			} else {
				$mp3_width = $width;
			}

			$file = $site_url . '?podcasts=' . $post_ID;

			# insert player
			$out .= mediacaster::display_audio($file) . "\n";
		}
		
		if ( $flash_videos ) {
			$site_url = trailingslashit(site_url());
			$file = $site_url . '?videos=' . $post_ID;
			$out .= mediacaster::display_video($file) . "\n";
		}

		if ( $qt_audios ) {
			foreach ( $qt_audios as $file )
				$out .= mediacaster::display_quicktime($file) . "\n";
		}

		if ( $qt_videos ) {
			foreach ( $qt_videos as $file )
				$out .= mediacaster::display_quicktime($file) . "\n";
		}

		if ( $options['player']['position'] != 'bottom' )
			$content = $out . $content;
		else
			$content = $content . $out;

		return $content;
	} # display_playlist()
	
	
	/**
	 * get_flashvars()
	 *
	 * @param string $file
	 * @param string $image
	 * @return array $flashvars
	 **/

	function get_flashvars($file, $image = false) {
		$flashvars = array();
		$flashvars['file'] = $file;
		$flashvars['title'] = 'title';
		$flashvars['description'] = 'description';
		$flashvars['author'] = 'author';
		
		if ( $image )
			$flashvars['image'] = $image;
		
		$flashvars['skin'] = plugin_dir_url(__FILE__) . 'player/kleur.swf';
		$flashvars['plugins'] = 'quickkeys-1,viral-1';
		
		$flashvars['viral.onpause'] = 'false';
		$flashvars['viral.link'] = in_the_loop() ? get_permalink() : get_option('home');
		
		return apply_filters('mediacaster_flashvars', $flashvars);
	} # get_flashvars()


	/**
	 * display_audio()
	 *
	 * @param string $file
	 * @return string $player
	 **/

	function display_audio($file) {
		if ( strpos($file, '/') === false ) {
			$site_url = trailingslashit(site_url());
			$path = mediacaster::get_path(get_the_ID());
			$file = $site_url . $path . $file;
		}
		
		global $player_width;
		static $count = 0;
		
		if ( $cover = mediacaster::get_cover() ) {
			$cover_size = getimagesize(ABSPATH . $cover);
			$width = $cover_size[0];
			$height = $cover_size[1];
			
			if ( $width < 360 )
				$width = 360;
		} else {
			$width = $player_width;
			$height = 0;
		}
		
		$height += 59;
		
		$id = 'm' . md5($file . '_' . $count++);
		
		$player = plugin_dir_url(__FILE__) . 'player/player-viral.swf';
		
		$allowfullscreen = 'true';
		$allowscriptaccess = 'always';
		$allownetworking = 'all';
		
		$flashvars = mediacaster::get_flashvars($file, $image);
		$flashvars = apply_filters('mediacaster_audio_flashvars', $flashvars);
		$flashvars = http_build_query($flashvars, null, '&');
		
		$script = '';
		
		if ( !is_feed() )
			$script = <<<EOS
<script type="text/javascript">
var params = {};
params.allowfullscreen = "$allowfullscreen";
params.allowscriptaccess = "$allowscriptaccess";
//params.allownetworking = "$allownetworking";
params.flashvars = "$flashvars";

swfobject.embedSWF("$player", "$id", "$width", "$height", "9.0.0", false, false, params);
</script>
EOS;
		
		return <<<EOS

<div class="media_container"><div class="media" style="width: {$width}px; height: {$height}px;">
<object id="$id" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$width" height="$height">
<param name="movie" value="$player" />
<param name="allowfullscreen" value="$allowfullscreen" />
<param name="allowscriptaccess" value="$allowscriptaccess" />
<param name="allownetworking" value="$allownetworking" />
<param name="flashvars" value="$flashvars" />
<embed src="$player" width="$width" height="$height" allowfullscreen="$allowfullscreen" allowscriptaccess="$allowscriptaccess" allownetworking="$allownetworking" flashvars="$flashvars" />
</object>
</div></div>
$script

EOS;
	} # display_audio()


	/**
	 * display_video()
	 *
	 * @param string $file
	 * @return string $player
	 **/

	function display_video($file) {
		if ( strpos($file, '/') === false ) {
			$site_url = trailingslashit(site_url());
			$path = mediacaster::get_path(get_the_ID());
			$file = $site_url . $path . $file;
		}
		
		global $player_width;
		global $player_height;
		static $count = 0;
		
		$width = $player_width;
		$height = $player_height;
		
		$height += 59;
		
		preg_match("/\.([^.]+)$/", $file, $ext); 
		$ext = end($ext);
		
		$image = false;
		
		switch ( strtolower($ext) ) {
		case 'flv':
		case 'swf':
			$site_url = trailingslashit(site_url());

			$image = $file;
			$image = str_replace($site_url, '', $image);
			$image = str_replace('.' . $ext, '', $image);

			if ( defined('GLOB_BRACE') ) {
				$image = glob(ABSPATH . $image . '.{jpg,jpeg,png}', GLOB_BRACE);
			} else {
				$image = glob(ABSPATH . $image . '.jpg');
			}

			if ( $image ) {
				$image = current($image);
				$image = str_replace(ABSPATH, $site_url, $image);
			}

			break;
		}
		
		$id = 'm' . md5($file . '_' . $count++);
		
		$player = plugin_dir_url(__FILE__) . 'player/player-viral.swf';
		
		$allowfullscreen = 'true';
		$allowscriptaccess = 'always';
		$allownetworking = 'all';
		
		$flashvars = mediacaster::get_flashvars($file, $image);
		$flashvars = apply_filters('mediacaster_video_flashvars', $flashvars);
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

<div class="media_container"><div class="media" style="width: {$width}px; height: {$height}px;">
<object id="$id" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$width" height="$height">
<param name="movie" value="$player" />
<param name="allowfullscreen" value="$allowfullscreen" />
<param name="allowscriptaccess" value="$allowscriptaccess" />
<param name="allownetworking" value="$allownetworking" />
<param name="flashvars" value="$flashvars" />
<embed src="$player" width="$width" height="$height" allowfullscreen="$allowfullscreen" allowscriptaccess="$allowscriptaccess" allownetworking="$allownetworking" flashvars="$flashvars" />
</object>
</div></div>
$script

EOS;
	} # display_video()

	
	/**
	 * display_youtube()
	 *
	 * @param string $file
	 * @return string $player
	 **/

	function display_youtube($file) {
		global $player_width;
		global $player_height;
		static $count = 0;
		
		$width = $player_width;
		$height = round($width * 340 / 560);
		$height = round($width * 295 / 480);
		
		$id = 'm' . md5($file . '_' . $count++);
		
		$file = parse_url($file);
		$file = $file['query'];
		parse_str($file, $file);
		
		if ( empty($file['v']) )
			return;
		
		$file = $file['v'];
		$file = preg_replace("/[^a-z0-9_-]/i", '', $file);
		
		if ( !$file )
			return;
		
		# adjust height for youtube control
		$height += 4;
		
		$player = 'http://www.youtube.com/v/' . $file;
		
		$script = '';
		
		if ( !is_feed() )
			$script = <<<EOS
<script type="text/javascript">
var params = {};
params.allowfullscreen = "$allowfullscreen";
params.allowscriptaccess = "$allowscriptaccess";
params.allownetworking = "$allownetworking";

swfobject.embedSWF("$player", "$id", "$width", "$height", "9.0.0", false, false, params);
</script>
EOS;
		
		return <<<EOS

<div class="media_container"><div class="media" style="width: {$width}px; height: {$height}px;">
<object id="$id" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$width" height="$height">
<param name="movie" value="$player" />
<param name="allowfullscreen" value="$allowfullscreen" />
<param name="allowscriptaccess" value="$allowscriptaccess" />
<param name="allownetworking" value="$allownetworking" />
<embed src="$player" width="$width" height="$height" allowfullscreen="$allowfullscreen" allowscriptaccess="$allowscriptaccess" allownetworking="$allownetworking" />
</object>
</div></div>
$script

EOS;
	} # display_youtube()


	/**
	 * display_quicktime()
	 *
	 * @param string $file
	 * @param int $width
	 * @param int $height
	 * @return string $player
	 **/

	function display_quicktime($file) {
		if ( strpos($file, '/') === false ) {
			$site_url = trailingslashit(site_url());
			$path = mediacaster::get_path(get_the_ID());
			$file = $site_url . $path . $file;
		}
		
		if ( is_feed() ) {
			return '<div class="media">'
				. '<a href="' . esc_url($file) . '">'
				. basename($file)
				. '</a>'
				. '</div>';
		}

		$image = false;

		preg_match("/\.([^.]+)$/", $file, $ext); 
		$ext = end($ext);
		
		switch ( strtolower($ext) ) {
		case 'mov':
			$site_url = trailingslashit(site_url());

			$image = $file;
			$image = str_replace($site_url, '', $image);
			$image = str_replace('.' . $ext, '', $image);

			if ( defined('GLOB_BRACE') ) {
				$image = glob(ABSPATH . $image . '.{jpg,jpeg,png}', GLOB_BRACE);
			} else {
				$image = glob(ABSPATH . $image . '.jpg');
			}

			if ( $image ) {
				$image = current($image);
				$image = str_replace(ABSPATH, $site_url, $image);
			}

			break;
		}
		
		global $player_width;
		global $player_height;
		$width = $player_width;
		$height = $player_height;
		
		static $count = 0;
		$id = md5($file . '_' . $count++);
		
		$height += 16; # controller
		$file = esc_url($file);

		return <<<EOS

<div class="media_container">
<div class="media" style="width: {$width}px; height: {$height}px;">
<script type="text/javascript">
var so = new QTObject("$file", "$id", "$width", "$height");
so.addParam("autoplay","false");
so.addParam("loop","false");
so.addParam("targetcache","true");
so.write();
</script>
</div>
</div>

EOS;
	} # display_quicktime()
	
	
	/**
	 * display_file()
	 *
	 * @param string $file
	 * @return string $attachment
	 **/
	
	function display_file($file) {
		if ( strpos($file, '/') === false ) {
			$site_url = trailingslashit(site_url());
			$path = mediacaster::get_path(get_the_ID());
			$file = $site_url . $path . $file;
		}
		
		$label = basename($file);

		preg_match( "/(.+)\.([^\.]+)$/", $label, $label );
		$label = $label[1] . ' (' . $label[2] . ')';
		
		return '<div class="media_container">'
			. '<div class="media_attachment">'
			. '<a href="' . esc_url($file) . '">'
			. $label
			. '</a>'
			. '</div>'
			. '</div>';
	} # display_file()
	
	
	/**
	 * catch_playlist()
	 *
	 * @return void
	 **/

	function catch_playlist() {
		if ( isset($_GET['podcasts']) ) {
			mediacaster::display_playlist_xml($_GET['podcasts'], 'audio');
			die;
		} elseif ( isset($_GET['videos']) ) {
			mediacaster::display_playlist_xml($_GET['videos'], 'video');
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
		global $wpdb;

		$path = mediacaster::get_path($post_ID);

		$files = mediacaster::get_files($path, $post_ID);

		switch ( $type ) {
		case 'audio':
			$files = mediacaster::extract_podcasts($files, $post_ID, 'flash_audios');
			break;

		case 'video':
			$files = mediacaster::extract_podcasts($files, $post_ID, 'flash_videos');
			break;

		default:
			die;
		}

		$GLOBALS['wp_filter'] = array();

		while ( @ob_end_clean() );

		ob_start();

		header( 'Content-Type:text/xml; charset=' . get_option('blog_charset') ) ;

		echo '<?xml version="1.0" encoding="' . get_option('blog_charset') . '"?>' . "\n"
			. '<playlist version="1" xmlns="http://xspf.org/ns/0/">' . "\n";

		if ( $files ) {
			$site_url = trailingslashit(site_url());

			if ( $type == 'audio' ) {
				$cover = mediacaster::get_cover();
				if ( $cover )
					$cover = $site_url . $cover;
			}

			echo '<trackList>' . "\n";

			foreach ( $files as $key => $file ) {
				switch ( $type ) {
				case 'audio':
					$title = preg_replace("/\.mp3$/i", "", $key);
					break;

				case 'video':
					$title = preg_replace("/\.(flv|swf)$/i", "", $key);

					preg_match("/\.([^.]+)$/", $key, $ext); 
					$ext = end($ext);

					$image = $file;
					$image = str_replace($site_url, '', $image);
					$image = str_replace('.' . $ext, '', $image);

					if ( defined('GLOB_BRACE') ) {
						$image = glob(ABSPATH . $image . '.{jpg,jpeg,png}', GLOB_BRACE);
					} else {
						$image = glob(ABSPATH . $image . '.jpg');
					}

					if ( $image ) {
						$image = current($image);
						$cover = str_replace(ABSPATH, $site_url, $image);
					} else {
						$cover = false;
					}
					break;
				}

				echo '<track>' . "\n";

				echo '<title>'
					. $title
					. '</title>' . "\n";

				if ( $cover ) {
					echo '<image>'
						. $cover
						. '</image>' . "\n";
				}

				echo '<location>'
					. $file
					. '</location>' . "\n";

				echo '</track>' . "\n";
			}

			echo '</trackList>' . "\n";
		}

		echo '</playlist>';
	} # display_playlist_xml()

	
	/**
	 * get_path()
	 *
	 * @param mixed $post
	 * @return string $path
	 **/

	function get_path($post) {
		if ( is_numeric($post) ) {
			$post = get_post(intval($post));
		}

		if ( !is_admin() && ( $path = get_post_meta($post->ID, '_mediacaster_path', true) ) ) {
			return $path;
		}

		$post_ID = $post->ID;
		
		if ( $post->post_name == '' ) {
			$post->post_name = sanitize_title($post->post_title);
		}
		if ( $post->post_name == '' ) {
			$post->post_name = $post->ID;
		}

		$head = 'media/';

		if ( @ $post->post_type == 'page' ) {
			$tail = $post->post_name . '/';

			if ( $post->post_parent != 0 ) {
				while ( $post->post_parent != 0 ) {
					$post = get_post($post->post_parent);

					$tail = $post->post_name . '/' . $tail;
				}
			}

			$path = $head . $tail;
		} else {
			if ( !$post->post_date || $post->post_date == '0000-00-00 00:00:00') {
				$path = $head . date('Y/m/d', time()) . '/' . $post->post_name . '/';
			} else {
				$path = $head . date('Y/m/d', strtotime($post->post_date)) . '/' . $post->post_name . '/';
			}
		}

		if ( !is_admin() ) {
			delete_post_meta($post_ID, '_mediacaster_path');
			add_post_meta($post_ID, '_mediacaster_path', $path, true);
		}

		return $path;
	} # get_path()


	/**
	 * get_files()
	 *
	 * @param string $path
	 * @param int $post_ID
	 * @return void
	 **/

	function get_files($path, $post_ID = null) {
		$post_ID = (int) $post_ID;
		
		static $_files = array();
		
		if ( isset($_files[$post_ID]) )
			return $files[$post_ID];

		$site_url = trailingslashit(site_url());

		if ( defined('GLOB_BRACE') ) {
			$files = glob(ABSPATH . $path . '*.{mp3,flv,swf,m4a,mp4,m4v,mov,zip,gz,pdf}', GLOB_BRACE);
		} else {
			$files = array_merge(
				glob(ABSPATH . $path . '*.mp3'),
				glob(ABSPATH . $path . '*.flv'),
				glob(ABSPATH . $path . '*.swf'),
				glob(ABSPATH . $path . '*.m4a'),
				glob(ABSPATH . $path . '*.mp4'),
				glob(ABSPATH . $path . '*.m4v'),
				glob(ABSPATH . $path . '*.mov'),
				glob(ABSPATH . $path . '*.zip'),
				glob(ABSPATH . $path . '*.gz'),
				glob(ABSPATH . $path . '*.pdf')
				);
		}

		foreach ( (array) $files as $key => $file ) {
			unset($files[$key]);

			$file = basename($file);

			$files[$file] = $site_url . $path . $file;
		}

		if ( $post_ID ) {
			$enclosures = get_post_meta($post_ID, 'enclosure');

			if ( $enclosures ) {
				foreach ( (array) $enclosures as $enclosure ) {
					$file = basename($enclosure);
					$files[$file] = $enclosure;
				}
			}
		}

		ksort($files);
		
		$_files[$post_ID] = $files;

		return $files;
	} # get_files()


	/**
	 * extract_podcasts()
	 *
	 * @param array $files
	 * @param int $post_ID
	 * @param string $type
	 * @return void
	 **/

	function extract_podcasts($files, $post_ID, $type = 'flash_audios') {
		$podcasts = array();

		foreach ( $files as $key => $file ) {
			switch ( $type ) {
			case 'flash_audios':
				if ( strpos($key, '.mp3') !== false )
					$podcasts[$key] = $file;
				break;

			case 'flash_videos':
				if ( strpos($key, '.flv') !== false || strpos($key, '.mp4') !== false )
					$podcasts[$key] = $file;
				break;

			case 'qt_audios':
				if ( strpos($key, '.m4a') !== false )
					$podcasts[$key] = $file;
				break;

			case 'qt_videos':
				if ( strpos($key, '.m4v') !== false || strpos($key, '.mov') !== false )
					$podcasts[$key] = $file;
				break;
			}
		}

		if ( $podcasts ) {
			$post = get_post(intval($post_ID));

			switch ( $type ) {
			case 'flash_audios':
				$ext = 'mp3';
				break;

			case 'flash_videos':
				$ext = '(?:flv|mp4)';
				break;

			case 'qt_audios':
				$ext = 'm4a';
				break;

			case 'qt_videos':
				$ext = '(?:mov|m4v)';
				break;
			}

			preg_match_all("/
				(?:<!--|\[)
				(?:media)
				(?:\#|:)
				(
					[^>\]]+
					\.$ext
				)
				(?:-->|\])
				/ix",
				$post->post_content,
				$embeded
				);

			if ( $embeded )
				$embeded = end($embeded);
			else
				$embeded = array();

			foreach ( $embeded as $key )
				unset($podcasts[$key]);
		}

		return $podcasts;
	} # extract_podcasts()


	/**
	 * get_cover()
	 *
	 * @return string $cover
	 **/

	function get_cover() {
		static $cover;
		
		if ( !is_admin() && isset($cover) )
			return $cover;
		
		if ( defined('GLOB_BRACE') ) {
			$cover = glob(ABSPATH . 'media/cover{,-*}.{jpg,jpeg,png}', GLOB_BRACE);
		} else {
			$cover = glob(ABSPATH . 'media/cover-*.jpg');
		}
		
		if ( $cover ) {
			$cover = 'media/' . basename(current($cover));
		} else {
			$cover = false;
		}

		return $cover;
	} # get_cover()


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
			. '<copyright>&#xA9; ' . apply_filters('the_excerpt_rss', $options['itunes']['copyright']) . '</copyright>' . "\n\t\t"
			. '<itunes:author>' . apply_filters('the_excerpt_rss', $options['itunes']['author']) . '</itunes:author>' . "\n\t\t"
			. '<itunes:summary>' . apply_filters('the_excerpt_rss', $options['itunes']['summary']) . '</itunes:summary>' . "\n\t\t"
			. '<itunes:explicit>' . apply_filters('the_excerpt_rss', $options['itunes']['explicit']) . '</itunes:explicit>' . "\n\t\t"
			. '<itunes:block>' . apply_filters('the_excerpt_rss', $options['itunes']['block']) . '</itunes:block>' . "\n\t\t"
			;

		$image = 'wp-content/itunes/' . $options['itunes']['image']['name'];

		if ( file_exists(ABSPATH . $image) ) {
			echo '<itunes:image href="' . esc_url($site_url . 'wp-content/itunes/' . $options['itunes']['image']['name']) . '" />' . "\n\t\t"
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
		$site_url = trailingslashit(site_url());

		global $post;

		$path = mediacaster::get_path($post);

		$files = mediacaster::get_files($path);

		$add_itune_tags = false;

		foreach ( $files as $key => $file ) {
			preg_match("/\.([^.]+)$/", $key, $ext); 
			$ext = end($ext);

			switch ( strtolower($ext) ) {
				case 'mp3':
					$mime = 'audio/mpeg';
					break;
				case 'm4a':
					$mime = 'audio/x-m4a';
					break;
				case 'mp4':
					$mime = 'video/mp4';
					break;
				case 'm4v':
					$mime = 'video/x-m4v';
					break;
				case 'mov':
					$mime = 'video/quicktime';
					break;
				case 'pdf':
					$mime = 'application/pdf';
					break;
				case 'zip':
					$mime = 'application/gzip';
					break;
				case 'gz':
					$mime = 'application/x-gzip';
					break;
				default:
					$mime = 'audio/mpeg';
					break;
			}

			$size = @filesize(ABSPATH . $path . $key);

			echo "\n\t\t"
				. '<enclosure'
				. ' url="'
					.  $file
					. '"'
				. ' length="' . $size . '"'
				. ' type="' . $mime . '"'
				. ' />';

			$add_itunes_tags = true;
		}

		if ( $add_itunes_tags && !class_exists('podPress_class') && is_feed() ) {
			$author = get_the_author();

			$summary = get_post_meta(get_the_ID(), '_description', true);
			if ( !$summary ) {
				$summary = get_the_excerpt();
			}

			$keywords = get_post_meta(get_the_ID(), '_keywords', true);
			if ( !$keywords ) {
				$keywords = array();

				if ( $cats = get_the_category(get_the_ID()) ) {
					foreach ( $cats as $cat ) {
						$keywords[] = $cat->name;
					}
				}

				if ( $tags = get_the_tags(get_the_ID()) ) {
					foreach ( $tags as $tag ) {
						$keywords[] = $tag->name;
					}
				}

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

		$admin_user = $wpdb->get_row("
			SELECT	$wpdb->users.*
			FROM	$wpdb->users
			INNER JOIN $wpdb->usermeta
				ON $wpdb->usermeta.user_id = $wpdb->users.ID
			WHERE	$wpdb->usermeta.meta_key = 'wp_capabilities'
			AND		$wpdb->usermeta.meta_value LIKE '%administrator%'
			ORDER BY $wpdb->users.ID ASC
			LIMIT 1;
			");

		$options['itunes']['author'] = $admin_user->user_nicename;

		$options['itunes']['summary'] = get_option('blogdescription');

		$options['itunes']['explicit'] = 'No';

		$options['itunes']['block'] = 'No';

		$options['itunes']['copyright'] = $options['itunes']['author'];

		$options['player']['width'] = 320;
		$options['player']['height'] = 180;
		$options['player']['position'] = 'top';

		$options['compat'] = false;

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
	
	
	/**
	 * meta_boxes()
	 *
	 * @return void
	 **/

	function meta_boxes() {
		add_meta_box('mediacaster', 'Mediacaster', array('mediacaster_admin', 'edit_media'), 'post', 'normal');
		add_meta_box('mediacaster', 'Mediacaster', array('mediacaster_admin', 'edit_media'), 'page', 'normal');
	} # meta_boxes()
} # mediacaster

function mediacaster_admin() {
	include dirname(__FILE__) . '/mediacaster-admin.php';
}

if ( !function_exists('load_multipart_entry') ) :
function load_multipart_entry() {
	include dirname(__FILE__) . '/multipart-entry/multipart-entry.php';
}
endif;

foreach ( array('post.php', 'post-new.php', 'page.php', 'page-new.php') as $hook ) {
	add_action("load-$hook", 'mediacaster_admin');
	add_action("load-$hook", 'load_multipart_entry');
	add_action("load-$hook", array('mediacaster_admin', 'init_editor'), 20);
}

add_action('load-settings_page_mediacaster', 'mediacaster_admin');
?>