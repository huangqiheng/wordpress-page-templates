<?php
/* Template Name: json-list */

$inputs = get_input_list();
$items = expand_inputs($inputs);

$output['status'] = 'ok';
$output['count'] = count($items);
$output['pages'] = 1;
$output['posts'] = $items;

set_cache_age();
echo jsonp($output);

function set_cache_age($age_val = 300)
{
	header('Cache-Control: public, must-revalidate, proxy-revalidate, max-age='.$age_val);
	header('Pragma: public');
	header('Last-Modified: '.gmdate('D, d M Y H:i:s', get_lastmod_time()).' GMT');
	header('Expires: '.gmdate('D, d M Y H:i:s', time()+$age_val).' GMT');
}

function get_lastmod_time()
{
	$last = wp_get_recent_posts(array('numberposts' => 1));
	return strtotime($last[0]['post_date_gmt']);
}

function get_input_list()
{
	$this_page = get_page($page->ID);
	$content = $this_page->post_content;

	$items = explode("\r\n\r\n", $content);
	$output = [];

	foreach ($items as $item) {
		$lines = explode("\r\n", $item);
		if (count($lines) < 3) {
			continue;
		}
		$item_obj['url'] = $lines[0];
		$item_obj['custom-thumbnail'] = $lines[1];
		$item_obj['custom-category'] = $lines[2];
		$output[] = $item_obj;
	}

	return $output;
}

function expand_inputs($inputs)
{
	$host_name = $_SERVER['SERVER_NAME'];

	foreach ($inputs as &$item) {
		$this_url = $item['url'];
		$this_host = parse_url($this_url)['host'];

		$item['ID'] = '';
		$item['post_date'] = '';
		$item['post_date_gmt'] = '';
		$item['post_title'] = '';
		$item['comment_count'] = 0;
		$item['post_excerpt'] = '';
		$item['author_display_name'] = '';
		$item['author_url'] = '';
		$item['categories'] = '';
		$item['post_content'] = '';

		if ($item['custom-category'] === 'ad') {
			continue;
		}

		if ($this_host == $host_name) {
			if ($post = get_post(url_to_postid($this_url))) {
				$item['ID'] = $post->ID;
				$item['post_date'] = $post->post_date;
				$item['post_date_gmt'] = $post->post_date_gmt;
				$item['post_title'] = $post->post_title;
				$item['comment_count'] = $post->comment_count;
				$item['post_excerpt'] = $post->post_excerpt;

				$author= get_userdata($post->post_author);
				$user_data = $author->data;

				$item['author_display_name'] = $user_data->display_name;
				$item['author_url'] = $user_data->user_url;
				

				$cats = get_the_category($post->ID);
				$cats_arr = [];
				foreach ($cats as $cat) {
					$cats_arr[] = $cat->cat_name;
				}
				$cats_str = implode(',', $cats_arr);
				$item['categories'] = $cats_str;

				$item['post_content'] = $post->post_content;
			}
		} else {
			if (strpos($this_host, 'appgame.com') !== FALSE) {
				$json_str = file_get_contents($this_url.'?json=1');
				$json_obj = json_decode($json_str);

				if ($json_obj->status == 'ok') {
					$post = $json_obj->post;

					if ($post) {
						$item['ID'] = $post->id;
						$item['post_date'] = $post->date;
						$item['post_date_gmt'] = gmdate('Y-m-d H:i:s T', strtotime($post->date)+3600*(-8+date("I")));
						$item['post_title'] = $post->title;
						$item['comment_count'] = $post->comment_count;
						$item['post_excerpt'] = $post->excerpt;

						$user_data = $post->author;

						$item['author_display_name'] = $user_data->name;
						$item['author_url'] = $user_data->url;
						
						$cats = $post->categories;
						$cats_arr = [];
						foreach ($cats as $cat) {
							$cats_arr[] = $cat->title;
						}
						$cats_str = implode(',', $cats_arr);
						$item['categories'] = $cats_str;

						$item['post_content'] = $post->content;
					}
				}
			}
		}
	}
	return $inputs;
}

function get_category_items($catid, $image)
{
	$args = array('category'=>$catid, 'posts_per_page'=>6);
	$posts_arr = get_posts($args);
	$result = [];
	$result['name'] = get_category($catid)->name;
	$result['url'] = get_category_link($catid);
	$result['image'] = $image;
	$items = [];
	foreach($posts_arr as $post) {
		$item = [];
		$item['id'] = $post->ID;
		$item['title'] = $post->post_title;
		$item['url'] = get_permalink($post->ID);
		$items[] = $item;
	}
	$result['items'] = $items;
	return $result;
}


function jsonp($data)
{
	header('Access-Control-Allow-Origin: *');  
	header('Content-Type: application/json; charset=utf-8');
	$json = json_encode($data);

	if(!isset($_GET['callback']))
		return $json;

	if(is_valid_jsonp_callback($_GET['callback']))
		return "{$_GET['callback']}($json)";

	return false;
}

function is_valid_jsonp_callback($subject)
{
	$identifier_syntax = '/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}]*+$/u';
	$reserved_words = array('break', 'do', 'instanceof', 'typeof', 'case',
			'else', 'new', 'var', 'catch', 'finally', 'return', 'void', 'continue', 
			'for', 'switch', 'while', 'debugger', 'function', 'this', 'with', 
			'default', 'if', 'throw', 'delete', 'in', 'try', 'class', 'enum', 
			'extends', 'super', 'const', 'export', 'import', 'implements', 'let', 
			'private', 'public', 'yield', 'interface', 'package', 'protected', 
			'static', 'null', 'true', 'false');
	return preg_match($identifier_syntax, $subject)
		&& ! in_array(mb_strtolower($subject, 'UTF-8'), $reserved_words);
}

?>
