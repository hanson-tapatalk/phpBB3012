<?php
if(!defined('IN_PHPBB') && !defined("IN_MOBIQUO")) exit;
if(!isset($tapatalk_hook_run)) $tapatalk_hook_run = true;
if(isset($config['tapatalk_spam_status']) && ($config['tapatalk_spam_status'] >= 2) && $tapatalk_hook_run)
{
	if(!function_exists("tt_is_spam"))
	{
		define('IN_MOBIQUO',true);
		require_once $phpbb_root_path.(!empty($config['tapatalkdir']) ? $config['tapatalkdir'] : 'mobiquo').'/mobiquo_common.php';
	}
	if(isset($data['email']) && isset($user->ip) && tt_is_spam($data['email'],$user->ip))
	{
		trigger_error("Your email address matches that of a known spammer and therefore you cannot register here. If you feel this is an error, please contact the administrator or try again later.");
	}
}
$tapatalk_hook_run = false;
