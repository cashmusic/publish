<?php
/*******************************************************************************
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
 *
 *
 * Main settings are stored in the settings.json file. Format:
 *
 *    {
 *			"featured_work":[
 *				"work_id",
 * 			"anotherworkid"
 *			],
 *			"secondary_work":[
 * 			"yetanotherworkid"
 *			],
 *			"tertiary_work":[
 * 			"knockknockwhosthere-workid"
 *			],
 *			"featured_authors":[
 *				"kurtvonnegut"
 *			],
 *			"featured_tags":[
 *				"open",
 * 			"idea"
 *			],
 *			"template":"index"
 *		}
 *
 ******************************************************************************/



/*******************************************************************************
 *
 * SET UP PAGE EXECUTION
 *
 ******************************************************************************/

// get our education class
require_once(__DIR__.'/classes/Harvard.php');
$brown = new Harvard;

// get main settings
$main_settings = json_decode(file_get_contents(__DIR__.'/settings.json'),true);

// parse the route
if (isset($_GET['p'])) {
	$parsed_route = $brown->parseRoute($_GET['p']);
} else {
	$parsed_route = false;
}

$display_options = array();
$display_options['root_url'] = 'https://watt.cashmusic.org';
// set json true/false based on parsed route
$display_options['json'] = false;
if ($parsed_route['json']) {
	$display_options['json'] = true;
}

$display_options['current_url'] = $display_options['root_url'] . '/' . $_GET['p'];
$display_options['share_image'] = 'http://static.cashmusic.netdna-cdn.com/www/img/constant/texture/watt-home.gif';

// grab the full index from the Harvard class
$full_index = $brown->getIndex();

if (file_exists(__DIR__.'/templates/header.mustache')) {
	$display_options['header'] = file_get_contents(__DIR__.'/templates/header.mustache');
}

if (file_exists(__DIR__.'/templates/footer.mustache')) {
	$display_options['footer'] = file_get_contents(__DIR__.'/templates/footer.mustache');
}

// Feature tags site wide
	$display_options['featured_tags'] = $main_settings['featured_tags'];

// Recent/Popular site wide

	foreach ($main_settings['recent_work'] as $work_id) {
		$display_options['recent_work'][] = $full_index['work'][$work_id];
	}

	foreach ($main_settings['popular_work'] as $work_id) {
		$display_options['popular_work'][] = $full_index['work'][$work_id];
	}
	
	foreach ($main_settings['recent_video'] as $work_id) {
		$display_options['recent_video'][] = $full_index['work'][$work_id];
	}

	foreach ($main_settings['featured_video'] as $work_id) {
		$display_options['featured_video'][] = $full_index['work'][$work_id];
	}

	foreach ($main_settings['further_reading'] as $work_id) {
		$display_options['further_reading'][] = $full_index['work'][$work_id];
	}



/*******************************************************************************
 *
 * GET DATA AND RENDER PAGE
 *
 ******************************************************************************/

// first set up variables
$template = '404';

// figure out what template we're using
if ($parsed_route) {
	if ($parsed_route['type'] == 'view') {
		/*************************************************************************
		 *
		 * VIEW AN ARTICLE (/view)
		 *
		 ************************************************************************/
		require_once(__DIR__.'/lib/markdown/markdown.php');
		$display_options['id'] = $parsed_route['options'][0]; // get article id
		if (file_exists(__DIR__.'/content/work/'.$display_options['id'].'.md')) {
			$display_options['content'] = Markdown(file_get_contents(__DIR__.'/content/work/'.$display_options['id'].'.md'));
			$work_details = json_decode(file_get_contents(__DIR__.'/content/work/'.$display_options['id'].'.json'),true);

			if ($work_details) {
				$display_options = array_merge($work_details,$display_options);
				// build tags array
				$tmp_array = array();
				foreach ($work_details['tags'] as $tag) {
					$tmp_array[]['tag'] = $tag;
				}
				$display_options['tags'] = $tmp_array;
				$display_options['display_time'] = $brown->formatTimeAgo($work_details['date']);
				$display_options['display_byline'] = $brown->formatByline($work_details['author_id']);
				$display_options['display_share'] = $brown->formatShare();
				$display_options['author_name'] = $full_index['work'][$display_options['id']]['author_name'];
				if (isset($work_details['template'])) {
					$template = $work_details['template'];
				} else {
					$template = 'default';
				}

				if (count($display_options['assets'])) {
					$display_options['share_image'] = $display_options['assets'][0]['url'];
				}
			}
			if ($display_options['json']) {
				$output = array(
					"metadata" => $full_index['work'][$display_options['id']],
					"content" => $display_options['content']
				);
				echo json_encode($output);
				exit();
			}
		}
	} else if ($parsed_route['type'] == 'licenses' || $parsed_route['type'] == 'licenses.json') {
		/*************************************************************************
		 *
		 * LICENSE ENDPOINT (/licenses.json)
		 *
		 ************************************************************************/

		$brown->setJSONHeaders();
		echo file_get_contents(__DIR__.'/content/licenses.json');
		exit();
	} else if ($parsed_route['type'] == 'rss') {
		/*************************************************************************
		 *
		 * RSS FEED (/rss)
		 *
		 ************************************************************************/

		$display_options['filtered_work'] = $full_index['filtered_work'];
		$template = 'rss';
	} else if ($parsed_route['type'] == 'podcast') {
		/*************************************************************************
		 *
		 * PODCAST FEED (/podcast)
		 *
		 ************************************************************************/
		$template = 'rss-media';
	} else if ($parsed_route['type'] == 'index' || $parsed_route['type'] == 'all') {
		/*************************************************************************
		 *
		 * FULL ARTICLE/AUTHOR/TAG INDEX (/index)
		 *
		 ************************************************************************/
		 $loopable_authors = array();
		 foreach ($full_index['authors']['details'] as $author) {
			$loopable_authors[] = $author;
		 }

		 $display_options['work'] = $full_index['filtered_work'];
		 $display_options['authors'] = $loopable_authors;
		 $display_options['tags'] = $full_index['tags']['list'];

		$template = 'all';
		 if ($display_options['json']) {
			 // JSON requested, so spit it out and exit (no template)
			 $output = array(
				"authors" => $display_options['authors'],
				"tags" => 	 $display_options['tags'],
				"work" => 	 $display_options['work']
			);
			 echo json_encode($output);
			 exit();
		 }
	} else if ($parsed_route['type'] == 'tag') {
		/*************************************************************************
		 *
		 * VIEW A SPECIFIC TAG (/tag)
		 *
		 ************************************************************************/
		$template = 'tag';
		if (count($parsed_route['options'])) {
			// found a tag. now what?
			$display_options['tag'] = $parsed_route['options'][0];
			if (isset($full_index['tags']['index'][$display_options['tag']])) {
				// set details and features
				if (isset($full_index['tags']['details'][$display_options['tag']])) {
					$display_options = array_merge($display_options,$full_index['tags']['details'][$display_options['tag']]);
				}
				//$features = array_merge($display_options['featured_work'],array());
				// set the content
				$work = array();
				foreach ($full_index['tags']['index'][$display_options['tag']] as $work_id) {
					if ((!in_array($work_id,$features) && !$display_options['json']) || $display_options['json']) {
						$work[] = $full_index['work'][$work_id];
					}
				}
				$display_options['work'] = $work;
				$display_options['tag_list'] = $full_index['tags']['list'];

				if (!$display_options['json']) {
					$display_options['featured_work'] = array();
					foreach ($features as $work_id) {
						$display_options['featured_work'][] = $full_index['work'][$work_id];
					}
				}
			}
			if ($display_options['json']) {
				// JSON requested, so spit it out and exit (no template)
				echo json_encode($display_options['work']);
				exit();
			}

			if (!$display_options['title']) {
				$display_options['title'] =  $display_options['tag'];
				$display_options['description'] = 'All articles matching #' . $display_options['tag'];
			}
		} else {
			// No actual tag specified. Redirect.
			header('Location: /');
			exit;
		}
	} else if ($parsed_route['type'] == 'redirect') {
		/*************************************************************************
		 *
		 * REDIRECT TO EXTERNAL CONTENT (/redirect)
		 *
		 ************************************************************************/
		if (isset($full_index['work'][$parsed_route['options'][0]]['url'])) {
			header('Location: ' . $full_index['work'][$parsed_route['options'][0]]['url']);
		}

	} else if ($parsed_route['type'] == 'video') {
		/*************************************************************************
		 *
		 * REDIRECT TO EXTERNAL CONTENT (/video)
		 *
		 ************************************************************************/
		if (isset($full_index['work'][$parsed_route['options'][0]]['url'])) {
			header('Location: ' . $full_index['work'][$parsed_route['options'][0]]['url']);
		}

	} else if ($parsed_route['type'] == 'author') {
		/*************************************************************************
		 *
		 * SHOW AUTHOR PAGE (/author)
		 *
		 ************************************************************************/
		 $template = 'author';
 		if (count($parsed_route['options'])) {
 			// found a tag. now what?
 			$display_options['author_id'] = $parsed_route['options'][0];
 			if (isset($full_index['authors']['index'][$display_options['author_id']])) {
 				// set the content
 				$work = array();
 				foreach ($full_index['authors']['index'][$display_options['author_id']] as $work_id) {
 					$work[] = $full_index['work'][$work_id];
					$display_options['author_name'] = $full_index['work'][$work_id]['author_name'];
					$display_options['author_byline'] = $full_index['work'][$work_id]['author_byline'];
 				}
 				$display_options['work'] = $work;
				$display_options['tag_list'] = $full_index['tags']['list'];
 			}
 			if ($display_options['json']) {
 				// JSON requested, so spit it out and exit (no template)
 				echo json_encode($display_options['work']);
 				exit();
 			}
			if (!$display_options['title']) {
				$display_options['title'] = $display_options['author_name'];
				$display_options['description'] = 'All Watt articles by ' . $display_options['author_name'];
			}
 		} else {
 			// No actual tag specified. Redirect.
 			header('Location: /');
 			exit;
 		}
	}
} else {
	/****************************************************************************
	 *
	 * MAIN PAGE (/)
	 *
	 ***************************************************************************/

	$display_options['featured_work'] = array();
	$display_options['featured_video'] = array();
	$display_options['secondary_work'] = array();
	$display_options['tertiary_work'] = array();
	$display_options['quaternary_work'] = array();
	$display_options['featured_authors'] = array();

	foreach ($main_settings['featured_work'] as $work_id) {
		require_once(__DIR__.'/lib/markdown/markdown.php');
		 if (file_exists(__DIR__.'/content/work/'.$work_id.'.md')) {
			$full_index['work'][$work_id]['content'] = Markdown(file_get_contents(__DIR__.'/content/work/'.$work_id.'.md'));
		}
		$display_options['display_share'] = $brown->formatShare();
		$display_options['featured_work'][] = $full_index['work'][$work_id];
	}
	foreach ($main_settings['featured_video'] as $work_id) {
		$display_options['featured_video'][] = $full_index['work'][$work_id];
	}
	foreach ($main_settings['secondary_work'] as $work_id) {
		$display_options['secondary_work'][] = $full_index['work'][$work_id];
	}
	foreach ($main_settings['tertiary_work'] as $work_id) {
		$display_options['tertiary_work'][] = $full_index['work'][$work_id];
	}
	foreach ($main_settings['quaternary_work'] as $work_id) {
		$display_options['quaternary_work'][] = $full_index['work'][$work_id];
	}
	foreach ($main_settings['featured_authors'] as $author_id) {
		$display_options['featured_authors'][] = $full_index['authors']['index'][$author_id];
	}

	if (!$display_options['title']) {
		$display_options['title'] = 'Home';
		$display_options['description'] = 'Watt is a publication dedicated to asking and answering the questions that matter. We explore the economic, technology, culture, and health issues facing musicians.';
	}

	$display_options['work'] = $full_index['filtered_work'];
	$display_options['tag_list'] = $full_index['tags']['list'];
	$template = $main_settings['template'];

}

// pick the correct template and echo
echo $brown->renderMustache($template, $display_options);
?>
