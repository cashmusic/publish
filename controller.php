<?php
/***************************************************************************************************
 *
 * CASH Music publishing tool - main controller
 * http://archive.cashmusic.org/
 *
 * @package archive.org.cashmusic
 * @author CASH Music
 * @link http://cashmusic.org/
 *
 * Copyright (c) 2016, CASH Music
 * Licensed under the GNU Lesser General Public License version 3.
 * See http://www.gnu.org/licenses/lgpl-3.0.html
 *
 ***************************************************************************************************/



/***************************************************************************************************
 *
 * INCLUDES AND VARIABLES
 *
 ***************************************************************************************************/

//require_once(__DIR__.'/definitions.php');
require_once(__DIR__.'/lib/mustache/Mustache.php');
$full_index = json_decode(file_get_contents(__DIR__.'/index.json'),true);
$request_type = false;
$request_options = false;

$tag_index = array();
$published_index = array();
$now = time();
foreach ($full_index as $key => $work) {
	if (strtotime($work['date']) < $now) {
		$work['id'] = $key;
		$published_index[] = $work; // trim to current date or earlier
		if (is_array($work['tags'])) {
			if (count($work['tags'])) {
				foreach ($work['tags'] as $tag) {
					$tag_index[$tag][] = $work;
				}
			}
		}
	}
}

if (isset($_GET['p'])) {
	$request = explode('/',trim($_GET['p'],'/'));
	if (is_array($request)) {
		$request_type = array_shift($request);
		$request_options = $request;
		if ($request_type == 'writing') {
			$request_type = 'view';
		}
	}
}

/***************************************************************************************************
 *
 * GET DATA AND RENDER PAGE
 *
 ***************************************************************************************************/

// first set up variables
$display_options = array();

// figure out what template we're using
if ($request_type) {
	if ($request_type == 'view') {
		$template = file_get_contents(__DIR__.'/templates/default.mustache');
	} else if ($request_type == 'rss') {
		$template = file_get_contents(__DIR__.'/templates/rss.mustache');
	} else if ($request_type == 'podcast') {
		$template = file_get_contents(__DIR__.'/templates/rss-media.mustache');
	} else if ($request_type == 'tag') {
		$template = file_get_contents(__DIR__.'/templates/tag.mustache');
		if (count($request_options)) {
			// found a tag. now what?
			$display_options['tag'] = $request_options[0];
			$display_options['json'] = false;
			if (strpos($display_options['tag'],'.json')) {
				// found a trailing.json
				$display_options['tag'] = str_replace('.json','',$display_options['tag']);
				$display_options['json'] = true;
			}
			if (isset($tag_index[$display_options['tag']])) {
				// set the content
				$display_options['work'] = $tag_index[$display_options['tag']];
			}
			if ($display_options['json']) {
				// JSON requested, so spit it out and exit (no template)
				echo json_encode($display_options['work']);
				exit();
			}
		} else {
			// No actual tag specified. Redirect.
			header('Location: /');
			exit;
		}
	} else if ($request_type == 'redirect') {
		// lets us store info and create permalinks for things that are hosted elsewhere
		if (isset($full_index[$request_options[0]]['url'])) {
			header('Location: ' . $full_index[$request_options[0]]['url']);
		} else {
			$template = file_get_contents(__DIR__.'/templates/404.mustache');
		}
	} else {
		$template = file_get_contents(__DIR__.'/templates/404.mustache');
	}
} else {
	$display_options['work'] = $published_index;
	$template = file_get_contents(__DIR__.'/templates/index.mustache');
}

// pick the correct template and echo
$lemmy = new Mustache;
echo $lemmy->render($template, $display_options);
?>
