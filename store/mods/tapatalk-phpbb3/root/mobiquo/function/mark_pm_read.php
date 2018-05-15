<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function mark_pm_read_func($xmlrpc_params)
{
	global $db, $auth, $user, $config;
	
	$params = php_xmlrpc_decode($xmlrpc_params);
	$msg_id = trim($params[0]);
	$msg_id = $db->sql_escape($msg_id);
	$user->setup('ucp');	
   
    $user_id = $user->data['user_id'];
    
    if(!empty($msg_id))
    {
    	$sql = 'UPDATE ' . PRIVMSGS_TO_TABLE . "
		SET pm_unread = 0
		WHERE msg_id in ($msg_id)
			AND user_id = '$user_id'	";
    	$db->sql_query($sql);
    	$read_count = count(explode(',', $msg_id));
    	
    	$sql = 'UPDATE ' . USERS_TABLE . "
		SET user_unread_privmsg = user_unread_privmsg - $read_count
		WHERE user_id = $user_id";
		$db->sql_query($sql);
    }
    else 
    {
    	$sql = 'UPDATE ' . PRIVMSGS_TO_TABLE . "
		SET pm_unread = 0
		WHERE user_id = '$user_id'	";
    	$db->sql_query($sql);
    	
    	$sql = 'UPDATE ' . USERS_TABLE . "
		SET user_unread_privmsg = 0
		WHERE user_id = $user_id";
		$db->sql_query($sql);
    }
	
	return xmlresptrue();
}