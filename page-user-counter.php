<?php
/* Template Name: user-counter */

require_once 'page-config.php';

define('CACHE_SECONDS', 5);

$base_number = get_input_base();
$param = get_param();

if (@$param['api'] === API_KEY) {
	if ($param['cmd'] === 'reset') {
		del_counter();
		mmc_clear();
		counter_version(time());
		$output = [];
		$output['status'] = 'ok';
		$output['time'] = gm_date(time());
		jsonp_nocache_exit($output);
	}
	$output = [];
	$output['status'] = 'error';
	$output['error'] = 'command error.';
	jsonp_nocache_exit($output);
}

if (@$param['cmd'] === 'add') {
	if ($counted_time = is_counted()) {
		$output = [];
		$output['status'] = 'error';
		$output['counter'] = reply_counter();
		$output['counted'] = gm_date($counted_time);
		$output['error'] = 'already added.';
		jsonp_nocache_exit($output);
	}
	mark_counter();
	$real_counter = inc_meta_counter();
	mmc_counter($real_counter);
	last_mtime(time());

	$output = [];
	$output['status'] = 'ok';
	$output['counter'] = $real_counter + $base_number;
	$output['time'] = gm_date(time());
	$output['code'] = GUID();
	jsonp_nocache_exit($output);
}


$output = [];
$output['status'] = 'ok';
$output['counter'] = reply_counter();
$output['time'] = gm_date(time());
$output['mtime'] = gm_date(last_mtime());
jsonp_nocache_exit($output);
//jsonp_cache_exit($output, CACHE_SECONDS);

/*************************************************************************/
/*************************************************************************/

//define('COOKIE_TIMEOUT', 3600*24*365*100); //cookie超时时间，设一个超大的
//define('COOKIE_TIMEOUT', 3600); //cookie超时时间，设一个超大的
define('META_COUNTER_VALUE','COUNTER_VALUE');
define('COUNTER_COOKIE_NAME','counter');
define('CRC32_PREFIX','counter-crc32');
define('MEMC_COUNTER_PREFIX', 'MEMC_COUNTER_PREFIX');//没必要改

function gm_date($time)
{
	return gmdate('D, d M Y H:i:s \G\M\T', $time);
}

function reply_counter()
{
	global $base_number;
	$mem_counter = mmc_counter();
	if ($mem_counter === 0) {
		$mem_counter = get_meta_counter();
		if ($mem_counter > 0) {
			mmc_counter($mem_counter);
		}
	}
	$mem_counter +=  $base_number;
	return $mem_counter;
}

function counter_key() {return MEMC_COUNTER_PREFIX.get_keyid(); }
function modify_key() {return MEMC_COUNTER_PREFIX.get_keyid().'_last_modify'; }

function mmc_clear()
{
	$memcache_obj = memcache_pconnect(MEMC_HOST, MEMC_PORT);
	memcache_delete($memcache_obj, counter_key());
	memcache_delete($memcache_obj, modify_key());
}
		
function mmc_counter($now_counter = null)
{
	$memcache_obj = memcache_pconnect(MEMC_HOST, MEMC_PORT);

	if ($now_counter) {
		return memcache_set($memcache_obj, counter_key(), $now_counter, 0, 0);
	} else {
		$res_counter = memcache_get($memcache_obj, counter_key());
		if ($res_counter === false) {
			return 0;
		}
		return $res_counter;
	}
}

function last_mtime($time = null)
{
	$memcache_obj = memcache_pconnect(MEMC_HOST, MEMC_PORT);

	if ($time) {
		$res = memcache_set($memcache_obj, modify_key(), $time, 0, 0);
		return $res;
	} else {
		$mtime = memcache_get($memcache_obj, modify_key());
		if ($mtime === false) {
			return 0;
		}
		return $mtime;
	}
}

function get_keyid()
{
	$uri = preg_replace('/\?.*$/', '',  @$_SERVER['REQUEST_URI']);
	$key =  crc32(CRC32_PREFIX.$uri);
	return (string)$key;
}

function is_counted()
{
	$counter = read_counter();

	$old_version = @$counter['version'];
	$new_version = counter_version();
	if ($old_version !== $new_version) {
		return false;
	}

	$count_time = $counter[get_keyid()];
	return empty($count_time)? false : (int)$count_time;
}

function mark_counter()
{
	$counter = read_counter();
	$counter[get_keyid()] = time();
	$counter['version'] = counter_version();
	save_counter($counter);
}

function read_counter()
{
	$counter_ori = isset($_COOKIE['counter']) ? $_COOKIE['counter'] : null;

	if (empty($counter_ori)) {
		return [];
	}

	$counter = json_decode(base64_decode($counter_ori), true);
	return $counter;
}

function save_counter($counter)
{
	$counter_sav = base64_encode(json_encode($counter));
	setcookie('counter', $counter_sav, time()+3600*24*365*100, '/', $_SERVER['HTTP_HOST']);
}


function get_param($key = null)
{
	$union = array_merge($_GET, $_POST); 
	if ($key) {
		return @$union[$key];
	} else {
		return $union;
	}
}

function counter_version($new_version = null)
{
	$meta_key = META_COUNTER_VALUE.'version';
	if ($new_version) {
		update_post_meta(get_the_id(), $meta_key, $new_version);
	} else {
		$version = get_post_meta(get_the_id(), $meta_key);
		if (empty($version)) {
			$version = time();
			update_post_meta(get_the_id(), $meta_key, $version);
		} else {
			$version = $version[0];
		}
		return $version;
	}
}

function inc_meta_counter()
{
	$counter = get_meta_counter();
	 update_post_meta(get_the_id(), META_COUNTER_VALUE, ++$counter);
	return $counter;
}

function get_meta_counter()
{
	$counter = get_post_meta(get_the_id(), META_COUNTER_VALUE);
	return ($counter)? $counter[0] : 0;
}

function del_counter()
{
	delete_post_meta(get_the_id(), META_COUNTER_VALUE);
}


function jsonp_nocache_exit($output)
{
	set_nocache();
	echo jsonp($output);
	exit();
}

function jsonp_cache_exit($output, $age_val=300)
{
	set_cache_age($age_val);
	echo jsonp($output);
	exit();
}

function set_nocache()
{
	header("Cache-Control: no-cache, must-revalidate"); //HTTP 1.1
	header("Pragma: no-cache"); //HTTP 1.0
	header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
}

function set_cache_age($age_val = 300)
{
	header('Cache-Control: public, must-revalidate, proxy-revalidate, max-age='.$age_val);
	header('Pragma: public');
	header('Last-Modified: '.gm_date(last_mtime()));
	header('Expires: '.gm_date(time()+$age_val));
}

function GUID()
{
	if (function_exists('com_create_guid') === true) {
		return trim(com_create_guid(), '{}');
	}

	return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', 
		mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), 
		mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
}

function get_input_base()
{
	if (function_exists('get_page')) {
		$this_page = get_page(get_the_id());
		$content = $this_page->post_content;

		$items = explode("\r\n", $content);
		return (int)$items[0];
	} else {
		return 0;
	}
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
