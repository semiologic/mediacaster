<?php
/*
Plugin Name: Mediacaster
Plugin URI: http://www.semiologic.com/software/mediacaster/
Description: Lets you add podcasts, videos, and formatted download links in your site's posts and pages.
Version: 2.0.6
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
Text Domain: mediacaster
Domain Path: /lang
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts (http://www.mesoconcepts.com), and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
*/


load_plugin_textdomain('mediacaster', false, dirname(plugin_basename(__FILE__)) . '/lang');


/**
 * mediacaster
 *
 * @package Mediacaster
 **/

class mediacaster {
	/**
	 * ext2type()
	 *
	 * @param array $types
	 * @return array $types
	 **/

	function ext2type($types) {
		$types['video'] = array_merge($types['video'], array('flv', 'f4b', 'f4p', 'f4v'));
		$types['audio'] = array_merge($types['audio'], array('3pg', '3g2'));
		$types['code'] = array_merge($types['code'], array('diff', 'patch'));
		return $types;
	} # ext2type()
	
	
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
		if ( !isset($mimes['diff|patch']) )
			$mimes['diff|patch'] = 'text/plain';
		if ( !isset($mimes['rar']) )
			$mimes['rar'] = 'application/x-rar-compressed';
		return $mimes;
	} # upload_mimes()
	
	
	/**
	 * wp_get_attachment_url()
	 *
	 * @param string $url
	 * @param int $post_id
	 * @return string $url
	 **/

	function wp_get_attachment_url($url, $post_id) {
		$src = get_post_meta($post_id, '_mc_src', true);
		return $src ? $src : $url;
	} # wp_get_attachment_url()
	
	
	/**
	 * get_attached_file()
	 *
	 * @param string $path
	 * @param int $post_id
	 * @return string $path
	 **/

	function get_attached_file($path, $post_id) {
		$src = get_post_meta($post_id, '_mc_src', true);
		return $src ? $src : $path;
	} # get_attached_file()
	
	
	/**
	 * template_redirect()
	 *
	 * @return void
	 **/

	function template_redirect() {
		if ( preg_match("/\/mc-snapshot\.(\d+)\.(\d+)\.([0-9a-f]+)\.php/i", $_SERVER['REQUEST_URI'], $match) ) {
			include_once ABSPATH . 'wp-admin/includes/admin.php';
			include_once dirname(__FILE__) . '/mediacaster-admin.php';
			$nonce = array_pop($match);
			$user_id = array_pop($match);
			$post_id = array_pop($match);
			mediacaster_admin::create_snapshot($post_id, $user_id, $nonce);
			die;
		} elseif ( isset($_GET['podcasts']) && intval($_GET['podcasts']) ) {
			mediacaster::display_playlist_xml($_GET['podcasts']);
			die;
		} elseif ( !is_attachment() ) {
			return;
		}
		
		global $wp_the_query;
		$attachment = $wp_the_query->get_queried_object();
		
		$src = wp_get_attachment_url($attachment->ID);
		$regexp = mediacaster::get_extensions();
		$regexp = '/\\.' . implode('|', $regexp) . '$/i';
		
		if ( !preg_match($regexp, $src) )
			return;
		
		$args = array(
			'id' => $attachment->ID,
			'post_id' => $attachment->post_parent,
			'autostart' => 'autostart',
			'standalone' => 'standalone',
			);
		
		include dirname(__FILE__) . '/media.php';
		die;
	} # template_redirect()
	
	
	/**
	 * shortcode()
	 *
	 * @param array $args
	 * @param string $content
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
		} else {
			$args['src'] = esc_url_raw(str_replace(' ', rawurlencode(' '), $args['src']));
		}
		
		if ( empty($args['type']) ) {
			if ( preg_match("/^https?:\/\/(?:www\.)?youtube\.com\//i", $args['src']) ) {
				$args['type'] = 'youtube';
			} elseif ( preg_match("/[\/=](rss2?|xml|feed|atom)(\/|&|$)/i", $args['src']) ) {
				$args['type'] = 'audio';
			} else {
				$type = wp_check_filetype($args['src']);
				$args['type'] = $type['ext'];
			}
		}
		
		if ( !empty($attachment) && $attachment->post_parent
			&& in_array($args['type'], array('mp3', 'm4a', 'aac', 'audio'))
			&& preg_match("/^audio\//i", $attachment->post_mime_type) ) {
			$enclosed = get_post_meta($attachment->post_parent, '_mc_enclosed');
			if ( $enclosed )
				$enclosed = array_map('intval', $enclosed);
			else
				$enclosed = array();
			if ( !in_array($attachment->ID, $enclosed) )
				add_post_meta($attachment->post_parent, '_mc_enclosed', $attachment->ID);
		}
		
		foreach ( array('width', 'height') as $arg ) {
			if ( isset($args[$arg]) && !is_numeric($args[$arg]) )
				unset($args[$arg]);
		}
		
		foreach ( array('autostart', 'thickbox', 'ltas') as $arg ) {
			if ( isset($args[$arg]) )
				continue;
			
			$args[$arg] = array_search($arg, $args) !== false;
		}
		
		switch ( $args['type'] ) {
		case 'youtube':
			return mediacaster::youtube($args);
		
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
		case 'video':
			return mediacaster::video($args, $content);
		
		default:
			return mediacaster::file($args, $content);
		}
	} # shortcode()
	
	
	/**
	 * youtube()
	 *
	 * @param array $args
	 * @return void
	 **/

	function youtube($args) {
		static $count = 0;
		extract($args, EXTR_SKIP);
		extract(mediacaster::defaults());
		
		if ( empty($width) )
			$width = $max_width;
		if ( empty($height) )
			$height = (int) round($max_width * 2 / 3);
		
		if ( $width > $max_width ) {
			$height = (int) round($height * $max_width / $width);
			$width = $max_width;
		}
		
		$hd = 0;//$width >= 480 && $height >= 270 ? 1 : 0; // 480px wide, 16:9 format
		
		foreach ( array(array(3, 2), array(4, 3), array(16, 9), array(18.5, 10), array(24,10)) as $ratio ) {
			list($w, $h) = $ratio;
			if ( abs(round($h * $width / $height, 1) - $w) <= .1 ) {
				$height += 25; // controlbar
				break;
			}
		}
		
		$allowscriptaccess = 'false';
		$allowfullscreen = 'true';
		$wmode = 'transparent';
		
		$src = trim($src, ' "');
		$src = trim($src);
		$src = preg_replace("|.*/p/([^/]+)/?|", "/?p=$1&", $src); // http://www.youtube.com/p/foobar
		$src = preg_replace("|.*/v/([^/]+)/?|", "/?v=$1&", $src); // http://www.youtube.com/v/foobar
		$src = preg_replace("|.*#play/user/([^/]+)|", "/?p=$1", $src); // http://www.youtube.com/user/foo#play/user/bar
		$src = str_replace(array('&amp;', '&#038;'), '&', $src);
		$src = @parse_url($src);
		
		if ( !isset($src['query']) )
			return '';
		
		parse_str($src['query'], $src);
		
		if ( isset($src['p']) ) {
			$src = preg_replace("/[^0-9a-z_-]/i", '', $src['p']);
			if ( !$src )
				return '';
			$src = 'p/' . $src;
		} elseif ( isset($src['v']) ) {
			$src = preg_replace("/[^0-9a-z_-]/i", '', $src['v']);
			if ( !$src )
				return '';
			$src = 'v/' . $src;
		} else {
			return '';
		}
		
		$player = 'http://www.youtube.com/' . $src . '&fs=1&rel=0&border=0&showinfo=0&showsearch=0&hd=' . $hd;
		
		if ( in_the_loop() )
			$salt = get_the_ID();
		elseif ( is_singular() || is_attachment() )
			$salt = $GLOBALS['wp_query']->get_queried_object_id();
		else
			$salt = uniqid(rand());
		
		$player_id = 'm' . md5($src . '_' . $count++ . '_' . $salt);
		
		$script = '';
		
		if ( !is_feed() )
			$script = <<<EOS
<script type="text/javascript">
var params = {};
params.allowfullscreen = "$allowfullscreen";
params.allowscriptaccess = "$allowscriptaccess";
params.wmode = "$wmode";

var flashvars = {};

var attributes = {
  id: "$player_id"
};
swfobject.embedSWF("$player", "$player_id", "$width", "$height", "9.0.0", false, flashvars, params, attributes);
</script>
EOS;
		
		return <<<EOS

<div class="media_container"><div class="media" style="width: {$width}px; height: {$height}px;"><object id="$player_id" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$width" height="$height"><param name="movie" value="$player" /><param name="allowfullscreen" value="$allowfullscreen" /><param name="allowscriptaccess" value="$allowscriptaccess" /><param name="wmode" value="$wmode" /><param name="flashvars" value="$flashvars_html" /><embed src="$player" pluginspage="http://www.macromedia.com/go/getflashplayer" width="$width" height="$height" allowfullscreen="$allowfullscreen" allowscriptaccess="$allowscriptaccess" wmode="$wmode" flashvars="$flashvars_html" /></object></div></div>

$script

EOS;
	} # youtube()
	
	
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
		extract(mediacaster::defaults());
		extract(mediacaster::get_skin($skin));
		
		foreach ( array('width', 'height', 'id', 'standalone', 'link') as $arg ) {
			if ( empty($$arg) )
				$$arg = false;
		}
		
		$thickbox = false;
		$autostart = mediacaster::autostart($autostart) || $standalone;
		
		if ( $id && !$link )
			$link = get_post_meta($id, '_mc_link', true);
		
		if ( $cover ) {
			$image = WP_CONTENT_URL . $cover;
			static $cover_size;
			
			if ( !isset($cover_size) )
				$cover_size = getimagesize(WP_CONTENT_DIR . $cover);
			
			if ( $cover_size && $cover_size[0] && $cover_size[1] ) {
				if ( !$width ) {
					$width = $cover_size[0];
					$height = $cover_size[1];
				} elseif ( !$height ) {
					$height = (int) round($width * $cover_size[1] / $cover_size[0]);
				}
			}
			
			if ( $width > $max_width ) {
				$height = (int) round($height * $max_width / $width);
				$width = $max_width;
			}
		} else {
			$image = false;
			if ( !$width )
				$width = 360;
			else
				$width = max($width, $max_width);
			$height = 0;
		}
		
		if ( in_the_loop() )
			$salt = get_the_ID();
		elseif ( is_singular() || is_attachment() )
			$salt = $GLOBALS['wp_query']->get_queried_object_id();
		else
			$salt = uniqid(rand());
		
		$player_id = 'm' . md5($src . '_' . $count++ . '_' . $salt);
		
		$allowfullscreen = 'false';
		$allowscriptaccess = 'always';
		$wmode = 'transparent';
		
		$flashvars = array();
		$flashvars['file'] = esc_url_raw($src);
		$flashvars['skin'] = esc_url_raw("$skin_dir/$skin.swf");
		
		if ( $image )
			$flashvars['image'] = esc_url_raw($image);
		
		$flashvars['repeat'] = 'list';
		
		$flashvars['plugins'] = array('quickkeys-1');
		
		if ( $width >= $min_width ) {
			if ( $image ) {
				$allowfullscreen = 'true';
				$flashvars['controlbar'] = 'over';
			} else {
				$height += $skin_height;
			}
			
			if ( $link )
				$flashvars['link'] = esc_url_raw($link);
		} else {
			$width = max($width, 50);
			$height = max($height, 50);
			$flashvars['controlbar'] = 'none';
		}
		
		if ( $autostart )
			$flashvars['autostart'] = 'true';
		
		$flashvars = apply_filters('mediacaster_audio', $flashvars, $args);
		$flashvars['plugins'] = implode(',', $flashvars['plugins']);
		$flashvars_js = '';
		foreach ( $flashvars as $k => $v )
			$flashvars_js .= "flashvars['" . addslashes($k) . "'] = '" . addslashes($v) . "';\n";
		$flashvars_html = http_build_query($flashvars, null, '&amp;');
		
		$script = '';
		
		if ( !is_feed() )
			$script = <<<EOS
<script type="text/javascript">
var params = {};
params.allowfullscreen = "$allowfullscreen";
params.allowscriptaccess = "$allowscriptaccess";
params.wmode = "$wmode";

var flashvars = {};
$flashvars_js
var attributes = {
  id: "$player_id",
  name: "$player_id"
};
swfobject.embedSWF("$player", "$player_id", "$width", "$height", "9.0.0", false, flashvars, params, attributes);
</script>
EOS;
		
		return <<<EOS

<div class="media_container"><div class="media" style="width: {$width}px; height: {$height}px;"><object id="$player_id" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$width" height="$height"><param name="movie" value="$player" /><param name="allowfullscreen" value="$allowfullscreen" /><param name="allowscriptaccess" value="$allowscriptaccess" /><param name="wmode" value="$wmode" /><param name="flashvars" value="$flashvars_html" /><embed src="$player" pluginspage="http://www.macromedia.com/go/getflashplayer" width="$width" height="$height" allowfullscreen="$allowfullscreen" allowscriptaccess="$allowscriptaccess" wmode="$wmode" flashvars="$flashvars_html" /></object></div></div>

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
		extract(mediacaster::defaults());
		extract(mediacaster::get_skin($skin));
		
		foreach ( array('width', 'height', 'image_width', 'image_height', 'id', 'standalone', 'link', 'image', 'thumbnail') as $arg ) {
			if ( empty($$arg) )
				$$arg = false;
		}
		
		if ( !$image )
			$image = get_post_meta($id, '_mc_image', true);
		$image_id = $id ? get_post_meta($id, '_mc_image_id', true) : false;
		
		if ( ( !$image_width || !$image_height ) && $id ) {
			$image_width = (int) get_post_meta($id, '_mc_width', true);
			$image_height = (int) get_post_meta($id, '_mc_height', true);
			
			if ( ( !$image_width || !$image_height ) && $image ) {
				$image_size = get_post_meta($id, '_mc_image_size', true);
				
				if ( $image_size === '' ) {
					$image_size = @getimagesize($image);
					if ( !$image_size )
						$image_size = array();
					update_post_meta($id, '_mc_image_size', $image_size);
				}
				
				if ( $image_size ) {
					$image_width = (int) $image_size[0];
					$image_height = (int) $image_size[1];
				}
			}
			
			if ( ( !$image_width || !$image_height ) && $image_id ) {
				$image = wp_get_attachment_url($image_id);
				$image_width = (int) get_post_meta($id, '_mc_image_width', true);
				$image_height = (int) get_post_meta($id, '_mc_image_height', true);
			}
		}
		
		if ( !$image && $image_id )
			$image = wp_get_attachment_url($image_id);
		
		if ( $id && !$link )
			$link = get_post_meta($id, '_mc_link', true);
		
		$width = (int) $width;
		$height = (int) $height;
		
		$max_tb_width = 780;
		$max_tb_height = 540;
		
		if ( !$thumbnail )
			$thumbnail = get_post_meta($id, '_mc_thumbnail', true);
		
		if ( !is_feed() && $thickbox && ( $image || $thumbnail ) && $id ) {
			$href = apply_filters('the_permalink', get_permalink($id));
			
			$thumbnail_size = get_post_meta($id, '_mc_thumbnail_size', true);
			
			if ( $thumbnail && $thumbnail_size ) {
				$image = $thumbnail;
				if ( !$width && !$height ) {
					$width = $thumbnail_size[0];
					$height = $thumbnail_size[1];
				} elseif ( !$height ) {
					$height = (int) round($thumbnail_size[1] * $width / $thumbnail_size[0]);
				}
			}
			
			$tb_width = $image_width;
			$tb_height = $image_height;
			
			if ( !$tb_width )
				$tb_width = $max_width;
			if ( !$width )
				$width = $image_width ? $image_width : $max_width;
			if ( !$height && $image_width && $image_height )
				$height = (int) round($image_height * $width / $image_width);
			
			if ( $width > $max_width ) {
				$height = (int) round($height * $max_width / $width);
				$width = $max_width;
			}
			
			if ( $tb_width > $max_tb_width ) {
				$tb_height = (int) round($tb_height * $max_tb_width / $tb_width);
				$tb_width = $max_tb_width;
			}
			
			if ( !$height && !$tb_height ) {
				$height = (int) round($width * 2 / 3);
				$tb_height = (int) round($tb_width * 2 / 3);
			} elseif ( !$height ) {
				$height = (int) round($width * 2 / 3);
			} elseif ( !$tb_height ) {
				$tb_height = (int) $height * $tb_width / $width;
			}
			
			if ( $tb_height > $max_tb_height ) {
				$tb_width = (int) round($tb_width * $max_tb_height / $tb_height);
				$tb_height = $max_tb_height;
			}
			
			$tb_height += 10; // title bar
			
			$href = rtrim($href, '?');
			$href = @html_entity_decode($href, ENT_COMPAT, get_option('blog_charset'));
			$href .= ( strpos($href, '?') === false ? '?' : '&' )
				. "TB_iframe=true&width=$tb_width&height=$tb_height";
			$href = esc_url($href);
			
			$title = trim(strip_tags($content));
			if ( $title )
				$title = ' title="' . esc_attr($title) . '"';
			
			$image = esc_url($image);
			
			$tip_text = __('Click to Play the Video', 'mediacaster');
			
			return <<<EOS

<div class="media_container">
<div class="media media_caption">
<a href="$href" class="thickbox no_icon" $title><img src="$image" width="$width" alt="" /><br />$tip_text</a>
</div>
</div>

EOS;
		}
		
		$autostart = mediacaster::autostart($autostart) || $standalone;
		$thickbox = $thickbox && !is_feed();
		
		if ( $standalone ) {
			$image = false;
			$max_width = $max_tb_width;
			$max_height = $max_tb_height;
			
			if ( $image_width ) {
				$width = $image_width;
				$height = $image_height;
			}
		} else {
			$max_height = false;
			
			if ( !$width ) {
				if ( $image_width ) {
					$width = $image_width;
					$height = $image_height;
				} else {
					$width = $max_width;
				}
			}
		}
		
		if ( !$height ) {
			if ( $image_height )
				$height = (int) round($image_height * $width / $image_width);
			else
				$height = (int) round($width * 2 / 3);
		}
		
		if ( $width > $max_width ) {
			$height = (int) round($height * $max_width / $width);
			$width = $max_width;
		}
		
		if ( $max_height && $height > $max_height ) {
			$width = (int) round($width * $max_height / $height);
			$height = $max_height;
		}
		
		if ( in_the_loop() )
			$salt = get_the_ID();
		elseif ( is_singular() || is_attachment() )
			$salt = $GLOBALS['wp_query']->get_queried_object_id();
		else
			$salt = uniqid(rand());
		
		$player_id = 'm' . md5($src . '_' . $count++ . '_' . $salt);
		
		$allowfullscreen = 'true';
		$allowscriptaccess = 'always';
		$wmode = 'transparent';
		
		$flashvars = array();
		$flashvars['file'] = esc_url_raw($src);
		$flashvars['skin'] = esc_url_raw("$skin_dir/$skin.swf");
		
		if ( $image )
			$flashvars['image'] = esc_url_raw($image);
		
		$flashvars['repeat'] = 'list';
		
		$flashvars['plugins'] = array('quickkeys-1');
		$flashvars['dock'] = 'true';
		
		if ( $width >= $min_width) {
			$flashvars['controlbar'] = 'over';
			
			if ( $link )
				$flashvars['link'] = esc_url_raw($link);
			
			/*
			$flashvars['plugins'][] = 'sharing-1';
			if ( in_the_loop() ) {
				$flashvars['sharing.link'] = apply_filters('the_permalink', get_permalink());
			} elseif ( is_singular() ) {
				$flashvars['sharing.link'] = apply_filters('the_permalink', get_permalink($GLOBALS['wp_query']->get_queried_object_id()));
			} else {
				$flashvars['sharing.link'] = user_trailingslashit(get_option('home'));
			}
			*/
		} else {
			$width = max($width, 50);
			$height = max($height, 50);
			$flashvars['controlbar'] = 'none';
		}
		
		if ( $autostart )
			$flashvars['autostart'] = 'true';
		
		$ltas_id = '';
		$ltas_class = '';
		if ( $ltas && !is_feed() && $width >= 300 && $height >= 250 && !empty($script) ) {
			$ltas_id = 'id="' . $player_id . '-ad"';
			$ltas_class = 'ltas-ad';
			$flashvars['plugins'][] = 'ltas';
			$flashvars['channel'] = $channel;
			if ( $id ) {
				$attachment = get_post($id);
				if ( $attachment->post_content ) {
					$flashvars['ltas.mediaid'] = md5($src);
					$flashvars['title'] = strip_tags(preg_replace("/\s+/", ' ', $attachment->post_title));
					$flashvars['description'] = strip_tags(preg_replace("/\s+/", ' ', $attachment->post_content));
				}
			}
		}
		
		$flashvars = apply_filters('mediacaster_video', $flashvars, $args);
		$flashvars['plugins'] = implode(',', $flashvars['plugins']);
		$flashvars_js = '';
		foreach ( $flashvars as $k => $v )
			$flashvars_js .= "flashvars['" . addslashes($k) . "'] = '" . addslashes($v) . "';\n";
		foreach ( array('title', 'description') as $var ) {
			if ( !empty($flashvars['title']) )
				$flashvars['title'] = rawurlencode($flashvars['title']);
		}
		$flashvars_html = http_build_query($flashvars, null, '&amp;');
		
		if ( !is_feed() )
			$script = <<<EOS
<script type="text/javascript">
var params = {};
params.allowfullscreen = "$allowfullscreen";
params.allowscriptaccess = "$allowscriptaccess";
params.wmode = "$wmode";

var flashvars = {};
$flashvars_js
var attributes = {
  id: "$player_id",
  name: "$player_id"
};
swfobject.embedSWF("$player", "$player_id", "$width", "$height", "9.0.0", false, flashvars, params, attributes);
</script>
EOS;
		
		return <<<EOS

<div class="media_container"><div $ltas_id class="media $ltas_class" style="width: {$width}px; height: {$height}px;"><object id="$player_id" name="$player_id" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$width" height="$height"><param name="movie" value="$player" /><param name="allowfullscreen" value="$allowfullscreen" /><param name="allowscriptaccess" value="$allowscriptaccess" /><param name="wmode" value="$wmode" /><param name="flashvars" value="$flashvars_html" /><embed id="{$player_id}-2" name="{$player_id}-2" src="$player" pluginspage="http://www.macromedia.com/go/getflashplayer" width="$width" height="$height" allowfullscreen="$allowfullscreen" allowscriptaccess="$allowscriptaccess" wmode="$wmode" flashvars="$flashvars_html" /></object></div></div>

$script

EOS;
	} # video()
	
	
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
		
		$min_width = 320;
		$max_width = intval($content_width);
		
		if ( $max_width && !defined('sem_version') )
			$max_width -= 10; # add some margin space
		
		if ( !$max_width || is_feed() )
			$max_width = 420;
		
		$cover = $o['player']['cover'];
		$skin = $o['player']['skin'];
		
		if ( $o['longtail']['licensed'] )
			$player = plugin_dir_url(__FILE__) . $o['longtail']['licensed'];
		else
			$player = plugin_dir_url(__FILE__) . 'mediaplayer/player.swf';
		$skin_dir = plugin_dir_url(__FILE__) . 'skins';
		
		$script = $o['longtail']['script'];
		$channel = $o['longtail']['channel'];
		
		$defaults = compact('min_width', 'max_width', 'cover', 'skin', 'player', 'skin_dir', 'script', 'channel');
		
		return $defaults;
	} # defaults()
	
	
	/**
	 * get_skin()
	 *
	 * @param string $skin
	 * @return array $skin_details
	 **/
	
	function get_skin($skin = 'bekle') {
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
		
		return isset($skin_details[$skin]) ? $skin_details[$skin] : $skin_details['bekle'];
	} # get_skin()
	
	
	/**
	 * autostart()
	 *
	 * @param string $autostart
	 * @return string $autostart
	 **/

	function autostart($autostart = false) {
		static $autostarted = false;
		
		if ( $autostarted )
			return false;
		
		$autostarted |= (bool) $autostart;
		
		return $autostart;
	} # autostart()
	
	
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
		$icon = esc_url(wp_mime_type_icon(wp_ext2type($mime['ext'])));
		
		$src = esc_url($src);
		
		$title = apply_filters('mediacaster_file', $title, $args);
		
		return <<<EOS
<div class="media_container media_attachment">
<a href="$src" class="download_event no_icon" style="background-image: url($icon);">
$title
</a>
</div>
EOS;
	} # file()
	
	
	/**
	 * disable()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/

	function disable($in = null) {
		remove_filter('the_content', array('mediacaster', 'podcasts'), 12);
		
		return $in;
	} # disable()
	
	
	/**
	 * enable()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/
	
	function enable($in = null) {
		add_filter('the_content', array('mediacaster', 'podcasts'), 12);
		
		return $in;
	} # enable()
	
	
	/**
	 * scripts()
	 *
	 * @return void
	 **/

	function scripts() {
		wp_enqueue_script('swfobject');
		wp_enqueue_script('thickbox');
	} # scripts()
	
	
	/**
	 * styles()
	 *
	 * @return void
	 **/
	
	function styles() {
		$folder = plugin_dir_url(__FILE__);
		$css = $folder . 'css/mediacaster.css';
		
		wp_enqueue_style('mediacaster', $css, null, '20090903');
		wp_enqueue_style('thickbox');
	} # styles()
	
	
	/**
	 * ltas_scripts()
	 *
	 * @return void
	 **/

	function ltas_scripts() {
		$o = get_option('mediacaster');
		
		if ( !empty($o['longtail']['script']) )
			echo $o['longtail']['script'] . "\n";
	} # ltas_scripts()
	
	
	/**
	 * thickbox_images()
	 *
	 * @return void
	 **/

	function thickbox_images() {
		if ( class_exists('auto_thickbox') )
			return;
		
		$includes_url = includes_url();
		
		echo <<<EOS

<script type="text/javascript">
var tb_pathToImage = "{$includes_url}js/thickbox/loadingAnimation.gif";
var tb_closeImage = "{$includes_url}js/thickbox/tb-close.png";
</script>

EOS;
	} # thickbox_images()
	
	
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
		
		global $wpdb;
		
		$enclosures = get_post_meta($post->ID, '_mc_enclosures', true);
		
		if ( $enclosures !== '' && !$enclosures )
			return array();
		
		$do_enclosures = $wpdb->get_results("
			SELECT	*
			FROM	$wpdb->posts
			WHERE	post_type = 'attachment'
			AND		post_parent = $post->ID
			AND		post_mime_type NOT LIKE 'image/%'
			AND		post_status = 'inherit'
			ORDER BY menu_order, post_title, ID
			");
		update_post_cache($do_enclosures);
		
		$to_cache = array();
		foreach ( $do_enclosures as $enclosure )
			$to_cache[] = $enclosure->ID;
		update_postmeta_cache($to_cache);
		
		if ( $enclosures === '' )
			update_post_meta($post->ID, '_mc_enclosures', count($to_cache));
		
		if ( !$to_cache )
			return array();
		
		$enclosed = get_post_meta($post->ID, '_mc_enclosed');
		if ( $enclosed )
			$enclosed = array_map('intval', $enclosed);
		else
			$enclosed = array();
		
		if ( $playlist ) {
			foreach ( $do_enclosures as $key => $enclosure ) {
				if ( in_array($enclosure->ID, $enclosed) || !in_array($enclosure->post_mime_type, array('audio/mpeg', 'audio/mp3', 'audio/aac')) )
					unset($do_enclosures[$key]);
			}
		}
		
		return $do_enclosures;
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
		
		$args = array(
			'src' => user_trailingslashit(get_option('home')) . '?podcasts=' . get_the_ID(),
			'autostart' => false,
			);
		
		$out = mediacaster::audio($args);
		
		if ( $options['player']['position'] != 'bottom' )
			$content = $out . $content;
		else
			$content = $content . $out;
		
		return $content;
	} # podcasts()


	/**
	 * display_playlist_xml()
	 *
	 * @param int $post_ID
	 * @return void
	 **/

	function display_playlist_xml($post_ID) {
		$post_ID = intval($post_ID);
		
		$podcasts = mediacaster::get_enclosures($post_ID, true);
		
		#status_header(200);
		#dump($podcasts);die;
		
		# Reset WP
		$levels = ob_get_level();
		for ($i=0; $i<$levels; $i++)
			ob_end_clean();
		
		status_header(200);
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

			$link = get_post_meta($podcast->ID, '_mc_link', true);
			
			if ( $link ) {
				echo '<info>'
					. esc_url($link)
					. '</info>' . "\n";
			}
			
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
			. '<copyright>' . htmlentities($options['itunes']['copyright'], ENT_COMPAT, get_option('blog_charset')) . '</copyright>' . "\n\t\t"
			. '<itunes:author>' . htmlentities($options['itunes']['author'], ENT_COMPAT, get_option('blog_charset')) . '</itunes:author>' . "\n\t\t"
			. '<itunes:summary>' . htmlentities($options['itunes']['summary'], ENT_COMPAT, get_option('blog_charset')) . '</itunes:summary>' . "\n\t\t"
			. '<itunes:explicit>' . htmlentities(ucfirst($options['itunes']['explicit']), ENT_COMPAT, get_option('blog_charset')) . '</itunes:explicit>' . "\n\t\t"
			. '<itunes:block>' . htmlentities(ucfirst($options['itunes']['block']), ENT_COMPAT, get_option('blog_charset')) . '</itunes:block>' . "\n\t\t"
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
					$category = '<itunes:category text="' . esc_attr(htmlentities($cat, ENT_COMPAT, get_option('blog_charset'))) . '" />' . "\n\t\t";

					if ( $cat = array_pop($cats) ) {
						$cat = trim($cat);

						$category = '<itunes:category text="' . esc_attr(htmlentities($cat, ENT_COMPAT, get_option('blog_charset'))) . '">' . "\n\t\t\t"
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
		if ( !in_the_loop() || !is_feed() )
			return;
		
		$post_ID = get_the_ID();
		
		$enclosures = mediacaster::get_enclosures($post_ID, false);
		
		if ( !$enclosures )
			return;
		
		$add_itunes_tags = false;
		
		foreach ( $enclosures as $enclosure ) {
			$file = get_attached_file($enclosure->ID);
			$file_url = esc_url(wp_get_attachment_url($enclosure->ID));
			$mime = $enclosure->post_mime_type;
			$size = strpos($file, 'http') !== 0 ? @filesize($file) : false;
			
			if ( $size ) {
				echo "\n\t\t"
					. '<enclosure' . ' url="' .  $file_url . '" length="' . $size . '" type="' . $mime . '" />';
			} else {
				echo "\n\t\t"
					. '<enclosure' . ' url="' .  $file_url . '" type="' . $mime . '" />';
			}
			
			$add_itunes_tags = true;
		}

		if ( !$add_itunes_tags )
			return;

		$author = get_the_author();
		
		$summary = get_post_meta($post_ID, '_description', true);
		if ( !$summary )
			$summary = get_the_excerpt();

		$keywords = get_post_meta($post_ID, '_keywords', true);
		if ( !$keywords ) {
			$keywords = array();
			$cats = get_the_category($post_ID);
			$tags = get_the_tags($post_ID);
			
			if ( $cats && !is_wp_error($cats) ) {
				foreach ( $cats as $cat )
					$keywords[] = $cat->name;
			}

			if ( $tags && !is_wp_error($tags) ) {
				foreach ( $tags as $tag )
					$keywords[] = $tag->name;
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
			. '<itunes:author>' . htmlentities($author, ENT_COMPAT, get_option('blog_charset')) . '</itunes:author>' . "\n\t\t"
			. '<itunes:summary>' . htmlentities($summary, ENT_COMPAT, get_option('blog_charset')) . '</itunes:summary>' . "\n\t\t"
			. '<itunes:keywords>' . htmlentities($keywords, ENT_COMPAT, get_option('blog_charset')) . '</itunes:keywords>' . "\n\t\t"
			;
		
		echo "\n";
	} # display_feed_enclosures()
	
	
	/**
	 * get_extensions()
	 *
	 * @return array $extensions
	 **/

	function get_extensions($type = null) {
		$audio = array('mp3', 'm4a', 'aac');
		$video = array('flv', 'f4b', 'f4p', 'f4v', 'mp4', 'm4v', 'mov', '3pg', '3g2');
		
		return isset($type) ? $$type : array_merge($audio, $video);
	} # get_extensions()
	
	
	/**
	 * init_options()
	 *
	 * @return void
	 **/

	function init_options() {
		$options = array();

		$options['player'] = array(
			'position' => 'top',
			'skin' => 'bekle',
			'cover' => false,
			);
		
		$options['itunes'] = array(
			'author' => '',
			'summary' => get_option('blogdescription'),
			'explicit' => 'no',
			'block' => 'no',
			'copyright' => '',
			);
		
		$options['longtail'] = array(
			'agreed' => false,
			'licensed' => false,
			'script' => '',
			'channel' => '',
			);
		
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
		global $wp_db_version;
		
		if ( get_option('db_version') != $wp_db_version )
			return;
		
		$ops = get_option('mediacaster');
		
		if ( isset($ops['version']) )
			return;
		
		$ops['version'] = '2.0';
		
		if ( !isset($ops['player']['skin']) )
			$ops['player']['skin'] = 'bekle';
		
		if ( !isset($ops['player']['position']) )
			$ops['player']['position'] = 'top';
		
		if ( !isset($ops['player']['cover']) )
			$ops['player']['cover'] = false;
		
		if ( !isset($ops['longtail']) )
			$ops['longtail'] = array(
				'agreed' => false,
				'licensed' => false,
				'script' => '',
				'channel' => array(),
				);
		
		# prevent concurrent upgrades
		update_option('mediacaster', $ops);
		
		$ignore_user_abort = ignore_user_abort(true);
		@set_time_limit(600);
		
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
				$repl = '[mc src="' . $folder_url . str_replace(' ', rawurlencode(' '), $attachment) . '" type="file"/]';
				
				$post->post_content = preg_replace(
					"/\[media:\s*($find)\s*\]/",
					$repl,
					$post->post_content);
			}
			
			# process inline audios
			foreach ( $audios as $j => $audio ) {
				$find = preg_quote($audio);
				$repl = '[mc src="' . $folder_url . str_replace(' ', rawurlencode(' '), $audio) . '" type="audio"/]';
				
				if ( preg_match("/\[(?:audio|video|media):\s*($find)\s*\]/", $post->post_content) )
					$audios[$audio] = $repl;
				
				$post->post_content = preg_replace(
					"/\[(?:audio|video|media):\s*($find)\s*\]/",
					$repl,
					$post->post_content);
				
				unset($audios[$j]);
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
				$repl = ( '[mc src="' . $folder_url . str_replace(' ', rawurlencode(' '), $video) . '"' )
					. ( $image
					? ( ' image="' . $folder_url . str_replace(' ', rawurlencode(' '), $image) . '"' )
					: ''
					)
					. $format . ' type="video"/]';
				
				if ( !preg_match("/\[(?:audio|video|media):\s*$find\s*\]/", $post->post_content) ) {
					$videos[$video] = $repl;
				} else {
					$post->post_content = preg_replace(
						"/\[(?:audio|video|media):\s*$find\s*\]/",
						$repl,
						$post->post_content);
					unset($videos[$video]);
				}
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
				$post->post_content);
			
			$wpdb->query("
				UPDATE	$wpdb->posts
				SET		post_content = '" . $wpdb->_real_escape($post->post_content) . "'
				WHERE	ID = " . intval($post->ID)
				);
			wp_cache_delete($post->ID, 'posts');
			
			delete_post_meta($post->ID, '_mediacaster_path');
		}
		
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
			return "[mc src=\"$file\" type=\"youtube\"/]";
		} elseif ( preg_match("/^https?:\/\/video\.google\.com\//", $file) ) {
			$file = parse_url($file);
			$file = $file['query'];
			parse_str($file, $file);
			$file = $file['docid'];
			$file = preg_replace("/[^a-z0-9_-]/i", '', $file);
			
			if ( !$file )
				return '';
			
			$ops = get_option('mediacaster');
			
			$width = 420;
			$height = 315;
			$height += 26; // controlbar
			
			$player = 'http://video.google.com/googleplayer.swf?docId=' . $file;
			$id = md5($i++ . $player);
			
			return <<<EOS

<div class="media_container"><div class="media" style="width: {$width}px; height: {$height}px;"><object id="$id" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="$width" height="$height"><param name="movie" value="$player" /><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="true" /><embed src="$player" pluginspage="http://www.macromedia.com/go/getflashplayer" width="$width" height="$height" allowfullscreen="true" allowscriptaccess="true" /></object></div></div>

EOS;
		} else {
			return '[mc src="' . str_replace(' ', rawurlencode(' '), $file) . '"/]';
		}
	} # upgrade_callback()
	
	
	/**
	 * flush_cache()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/

	function flush_cache($in = null) {
		static $done = false;
		if ( $done )
			return $in;
		
		$done = true;
		delete_post_meta_by_key('_mc_enclosures');
		delete_post_meta_by_key('_mc_enclosed');
		
		return $in;
	} # flush_cache()
} # mediacaster

function mediacaster_admin() {
	include_once dirname(__FILE__) . '/mediacaster-admin.php';

	if ( current_filter() == 'load-media-new.php' )
		add_action('pre-upload-ui', array('mediacaster_admin', 'post_upload_ui'));	
}

foreach ( array('settings_page_mediacaster',
	'post.php', 'post-new.php', 'page.php', 'page-new.php',
	'media-upload.php', 'upload.php', 'async-upload.php', 'media.php', 'media-new.php') as $hook )
	add_action("load-$hook", 'mediacaster_admin');

add_filter('the_content', array('mediacaster', 'podcasts'), 12);
add_filter('the_excerpt', array('mediacaster', 'podcasts'), 12);

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

if ( function_exists('shortcode_unautop') )
	add_filter('widget_text', 'shortcode_unautop');
add_filter('widget_text', 'do_shortcode', 11);
add_shortcode('mc', array('mediacaster', 'shortcode'));

add_filter('ext2type', array('mediacaster', 'ext2type'));
add_filter('upload_mimes', array('mediacaster', 'upload_mimes'));
add_filter('wp_get_attachment_url', array('mediacaster', 'wp_get_attachment_url'), 10, 2);
add_filter('get_attached_file', array('mediacaster', 'get_attached_file'), 10, 2);

add_filter('get_the_excerpt', array('mediacaster', 'disable'), -20);
add_filter('get_the_excerpt', array('mediacaster', 'enable'), 20);

add_action('flush_cache', array('mediacaster', 'flush_cache'));
add_action('after_db_upgrade', array('mediacaster', 'flush_cache'));

if ( !is_admin() ) {
	add_action('wp_print_scripts', array('mediacaster', 'scripts'), 0);
	add_action('wp_print_styles', array('mediacaster', 'styles'), 0);
	add_action('wp_footer', array('mediacaster', 'ltas_scripts'));
	add_action('wp_footer', array('mediacaster', 'thickbox_images'), 20);
	
	add_action('template_redirect', array('mediacaster', 'template_redirect'), 0);
} else {
	add_action('admin_menu', array('mediacaster', 'admin_menu'));
}
?>