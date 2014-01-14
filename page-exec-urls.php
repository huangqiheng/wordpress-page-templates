<?php
/* Template Name: exec-urls */

require_once 'page-functions.php';

$urls = get_input_list();

$result = [];
foreach($urls as $index=>$url) {
	$exec_res = [];
	$exec_res['index'] = $index;
	$exec_res['url'] = $url;
	$data = file_get_contents($url);

	if ($data) {
		$exec_res['exec_res'] = json_decode($data, true);
	}
	$result[] = $exec_res;
}

jsonp_nocache_exit($result);

function get_input_list()
{
	$this_page = get_page($page->ID);
	$content = $this_page->post_content;

	$output = [];
	$lines = explode("\r\n", $content);
	foreach ($lines as $line) {
		$line = trim($line);
		if (empty($line)) {
			continue;
		}

		//取前面4个字作为特殊前缀
		$prefix = substr($line, 0, 4);

		//检查是否注释
		if ($prefix !== 'http') {
			continue;
		}

		$output[] = $line;
	}
	return $output;
}
