<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;
include_once TT_ROOT."include/classTTJson.php";
function get_recommended_user_func()
{
	global $db, $auth, $user, $config, $phpbb_home,$table_prefix,$tapatalk_users;
	$tapatalk_users = array();
	$users = array();
	
	$api_key = $config['tapatalk_push_key'];
    if(empty($api_key))
    {    	
    	trigger_error('NO_USER');
    }

	//get tapatalk users
	$sql = 	"SELECT userid AS uid FROM " . $table_prefix . "tapatalk_users";
	$query = $db->sql_query($sql);
	while ($row =  $db->sql_fetchrow($query))
	{
		$tapatalk_users[] = $row['uid'];
	}
	
	//get pm users
	$sql = "SELECT user_id AS uid FROM " . PRIVMSGS_TO_TABLE . " WHERE author_id = '" . $user->data['user_id'] . "'";
	$query = $db->sql_query($sql);
	while ($row =  $db->sql_fetchrow($query))
	{
		if(isset($users[$row['uid']]))
			$users[$row['uid']] = $users[$row['uid']] + 10;
		else 
			$users[$row['uid']] = 10;
	}
	
	//get pm to me users 
    $sql = "SELECT author_id AS uid FROM " . PRIVMSGS_TO_TABLE . " WHERE user_id = '" . $user->data['user_id'] . "'";
	$query = $db->sql_query($sql);
	while ($row =  $db->sql_fetchrow($query))
	{
		if(isset($users[$row['uid']]))
			$users[$row['uid']] = $users[$row['uid']] + 10;
		else 
			$users[$row['uid']] = 10;
	}
	
	//get sub users 
	$sql = "SELECT tw.user_id AS uid FROM " . TOPICS_WATCH_TABLE . " AS tw 
	LEFT JOIN " . TOPICS_TABLE . " AS t ON tw.topic_id=t.topic_id 
	WHERE t.topic_poster = '" . $user->data['user_id'] . "'";
	$query = $db->sql_query($sql);
	while ($row =  $db->sql_fetchrow($query))
	{
		if(isset($users[$row['uid']]))
			$users[$row['uid']] = $users[$row['uid']] + 5;
		else 
			$users[$row['uid']] = 5;
	}
	
	//get me sub users 
	$sql = "SELECT t.topic_poster AS uid FROM " . TOPICS_WATCH_TABLE . " AS tw 
	RIGHT JOIN " . TOPICS_TABLE . " AS t ON tw.topic_id=t.topic_id 
	WHERE tw.user_id = '" . $user->data['user_id'] . "'";
	$query = $db->sql_query($sql);
	while ($row =  $db->sql_fetchrow($query))
	{
		if(isset($users[$row['uid']]))
			$users[$row['uid']] = $users[$row['uid']] + 5;
		else 
			$users[$row['uid']] = 5;
	}
	
	arsort($users);	

	foreach ($users as $key =>$row)
	{
		//non tapatalk users
		if(isset($_POST['mode']) && $_POST['mode'] == 2 && in_array($key, $tapatalk_users))
		{
			unset($users[$key]);
		}
		if($key == $user->data['user_id'])	
		{
			unset($users[$key]);
		}
		if($key == 1)
		{
			unset($users[$key]);
		}
	}

	$page =  intval($_POST['page']);
    $perpage = intval($_POST['perpage']);
    $start = ($page-1) * $perpage;
	$total = count($users);
	$users_keys = array_keys($users);
	$users_slice = array_slice($users_keys, $start,$perpage);
	$id_str = implode(',', $users_slice);
	$return_user_lists = array();
	if(!empty($id_str))
	{
		$sql = "SELECT * FROM " . USERS_TABLE . " WHERE user_id in (" . $id_str . ")";
		$query = $db->sql_query($sql);
		while ($row =  $db->sql_fetchrow($query))
		{		
	        $return_user_lists[] = new xmlrpcval(array(
	            'username'      => new xmlrpcval(basic_clean($row['username']), 'base64'),
	            'user_id'       => new xmlrpcval($row['user_id'], 'string'),
	            'icon_url'      => new xmlrpcval(get_user_avatar_url($row['user_avatar'], $row['user_avatar_type']),'string'),
	            'type'          => new xmlrpcval('', 'string'),
	        ), 'struct');
		}
	}
	$suggested_users = new xmlrpcval(array(
        'total' => new xmlrpcval($total, 'int'),
        'list'         => new xmlrpcval($return_user_lists, 'array'),
    ), 'struct');

    return new xmlrpcresp($suggested_users);
}

