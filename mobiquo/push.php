<?php
error_reporting(E_ALL & ~E_NOTICE);
$phpbb_root_path = dirname(dirname(__FILE__)).'/';
define('IN_PHPBB', 1);
$phpEx = 'php';
define('IN_MOBIQUO', 1);
require_once 'xmlrpcresp.' . $phpEx;
include($phpbb_root_path . 'common.php');

if(!defined('TT_ROOT')) 
{
    if(empty($config['tapatalkdir'])) $config['tapatalkdir'] = 'mobiquo';
    define('TT_ROOT', $phpbb_root_path . $config['tapatalkdir'] . '/');
}

require_once(TT_ROOT . '/push/TapatalkPush.' . $phpEx);
$board_url = generate_board_url();   
$tapatalk_push = new TapatalkPush($config['tapatalk_push_key'], $board_url);
$return_status = $tapatalk_push->do_push_request(array('test' => 1), true);
if(empty($config['tapatalk_push_key']))
{
	$return_status = 'Please set Tapatalk API Key at forum option/setting';
}


$table_exist = push_table_exists();
if(isset($_GET['checkcode']))
{
	$string = file_get_contents($phpbb_root_path . 'includes/functions_posting.php');
	echo 'push code have been added in phpbb : ' . (strstr($string , 'tapatalkdir') ? 'yes' : 'no' ). '<br/>';
	exit;
}

echo '<b>Tapatalk Push Notification Status Monitor</b><br/>';
echo '<br/>Push notification test: ' . (($return_status === '1') ? '<b>Success</b>' : 'Failed('.$return_status.')');
echo '<br/>Current forum url: ' . $board_url;
echo '<br/>Tapatalk user table existence: ' . ($table_exist ? 'Yes' : 'No');
echo '<br/><br/><a href="https://www.tapatalk.com/api/api.php" target="_blank">Tapatalk API for Universal Forum Access</a> | <a href="https://www.tapatalk.com/mobile.php" target="_blank">Tapatalk Mobile Applications</a><br>
    For more details, please visit <a href="https://www.tapatalk.com" target="_blank">https://www.tapatalk.com</a>';



