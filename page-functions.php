<?php
require_once 'page-config.php';

function time_remain($future_time)
{
        $current_time  = time();  //获取今天时间戳  
        $timeHtml = ''; //返回文字格式  
        $temp_time = 0;
        switch($current_time){
                case ($current_time+60) >= $future_time:
                        $temp_time = $future_time-$current_time;
                        $timeHtml = $temp_time ."秒后";
                        break;
                case ($current_time+3600) >= $future_time:
                        $temp_time = date('i',$future_time-$current_time);
                        $timeHtml = $temp_time ."分钟后";
                        break;
                case ($current_time+3600*24) >= $future_time:
                        $temp_time = date('H',$future_time)-date('H',$current_time);
                        if ($temp_time < 0) {
                                $temp_time = 24 + $temp_time;
                        }
                        $timeHtml = $temp_time .'小时后';
                        break;
                case ($current_time+3600*24*2) >= $future_time:
                        $temp_time = date('H:i',$current_time);
                        $timeHtml = '明天'.$temp_time ;
                        break;
                case ($current_time+3600*24*3) >= $future_time:
                        $temp_time  = date('H:i',$current_time);
                        $timeHtml = '后天'.$temp_time ;
                        break;
                case ($current_time+3600*24*4) >= $future_time:
                        $timeHtml = '3天后';
                        break;
                default:
                        $timeHtml = date('Y-m-d',$current_time);
                        break;
        }
        return $timeHtml;
}

function is_won_lottery($probability=100)
{
	if ($probability===1) {return true;}
	if ($probability===0) {return false;}
	$first = mt_rand(1, $probability);
	$second = mt_rand(1, $probability);
	return ($first === $second);
}

function rmdir_Rf($directory)
{
	$results = [];
	foreach(glob("{$directory}/*") as $file)
	{
		if(is_dir($file)) {
			$sub_res = rmdir_Rf($file);
			if (!empty($sub_res)) {
				$results = array_merge($results, $sub_res);
			}

		} else {
			$results[] = $file;
			unlink($file);
		}
	}
	rmdir($directory);
	return $results;
}

function multiexplode ($delimiters,$string) {
    
    $ready = str_replace($delimiters, $delimiters[0], $string);
    $launch = explode($delimiters[0], $ready);
    return  $launch;
}

function read_keys($key_url)
{
	$data_str = file_get_contents($key_url);
	if ($data_str === null) {
		return [];
	}

	$output = [];
	$keys = multiexplode(["\r", "\n", "\r\n"], $data_str);
	foreach($keys as $key){
		$key = trim($key);
		if (empty($key)) {
			continue;
		}
		$output[] = $key;
	}

	return $output;
}

function cache_root()
{
	$uri = $_SERVER['REQUEST_URI'];
	$pos = strpos($uri, '?');
	if ($pos !== false) {
		$uri = substr($uri, 0, $pos);
	}

	$subs = explode('/', $uri);
	$root = dirname(__FILE__).'/'.CACHE_PATH;
	foreach($subs as $sub_dir){
		if (empty($sub_dir)) {
			continue;
		}
		$root .= '/'.$sub_dir;
		if (!file_exists($root)) {
			mkdir($root);
		}
	}
	return $root;
}

function device_id()
{
	$param = get_param();
	$device_id = @$param['device'];
	if (empty($device_id)) {
		$device_id = @$_COOKIE['device_id'];
	}
	return $device_id;
}

function handle_list_exit($cmd, $callback_func)
{
	$param = get_param();
	if (@$param['cmd'] !== $cmd) {
		return;
	}

	list($isok, $list_items, $data, $err_msg)  = call_user_func($callback_func, $param);

	$output = [];

	if ($isok) {
		$output['status'] = 'ok';
	} else {
		$output['status'] = 'error';
		$output['error'] = $err_msg;
	}

	$output = array_merge($output, $data); 

	$output['time'] = gm_date(time());
	$output['count'] = count($list_items);
	$output['items'] = $list_items;
	jsonp_nocache_exit($output);
}


function handle_bool_exit($cmd, $callback_func)
{
	$param = get_param();
	if (@$param['cmd'] !== $cmd) {
		return;
	}

	list($isok, $data, $err_msg)  = call_user_func($callback_func, $param);

	$output = [];

	if ($isok) {
		$output['status'] = 'ok';
	} else {
		$output['status'] = 'error';
		$output['error'] = $err_msg;
	}

	$output = array_merge($output, $data); 
	$output[$cmd] = $isok;
	$output['time'] = gm_date(time());
	jsonp_nocache_exit($output);
}

function handle_reset_exit($callback_func)
{
	$param = get_param();
	if (@$param['api'] === API_KEY) {
		if ($param['cmd'] === 'reset') {
			$output = call_user_func($callback_func, $param);
			if (empty($output)) {
				$output = [];
			}
			$output['status'] = 'ok';
			$output['time'] = gm_date(time());
			jsonp_nocache_exit($output);
		}
	}
}

function gm_date($time)
{
	return gmdate('D, d M Y H:i:s \G\M\T', $time);
}

$merged_params = null;

function get_param($key = null)
{
	global $merged_params;
	if ($merged_params === null) {
		$merged_params = array_merge($_GET, $_POST); 
	}

	if ($key) {
		return @$merged_params[$key];
	} else {
		return $merged_params;
	}
}

define('CRC32_PREFIX','counter-crc32');

function uri_crc32()
{
	$uri = preg_replace('/\?.*$/', '',  @$_SERVER['REQUEST_URI']);
	$key =  crc32(CRC32_PREFIX.$uri);
	return (string)$key;
}

function object_input()
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

		//取前面2个字作为特殊前缀
		$prefix = substr($line, 0, 2);

		//检查是否注释
		if ($prefix === '//') {
			continue;
		}
		
		$pos = strpos($line, ':');
		$pass_len = strlen(':');
		if ($pos === false) {
			$pos = strpos($line, '：');
			$pass_len = strlen('：');
			if ($pos === false) {
				continue;
			}
		}

		$key = trim(substr($line, 0, $pos));
		$value = trim(substr($line, $pos+$pass_len));

		$output[$key] = $value;
	}

	return $output;
}


function object_save($filename, $data)
{
	file_put_contents($filename, prety_json($data));
}

function object_read($filename)
{
	if (!file_exists($filename)) {
		return [];
	}

	$data_str = file_get_contents($filename);
	if ($data_str === null) {
		return [];
	}
	return json_decode($data_str, true);
}

function prety_json($obj)
{
	return indent_json(json_encode($obj));
}

function indent_json($json)
{
	$result      = '';
	$pos         = 0;
	$strLen      = strlen($json);
	$indentStr   = '  ';
	$newLine     = "\n";
	$prevChar    = '';
	$outOfQuotes = true;

	for ($i=0; $i<=$strLen; $i++) {

		// Grab the next character in the string.
		$char = substr($json, $i, 1);

		// Are we inside a quoted string?
		if ($char == '"' && $prevChar != '\\') {
			$outOfQuotes = !$outOfQuotes;

			// If this character is the end of an element,
			// output a new line and indent the next line.
		} else if(($char == '}' || $char == ']') && $outOfQuotes) {
			$result .= $newLine;
			$pos --;
			for ($j=0; $j<$pos; $j++) {
				$result .= $indentStr;
			}
		}

		// Add the character to the result string.
		$result .= $char;

		// If the last character was the beginning of an element,
		// output a new line and indent the next line.
		if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
			$result .= $newLine;
			if ($char == '{' || $char == '[') {
				$pos ++;
			}

			for ($j = 0; $j < $pos; $j++) {
				$result .= $indentStr;
			}
		}

		$prevChar = $char;
	}

	return $result;
}

/*********************************************************
  jsonp
 *********************************************************/

function html_nocache_exit($output)
{
	set_nocache();
	header('Access-Control-Allow-Origin: *');
	header('Content-Type: text/html; charset=utf-8');
	echo $output;
	exit();
}

function html_cache_exit($output, $age_val=300)
{
	set_cache_age($age_val);
	header('Access-Control-Allow-Origin: *');
	header('Content-Type: text/html; charset=utf-8');
	echo $output;
	exit();
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

/***************  curl ********************/

function curl_get_content($url, $user_agent=null)
{
	$headers = array(
			"Accept: application/json",
			"Accept-Encoding: deflate,sdch",
			"Accept-Charset: utf-8;q=1"
			);

	if ($user_agent === null) {
		$user_agent = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.57 Safari/537.36';
	}
	$headers[] = $user_agent;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_TIMEOUT, 3);

	$res = curl_exec($ch);
	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$err = curl_errno($ch);
	curl_close($ch);

	if (($err) || ($httpcode !== 200)) {
		return null;
	}

	return $res;
}


