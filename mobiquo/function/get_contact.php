<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;
require_once TT_ROOT . "function/function.php";
function get_contact_func($xmlrpc_params)
{
    global $db, $user, $auth, $template, $config, $phpbb_root_path, $phpEx,$table_prefix;
    
    $user->setup(array('memberlist', 'groups'));
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    if (!empty($params[0]) && !empty($params[1]))
    {
        $user_id = intval($params[0]);
        $code = $params[1];
    }
    else
    {
        trigger_error('NO_EMAIL');
    }
    
    $user_id = intval($user_id);
    
    // Display a profile
    if (!$user_id) trigger_error('NO_USER');
    
    if (!$config['email_enable'])
    {
        trigger_error('EMAIL_DISABLED');
    }
    
    $connection = new classTTConnection();
    $response = $connection->actionVerification($code,'get_contact');
    if($response !== true)
    {
        trigger_error('NO_EMAIL');
    }

    // Get user...
    $sql = 'SELECT *
        FROM ' . USERS_TABLE . "
        WHERE user_id = '$user_id'";
    $result = $db->sql_query($sql);
    $member = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    
    $api_key = $config['tapatalk_push_key'];
    if(empty($api_key))
    {        
        trigger_error('NO_USER');
    }
    if (!$member) trigger_error('NO_USER');
    
    $e = new TT_Cipher;
    $user_info = array(
        'result'             => new xmlrpcval(true, 'boolean'),
        'user_id'            => new xmlrpcval($member['user_id']),
        'display_name'       => new xmlrpcval(basic_clean($member['username']), 'base64'),
        'enc_email'          => new xmlrpcval(base64_encode($e->encrypt($member['user_email'], $api_key))),
    );
    
    $xmlrpc_user_info = new xmlrpcval($user_info, 'struct');
    return new xmlrpcresp($xmlrpc_user_info);
}
