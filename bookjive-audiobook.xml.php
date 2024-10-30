<?php

	require('../../../wp-load.php');
	nocache_headers();

	global $wpdb;

	$audiobook_edition_id = (int) $_GET['edition_id'];
	$sql_where = " AND ba.edition_id = {$audiobook_edition_id} ";

	$sql = "SELECT 
				ba.book_id,
				ba.widget_url,
				ba.edition_id,
				ba.book_url,
				ba.book_title,
				ba.author,
				ba.author_url
			FROM {$wpdb->prefix}bookjive_audiobooks as ba
			WHERE 1
				{$sql_where}
			ORDER BY ba.book_title";
	$audiobooks = $wpdb->get_results($sql, ARRAY_A);
	if(count($audiobooks)){

		echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
		echo "<xml>";
		foreach($audiobooks as &$a){

			$edition_id = (int) $a['edition_id'];
			$sql = "SELECT 
						bat.track_id,
						bat.chapter_num,
						bat.chapter_title,
						bat.audio_file,
						bat.sequence
					FROM {$wpdb->prefix}bookjive_audiobook_tracks as bat
					WHERE 1
						AND bat.edition_id = $edition_id
					ORDER BY bat.sequence";
			$a['tracks'] = $wpdb->get_results($sql, ARRAY_A);
			foreach($a['tracks'] as &$t){
				echo "\t<track>";
				echo "\t\t<path>{$t['audio_file']}</path>";
				echo "\t\t<title>{$t['chapter_title']}</title>";
				echo "\t</track>";
			}
		}
		echo "</xml>";

	}

