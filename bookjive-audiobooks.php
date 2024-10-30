<?php
/*
Plugin Name: Bookjive Audiobook
Plugin URI: http://www.bookjive.com/wiki/Audiobook_Plugin 
Description: High quality public domain audiobook player & audiobook downloads. I've created this plugin to help distribute and share some of the fantastic public domain audio books out there. I've done some audio editing to reduce background noise, broken up all the audio files into single chapters to make navigation easier, and removed the annoying announcements at the beginning of each chapter. There are some great audio books here that are very high quality. Enjoy! This has tested on WordPress 2.8.6 and 2.9. Not testing has been preformed on wordpress MU.
Version: 1.0.1
Author: BookJive
Author URI: http://www.bookjive.com
*/

define('BOOKJIVE_AUDIOBOOKS_VERSION', '1.0.0');

function bookjive_audiobooks_install() {

	global $wpdb;

	//AudioBooks
	$sql = "CREATE TABLE `{$wpdb->prefix}bookjive_audiobooks` (
			`edition_id` INT NOT NULL ,
 			`date_created` DATETIME NOT NULL ,
			`book_id` INT NOT NULL ,
			`book_title` VARCHAR( 255 ) NOT NULL ,
			`widget_url` VARCHAR( 255 ) NOT NULL ,
			`book_url` VARCHAR( 255 ) NOT NULL ,
			`author` VARCHAR( 255 ) NOT NULL ,
			`author_url` VARCHAR( 255 ) NOT NULL ,
			`download_url` VARCHAR( 255 ) NOT NULL ,
			`download_size` VARCHAR( 255 ) NOT NULL ,
			`reader` VARCHAR( 255 ) NOT NULL ,
			`reader_url` VARCHAR( 255 ) NOT NULL ,
			PRIMARY KEY ( `edition_id` )
			) ENGINE = MYISAM";
	$wpdb->query($sql);

	//AudioBooks Tracks
	$sql = "CREATE TABLE `{$wpdb->prefix}bookjive_audiobook_tracks` (
			`track_id` INT NOT NULL ,
			`edition_id` INT NOT NULL ,
			`chapter_num` INT NOT NULL ,
			`chapter_title` VARCHAR( 255 ) NOT NULL ,
			`audio_file` VARCHAR( 255 ) NOT NULL ,
			`sequence` FLOAT( 10, 2 ) NOT NULL ,
			PRIMARY KEY ( `track_id` )
			) ENGINE = MYISAM";
	$wpdb->query($sql);

}
register_activation_hook(__FILE__, 'bookjive_audiobooks_install');

function bookjive_audiobooks_deactivate(){

	global $wpdb;

	//AudioBooks
	$sql = "DROP TABLE `{$wpdb->prefix}bookjive_audiobooks`";
	$wpdb->query($sql);

	//AudioBooKs Tracks
	$sql = "DROP TABLE `{$wpdb->prefix}bookjive_audiobook_tracks`";
	$wpdb->query($sql);

}
register_deactivation_hook(__FILE__, 'bookjive_audiobooks_deactivate');

function bookjive_audiobook_content_parser($content){

    global $wpdb;

	$pattern = "@\[bookjive_audiobooks(\|([a-z0-9_]+))?\]@is";
    preg_match_all($pattern, $content, $matches);  
	$audiobook_url = $matches[2][0];
	if(!$matches[0]){
		return $content;
	}

	if($_GET['audiobook']){
		$audiobook_url = preg_replace("@[^a-z0-9_]@", "", $_GET['audiobook']);
	}

	$audiobooks_html = bookjive_audiobooks($audiobook_url);
	$content = preg_replace($pattern, $audiobooks_html, $content);
    return $content;

}
add_filter('the_content', 'bookjive_audiobook_content_parser');


function bookjive_audiobooks($audiobook_url){

    global $wpdb;

	//Directory
	preg_match("@wp-content/plugins/([^/]+)/@i", __FILE__, $matches);
	$directory = $matches[1];

	//Update Audiobook Feed
	$sql = "SELECT 
				UNIX_TIMESTAMP(ba.date_created) as created
			FROM {$wpdb->prefix}bookjive_audiobooks as ba
			WHERE 1
			ORDER BY ba.date_created ASC
			LIMIT 1";

	$audiobook_timestamp = (int) $wpdb->get_var($sql);
	if($audiobook_timestamp < time()-86400*7 || $_GET['clear_audiobook_cache'] == 1){
		update_bookjive_audiobooks();
	}

	//Load AudioBook URL
	$sql = "SELECT 
				ba.edition_id
			FROM {$wpdb->prefix}bookjive_audiobooks as ba
			WHERE 1
				AND ba.widget_url = '$audiobook_url'
			ORDER BY ba.book_title
			LIMIT 1";
	$audiobook_id = (int) $wpdb->get_var($sql);

	//CSS
	echo bookjive_audiobook_css();

	if($audiobook_id){
		//Write Player
		$sql = "SELECT 
					ba.*
				FROM {$wpdb->prefix}bookjive_audiobooks as ba
				WHERE 1
					AND ba.edition_id = $audiobook_id
				ORDER BY ba.book_title
				LIMIT 1";
		$audiobook = $wpdb->get_row($sql, ARRAY_A);

		if($_GET['audiobook']){
			$back_link = "<div id=\"audiobook-back\"><a href=\"".get_permalink()."\">&laquo; audio book list</a></div>";
		}

		$swf_player = get_bloginfo('url')."/wp-content/plugins/{$directory}/swfobject.js";
		$swf_multi_player = get_bloginfo('url')."/wp-content/plugins/{$directory}/playerMultipleList.swf";
        $swf_playlist = get_bloginfo('url')."/wp-content/plugins/{$directory}/bookjive-audiobook.xml.php?edition_id={$audiobook['edition_id']}";

		//Read By
		unset($reader);
		if($audiobook['reader']){
			if($audiobook['reader_url']){
				$reader = "<div id=\"audiobook-reader\">read by <a href=\"{$audiobook['reader_url']}\">{$audiobook['reader']}</a></div>";
			}else{
				$reader = "<div id=\"audiobook-reader\">read by {$audiobook['reader']}</div>";
			}
		}

		//Download
		unset($download);
		if($audiobook['download_url']){
			$download_size = ($audiobook['download_size'])?"({$audiobook['download_size']})":"";
			$download = "<div id=\"audiobook-download\"><a href=\"{$audiobook['download_url']}\">download audiobook {$download_size}</a></div>";
		}

		$html = <<<EOF
<center>
<div id="bookjive">
	{$back_link}
	<div id="audiobook-title"><a href="{$audiobook['book_url']}">{$audiobook['book_title']}</a></div>
	<div id="audiobook-subtitle">by <a href="{$audiobook['author_url']}">{$audiobook['author']}</a></div>
	{$reader}
	<div id="audiobook-content">
		<script type="text/javascript" src="{$swf_player}"></script>
		<div id="flashPlayer">This text will be replaced by the flash player.</div>
		<script type="text/javascript">
			var so = new SWFObject("{$swf_multi_player}", "mymovie", "375", "350", "7", "#5F5F5F");
			so.addVariable("autoPlay","no");
			so.addVariable("playlistPath","{$swf_playlist}");
			so.write("flashPlayer");
		</script> 
	</div>
	{$download}
	<div id="audiobook-footer">powered by <a href="http://www.bookjive.com">bookive.com</a></div>
</div>
</center>
EOF;
		return $html;

	}else{

		$sql = "SELECT 
					count(ba.edition_id)
				FROM {$wpdb->prefix}bookjive_audiobooks as ba
				WHERE 1";
		$total = (int) $wpdb->get_var($sql);

		//Write List
		$items_per_page = 5;
		$current_page = (int) ($_GET['audiobook_page'])?$_GET['audiobook_page']:1;
		$total_pages = ceil($total/$items_per_page);
		$permalink = get_permalink();
		$delim_char = (strpos($permalink, "?") === false)?"?":"&";

		if($current_page <= 1){
			$prev = "&laquo; PREV";
		}else{
			$prev = "<a href=\"".$permalink.$delim_char."audiobook_page=".($current_page-1)."\">&laquo; PREV</a>";
		}

		if($current_page >= $total_pages){
			$next = "NEXT &raquo;";
		}else{
			$next = "<a href=\"".$permalink.$delim_char."audiobook_page=".($current_page+1)."\">NEXT &raquo;</a>";
		}

		$pagination.= "{$prev} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Page {$current_page} of {$total_pages} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {$next}";

		$sql = "SELECT 
					ba.*
				FROM {$wpdb->prefix}bookjive_audiobooks as ba
				WHERE 1
				ORDER BY ba.book_title
				LIMIT ".($current_page-1)*$items_per_page.", {$items_per_page}";
		$audiobooks = $wpdb->get_results($sql, ARRAY_A);

		$play_btn = get_bloginfo('url')."/wp-content/plugins/{$directory}/play.gif";

		unset($list);
		if(count($audiobooks)){
			foreach($audiobooks as $a){
				$permalink = get_permalink();
				$delim_char = (strpos($permalink, "?") === false)?"?":"&";
				$audiobook_url = $permalink.$delim_char."audiobook={$a['widget_url']}";
				$list.= "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"375px\" style=\"border:1px solid #CCCCCC;margin:1px 0px 1px 0px\">";
				$list.= "<tr>";
				$list.= "<td style=\"padding:5px;width:40px\"><a href=\"{$audiobook_url}\" class=\"audiobook-list-item\"><img src=\"".$play_btn."\" style=\"width:30px\" /></a></td>";
				$list.= "<td><a href=\"{$audiobook_url}\" class=\"audiobook-list-item\"><span class=\"audiobook-list-book\">{$a['book_title']}</span> <span class=\"audiobook-list-author\">(by {$a['author']})</span></a></td>";
				$list.= "</tr>";
				$list.= "</table>";
			}
		}else{
			$list.= "No data. This plugin may need to be upgraded. Please try back later or contact <a href=\"http://bookjive.com\">www.bookjive.com</a> for assistance with this plugin.";	
		}

		$html = <<<EOF
<center>
<div id="bookjive">
	<div id="audiobook-list-title"><b>AudioBooks</b></div>
	<div id="audiobook-content">
		<div id="audiobook-pagination">{$pagination}</div>
		{$list}
		<div id="audiobook-pagination">{$pagination}</div>
	</div>
	<div id="audiobook-footer">powered by <a href="http://www.bookjive.com">bookive.com</a></div>
</div>
</center>
EOF;
		return $html;
	}

}
	
function update_bookjive_audiobooks(){

    global $wpdb;

	$json_feed = @file_get_contents("http://www.bookjive.com/wiki/feed_audio_books.php");
	if(!$json_feed){
		return;
	}

	$data = json_decode($json_feed);
	if(!is_array($data)){
		return;
	}

	foreach($data as $d){

		$edition_id = (int) $d->edition_id;
		$book_id = (int) $d->book_id;
		$book_url = $wpdb->escape($d->book_url);
		$widget_url = preg_replace("@[^a-z0-9_]@", "", $d->widget_url);
		$book_title = $wpdb->escape($d->book_title);
		$author = $wpdb->escape($d->author);
		$author_url = $wpdb->escape($d->author_url);
		$download_url = $wpdb->escape($d->download_url);
		$download_size = $wpdb->escape($d->download_size);
		$reader = $wpdb->escape($d->reader);
		$reader_url = $wpdb->escape($d->reader_url);

		//AudioBooks
		$sql = "INSERT INTO {$wpdb->prefix}bookjive_audiobooks
				(
					`edition_id`,
					`date_created`,
					`book_id`,
					`book_url`,
					`widget_url`,
					`book_title`,
					`author`,
					`author_url`,
					`download_url`,
					`download_size`,
					`reader`,
					`reader_url`
				)
				VALUES
				(
					'$edition_id',
					NOW(),
					'$book_id',
					'$book_url',
					'$widget_url',
					'$book_title',
					'$author',
					'$author_url',
					'$download_url',
					'$download_size',
					'$reader',
					'$reader_url'
				)
				ON DUPLICATE KEY UPDATE
					date_created=NOW(),
					book_id='$book_id',
					book_url='$book_url',
					widget_url='$widget_url',
					book_title='$book_title',
					author='$author',
					author_url='$author_url',
					download_url='$download_url',
					download_size='$download_size',
					reader='$reader',
					reader_url='$reader_url'";
		$wpdb->query($sql);

		//Tracks
		foreach($d->tracks as $t){

			$track_id = (int) $t->track_id;
			$edtion_id = (int) $edition_id;
			$chapter_num = $wpdb->escape($t->chapter_num);
			$chapter_title = $wpdb->escape($t->chapter_title);
			$audio_file = $wpdb->escape($t->audio_file);
			$sequence = (float) $t->sequence;

			$sql = "INSERT INTO {$wpdb->prefix}bookjive_audiobook_tracks
					(
						`track_id`,
						`edition_id`,
						`chapter_num`,
						`chapter_title`,
						`audio_file`,
						`sequence`
					)
					VALUES
					(
						'$track_id',
						'$edition_id',
						'$chapter_num',
						'$chapter_title',
						'$audio_file',
						'$sequence'
					)
					ON DUPLICATE KEY UPDATE
						edition_id='$edition_id',
						chapter_num='$chapter_num',
						chapter_title='$chapter_title',
						audio_file='$audio_file',
						sequence='$sequence'";
			$wpdb->query($sql);

		}

	}

}

function bookjive_audiobook_css(){

    $css = "<style type='text/css'>

				#bookjive {
					margin:15px 0px;
					width:375px;
				}

				#bookjive #audiobook-title {
					font-weight:bold;
				}

				#bookjive #audiobook-subtitle {
					font-size:80%;
				}

				#bookjive #audiobook-reader {
					font-size:80%;
				}

				#bookjive #audiobook-download {
					font-size:80%;
				}

				#bookjive #audiobook-back {
					margin:0px 0px 5px 0px;
					font-size:80%;
					text-align:left;
				}

				#bookjive #audiobook-content {
					margin:10px 0px 0px 0px;
				}

				#bookjive #audiobook-pagination {
					margin:5px 0px 5px 0px;
				}

				#bookjive a.audiobook-list-item table {
					margin:1px 0px 0px 0px;
					border:1px solid #CCCCCC;
				}

				#bookjive a.audiobook-list-item:hover table {
					border:1px solid #5F5F5F;
				}

				#bookjive .audiobook-list-book {
					font-size:104%;
					font-variant:small-caps;
				}

				#bookjive .audiobook-list-author {
					font-size:80%;
				}

				#bookjive #audiobook-footer {
					margin:10px 0px 0px 0px;
					font-size:80%;
				}

    		</style>";
	return $css;
}
