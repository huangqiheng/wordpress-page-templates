<?php
/* Template Name: lottery */

/*
活动名称：神雕侠侣
抽奖周期：21600
中奖几率:100
成功前至少失败几次:2
最多连续失败几次:5
每用户可中奖次数:1
只允许手机用户抽奖:是
需要提交邮箱:否
需要提交手机号码:否
发奖周期:21600
周期内最大中奖人数:50
总最大中奖人数:500
多久检查奖品内容变化:300
奖品内容文件:http://forms.appgame.com/wp-content/uploads/2013/12/keys.txt
*/

require_once 'page-functions.php';

$config = object_input();

$period = intval($config['抽奖周期']);
$period_disp = intval($config['发奖周期']);
$key_url = $config['奖品内容文件'];
$key_ctime_intval = $config['多久检查奖品内容变化'];
$used_key_file = cache_root().'/used_key.json';
$ready_key_file = cache_root().'/ready_key.json';
$global_file = cache_root().'/global.json';
$global_obj = object_read($global_file);

//初始化全局对象
if (empty($global_obj)) {
	$ready_keys = read_keys($key_url);
	$used_keys = [];

	$global_obj['ori_start'] = time();
	$global_obj['start'] = time();
	$global_obj['counter'] = 0;
	$global_obj['total_counter'] = 0;
	$global_obj['key_url'] = $key_url;
	$global_obj['key_ctime'] = time();
	$global_obj['key_md5'] = md5(json_encode($ready_keys));
} else {
	$ready_keys = object_read($ready_key_file);
	$used_keys = object_read($used_key_file);
}

//根据发奖周期，自动清空计数器
if (time() - $global_obj['start'] > $period_disp) {
	$global_obj['start'] = time();
	$global_obj['counter'] = 0;
}

//每60秒检查一下注册码文件是否改变并更新至之
if (($global_obj['key_url'] !== $key_url) ||
   ((time() - $global_obj['key_ctime']) > $key_ctime_intval)) {
	$global_obj['key_ctime'] = time();
	object_save($global_file, $global_obj);

	$new_keys = read_keys($key_url);
	$new_md5 = md5(json_encode($new_keys));
	if ($new_md5 !== $global_obj['key_md5']) {
		$global_obj['key_url'] = $key_url;
		$global_obj['key_md5'] = $new_md5;
		$new_ready_keys = array_diff($new_keys, $used_keys);
		$ready_keys = array_values($new_ready_keys);
	}
}

$device_id = device_id();
$user_file = cache_root().'/'.$device_id.'.json';
$user_obj = object_read($user_file);

if (empty($user_obj)) {
	$user_obj['last_checkout_time'] = 0;
	$user_obj['next_checkout_time'] = time();
	$user_obj['continue_failure_count'] = 0;
	$user_obj['has_items'] = [];
}

handle_bool_exit('valid', 'on_valid_handler');
handle_list_exit('checkout', 'on_checkout_handler');
handle_list_exit('history', 'on_history_handler');
handle_list_exit('summary', 'on_summary_handler');
handle_reset_exit('on_reset_handler');
jsonp_nocache_exit(['status'=>'error', 'error'=>'unknow command']);

function ex_data($errno=null)
{
	global $config, $user_obj, $global_obj, $ready_keys, $used_keys;
	global $device_id;
	$result = [];
	//$result['device_id'] = $device_id;
	$result['next_time'] = gm_date($user_obj['next_checkout_time']);
	$result['time_remain'] = time_remain($user_obj['next_checkout_time']);
	$result['period'] = intval($config['抽奖周期']);
	$result['lottery_id'] = basename(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
	$result['failure_count'] = $user_obj['continue_failure_count'];
	$result['failure_max'] = ($user_obj['continue_failure_count'] === intval($config['最多连续失败几次']));

	if ($errno) {
		$result['errno'] = $errno;
	}
	return $result;
}

function on_history_handler($param)
{
	global $user_obj;
	return [true, $user_obj['has_items'], [], 'done'];
}

function on_summary_handler($param)
{
	if (@$param['api'] !== API_KEY) {
		return [false, [], ex_data(4001), 'Sorry, api key wrong.'];
	}

	global $config, $user_obj, $global_obj, $ready_keys, $used_keys;
	$extra_data = [];
	$extra_data['活动名称'] = $config['活动名称'];
	$extra_data['抽奖开始'] = gm_date($global_obj['ori_start']);
	$extra_data['抽奖次数'] = $global_obj['total_counter'];
	$extra_data['发出个数'] = count($used_keys);
	$extra_data['剩下个数'] = count($ready_keys);

	$data = [];
	$data['发出奖品清单'] = $used_keys;
	$data['剩下奖品清单'] = $ready_keys;
	return [true, $data, $extra_data, 'done'];
}

function on_checkout_handler($param)
{
	global $config, $user_obj, $global_obj, $ready_keys, $used_keys;
	global $user_file, $global_file, $used_key_file, $ready_key_file;
	$period = intval($config['抽奖周期']);
	$succeed_before_failure_count = intval($config['成功前至少失败几次']);
	$max_failure_count = intval($config['最多连续失败几次']);
	$only_mobile = ($config['只允许手机用户抽奖'] === '是');

	//限制只能手机访问
	if ($only_mobile) {
		if (!wp_is_mobile()) {
			return [false, [], ex_data(2001), 'Sorry, only mobile allow.'];
		}
	}

	list($isok, $data, $err_msg) = on_valid_handler($param);
	if (!$isok) {
		return [false, [], ex_data($data['errno']), $err_msg];
	}

	//如果失败次数过多，就会直接成功
	if ($user_obj['continue_failure_count'] < $max_failure_count) {
		$is_won = is_won_lottery(intval($config['中奖几率']));
		//这家伙成功了，看看之前衰过几次？必须得失败上几次，我们心里才平衡
		if ($user_obj['continue_failure_count'] < $succeed_before_failure_count) {
			$is_won = false;
		}
	} else {
		$is_won = true;
	}

	$real_time = time() + $period;
	$user_obj['last_checkout_time'] = time();
	$user_obj['next_checkout_time'] = $real_time;

	if (!$is_won) {
		$user_obj['continue_failure_count'] += 1;
		object_save($user_file, $user_obj);
		return [false, [], ex_data(2003), 'Sorry, you don\'t win the lottery, try in next time.'];
	}

	$got_key = array_shift($ready_keys);
	if (empty($got_key)) {
		$user_obj['continue_failure_count'] += 1;
		object_save($user_file, $user_obj);
		return [false, [], ex_data(2004), 'Sorry, no keys found, try in next time.'];
	}


	array_push($used_keys, $got_key);
	$item_obj = ['time'=>gm_date(time()), 'key'=>$got_key];
	$user_obj['has_items'][] = $item_obj;
	$user_obj['continue_failure_count'] = 0;
	$global_obj['counter'] += 1;
	$global_obj['total_counter'] += 1;
	
	object_save($user_file, $user_obj);
	object_save($global_file, $global_obj);
	object_save($ready_key_file, $ready_keys);
	object_save($used_key_file, $used_keys);
	return [true, [$got_key], ex_data(), 'Ok, you win.'];
}

function on_valid_handler($param)
{
	global $user_file, $config, $user_obj, $global_obj;
	global $ready_keys;
	$need_save = false;
	$nex_time = $user_obj['next_checkout_time'];
	$period = intval($config['抽奖周期']);
	$last_time = $user_obj['last_checkout_time'];
	$real_time = ($last_time===0)? $nex_time : $last_time + $period;

	$max_inperiod = intval($config['周期内最大中奖人数']);
	$max_total = intval($config['总最大中奖人数']);
	$max_got = intval($config['每用户可中奖次数']);
	$now_got = count($user_obj['has_items']);

	$res_value = [true, ex_data(), 'Ok, checkout now please.'];
	do {
		//限制获得奖品的个数
		if ($max_got) {
			if ($now_got>=$max_got) {
				return [false, ex_data(1000), 'Sorry, only can got '.$max_got.' items'];
			}
		}

		if (time() < $real_time) {
			$res_value = [false,ex_data(1001), 'Not yet, you can checkout at '.gm_date($nex_time)];
			break;
		}

		if ($global_obj['counter'] >= $max_inperiod) {
			$real_time = time() + $period;
			$user_obj['last_checkout_time'] = time();
			$user_obj['next_checkout_time'] = $real_time;
			$need_save = true;
			$res_value = [false, ex_data(1002),'Max in period. You can checkout at '.gm_date($real_time)];
			break;
		}

		if ($global_obj['total_counter'] >= $max_total) {
			$real_time = time() + $period;
			$user_obj['last_checkout_time'] = time();
			$user_obj['next_checkout_time'] = time() + $real_time;
			$need_save = true;
			$res_value = [false, ex_data(1003),'Max in total. You can checkout at '.gm_date($real_time)];
			break;
		}

		if (empty($ready_keys)) {
			$real_time = time() + $period;
			$user_obj['last_checkout_time'] = time();
			$user_obj['next_checkout_time'] = time() + $real_time;
			$need_save = true;
			$res_value = [false, ex_data(1004),'No more keys. You can checkout at '.gm_date($real_time)];
			break;
		}

	}while(false);

	if ($need_save) {
		object_save($user_file, $user_obj);
	}

	return $res_value;
}

function on_reset_handler($param)
{
	global $user_file;
	if (@$param['all']) {
		$unlinks = rmdir_Rf(cache_root());
		return ['unlink'=>$unlinks];
	} else {
		unlink($user_file);
		return ['unlink'=>$user_file];
	}
}

