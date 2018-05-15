<?php

/*defined('IN_MOBIQUO') or exit;
require_once TT_ROOT . "function/function.php";

function get_topic_participants_func($xmlrpc_params)
{
	global $db, $user, $phpEx, $table_prefix, $config;
	
	$user->setup(array('memberlist', 'groups'));    
    $params = php_xmlrpc_decode($xmlrpc_params);	
    $topic_id = intval($params[0]);
    $max_num = intval($params[1]);
    $api_key = $config['tapatalk_push_key'];
    if(empty($api_key))
    {    	
    	trigger_error('NO_USER');
    }
    if(empty($max_num)) $max_num = 20;
    $sql = "SELECT user_id, username, user_email as email,user_avatar, user_avatar_type FROM " . USERS_TABLE . " 
    	WHERE user_id IN(SELECT DISTINCT poster_id  FROM " . POSTS_TABLE. " WHERE topic_id = " . $topic_id . ")
    	ORDER BY user_id desc limit " . $max_num;
	$result = $db->sql_query($sql);
	   
    while ($row = $db->sql_fetchrow($result))
    {
    	$user_lists[] = new xmlrpcval(array(
			'username'     => new xmlrpcval($row['username'], 'base64'),
			'user_id'       => new xmlrpcval($row['user_id'], 'string'),
			'icon_url'      => new xmlrpcval(get_user_avatar_url($row['user_avatar'], $row['user_avatar_type']), 'string'),
			'enc_email'     => new xmlrpcval(encrypt($row['email'],$api_key), 'base64')
		), 'struct');
    }
    $db->sql_freeresult($result);
    
    $online_users = new xmlrpcval(array(
		'result' => new xmlrpcval(true, 'boolean'),
		'list'         => new xmlrpcval($user_lists, 'array'),
	), 'struct');

	return new xmlrpcresp($online_users);
}*/