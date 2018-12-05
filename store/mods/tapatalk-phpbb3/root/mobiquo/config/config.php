<?php
/**
*
* @copyright (c) 2009 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

defined('IN_MOBIQUO') or exit;
$mobiquo_config = array(
	'api_level' => '3',
	'version' => 'pb30_5.0.1',
	'is_beta' => '0',
	'is_open' => '1',
	'guest_okay' => '1',
	'php_extension' => 'php',
	'reg_url' => 'ucp.php?mode=register',
	'hide_forum_id' => '-3',
	'check_dnsbl' => '1',
	'disable_search' => '0',
	'disable_latest' => '0',
	'disable_bbcode' => '0',
	'report_post' => '1',
	'report_pm' => '1',
	'mark_read' => '1',
	'mark_pm_unread' => '1',
	'mark_forum' => '1',
	'goto_unread' => '1',
	'goto_post' => '1',
	'get_latest_topic' => '1',
	'no_refresh_on_post' => '1',
	'get_id_by_url' => '1',
	'mod_approve' => '1',
	'mod_delete' => '0',
	'mod_report' => '1',
	'soft_delete' => '0',
	'delete_reason' => '0',
	'emoji_support' => '1',
	'user_id' => '1',
	'multi_quote' => '1',
	'pm_load' => '1',
	'subscribe_load' => '1',
	'participated_forum' => '1',
	'anonymous' => '1',
	'advanced_search' => '1',
	'alert' => '0',
	'push' => '1',
	'push_type' => 'pm,sub,quote,newtopic,tag',
	'inappreg' => '1',
	'announcement' => '1',
	'ban_delete_type' => 'hard_delete',
	'sign_in' => '1',
	'search_user' => '1',
	'ignore_user' => '1',
	'm_approve' => '1',
	'm_delete' => '0',
	'm_report' => '1',
	'sso_login' => '1',
	'sso_signin' => '1',
	'sso_register' => '1',
	'native_register' => '1',
	'avatar' => '1',
	'advanced_edit' => '1',
	'advanced_move' => '1',
	'ban_expires' => '1',
	'close_report' => '1',
	'get_contact' => '1',
	'mark_pm_read' => '1',
	'advance_register' => '1',
	'login_type' => 'username',
	'sso_activate' => '1',
    'banner_control' => '1',
);
foreach($_COOKIE as $key => $value) $_REQUEST[$key] = $value;
$phpEx = $mobiquo_config['php_extension'];
$phpbb_root_path = dirname(dirname(dirname(__FILE__))).'/';
$mobiquo_root_path = dirname(dirname(__FILE__)).'/';
$mobiquo_config['hide_forum_id'] = explode(',', $mobiquo_config['hide_forum_id']);
define('PHPBB_ROOT_PATH',$phpbb_root_path);

