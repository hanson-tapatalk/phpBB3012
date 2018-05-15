<?php
if(!defined('IN_MOBIQUO')) exit;
error_reporting(0);
define('PHPBB_MSG_HANDLER', 'tt_email_error');
require_once './include/mobi_acp_email.' .$phpEx;
require_once $phpbb_root_path . 'includes/functions_admin.' .$phpEx;
include_once TT_ROOT."include/classTTJson.php";
if (function_exists('set_magic_quotes_runtime'))
	@set_magic_quotes_runtime(0);
@ini_set('max_execution_time', '120');

$invite_response['result'] = false;
$furl = generate_board_url();
if(!empty($_POST['session']) && !empty($_POST['api_key']) && !empty($_POST['subject']) && !empty($_POST['body']))
{
	$_POST['submit'] = true;
	$GLOBALS['_REQUEST']['message'] = $_POST['message'] = $_POST['body'];
	$email = new mobi_acp_email();
    $push_url = "http://tapatalk.com/forum_owner_invite.php?PHPSESSID=$_POST[session]&api_key=$_POST[api_key]&url=".urlencode($furl)."&action=verify";
    $response = getContentFromRemoteServer($push_url, 10, $error, 'GET');
    if($response) $result = json_decode($response, true);
    if(empty($result) || empty($result['result']))
        if(preg_match('/\{"result":true/', $response))
            $result = array('result' => true); 
    if(isset($result) && isset($result['result']) && $result['result'])
    {
        if(!empty($_POST['username']))
        {
        	$GLOBALS['_REQUEST']['usernames'] = $_POST['usernames'] = $_POST['username'];
            $GLOBALS['_REQUEST']['send_immediately'] = $_POST['send_immediately'] = true;
        }
        
        $invite_response = $email->main('email', 'email');
    }
    else
    {
        $invite_response['result_text'] = $error;
    }
}
else if(!empty($_POST['email_target']))
{
    //get email targe
    $sql_ary = array('SELECT'	=> 'count(*) as total_count',
							'FROM'		=> array(
								USERS_TABLE	=> 'u',
							),
							'WHERE'		=> 'u.user_allow_massemail = 1
								AND u.user_type IN (' . USER_NORMAL . ', ' . USER_FOUNDER . ')',
							'ORDER_BY'	=> 'u.user_lang, u.user_notify_type',
						);
	$sql = $db->sql_build_query('SELECT', $sql_ary);
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
    echo $row['total_count'];
    exit;
}

header('Content-type: application/json');
echo json_encode($invite_response);
exit;


