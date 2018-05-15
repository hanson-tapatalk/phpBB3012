<?php 
defined('IN_MOBIQUO') or exit;
require_once($phpbb_root_path . 'includes/functions_user.' . $phpEx);
$_COOKIE = array();
$user->session_begin();
$auth->acl($user->data);
$user->setup('ucp');

function activate_account_func($xmlrpc_params)
{
	global $config, $phpbb_root_path, $phpEx, $db, $user, $auth;
	$params = php_xmlrpc_decode($xmlrpc_params);
	$email = $params[0];
	$result_text = '';
    require_once TT_ROOT."include/classTTJson.php";
    require_once TT_ROOT."include/classConnection.php";
	$connection = new classFileManagement();
    $verify_result = $connection->signinVerify($params[1],$params[2],generate_board_url(),$config['tapatalk_push_key']);
    if(!empty($verify_result['inactive']))
    {
    	$status = 2;
    }
    else if(!$verify_result['result'])
    {
    	$status = 4;
    	$result_text = $verify_result['result_text'];
    }
    else if ($verify_result['email'] != $email)
    {
    	$status = 3;
    }
    else 
    {
    	$email = $db->sql_escape($email);  	
        $sql = 'SELECT user_id, username, user_type, user_email, user_newpasswd, user_lang, user_notify_type, user_actkey, user_inactive_reason
			FROM ' . USERS_TABLE . "
			WHERE user_email = '$email'";
		$result = $db->sql_query($sql);
		$user_row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		if (!$user_row)
		{
			$status = 1;
		}
		else if ($user_row['user_type'] <> USER_INACTIVE && !$user_row['user_newpasswd'])
		{
			$status = 5;
			$result_text = $user->lang['ALREADY_ACTIVATED'];
		}
		else if ($user_row['user_inactive_reason'] == INACTIVE_MANUAL)
		{
			$status = 5;
			$result_text = $user->lang['WRONG_ACTIVATION'];
		}
		// Do not allow activating by non administrators when admin activation is on
		// Only activation type the user should be able to do is INACTIVE_REMIND
		// or activate a new password which is not an activation state :@
		else if (!$user_row['user_newpasswd'] && $user_row['user_inactive_reason'] != INACTIVE_REMIND && $config['require_activation'] == USER_ACTIVATION_ADMIN && !$auth->acl_get('a_user'))
		{
			$status = 5;
			$result_text = $user->lang['NO_AUTH_OPERATION'];
		}
		else 
		{
			user_active_flip('activate', $user_row['user_id']);
	
			$sql = 'UPDATE ' . USERS_TABLE . "
				SET user_actkey = ''
				WHERE user_id = {$user_row['user_id']}";
			$db->sql_query($sql);
	
			// Create the correct logs
			add_log('user', $user_row['user_id'], 'LOG_USER_ACTIVE_USER');
			if ($auth->acl_get('a_user'))
			{
				add_log('admin', 'LOG_USER_ACTIVE', $user_row['username']);
			}
		}
		
		/*if ($config['require_activation'] == USER_ACTIVATION_ADMIN && !$update_password)
		{
			include_once($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);

			$messenger = new messenger(false);

			$messenger->template('admin_welcome_activated', $user_row['user_lang']);

			$messenger->to($user_row['user_email'], $user_row['username']);

			$messenger->anti_abuse_headers($config, $user);

			$messenger->assign_vars(array(
				'USERNAME'	=> htmlspecialchars_decode($user_row['username']))
			);

			$messenger->send($user_row['user_notify_type']);

			$message = 'ACCOUNT_ACTIVE_ADMIN';
		}
		else
		{
			if (!$update_password)
			{
				$message = ($user_row['user_inactive_reason'] == INACTIVE_PROFILE) ? 'ACCOUNT_ACTIVE_PROFILE' : 'ACCOUNT_ACTIVE';
			}
			else
			{
				$message = 'PASSWORD_ACTIVATED';
			}
		}

		meta_refresh(3, append_sid("{$phpbb_root_path}index.$phpEx"));
		trigger_error($user->lang[$message]);*/
    }
    
    $result = array (
		'result'            => new xmlrpcval(true, 'boolean'),
	);
	
	if(!empty($status)) 
	{
	    $result['status'] = new xmlrpcval($status);
	    $result['result'] = new xmlrpcval(false, 'boolean');
	}
	if(!empty($result_text))
	{
		$result['result_text'] = new xmlrpcval($result_text, 'base64');
		$result['result'] = new xmlrpcval(false, 'boolean');
	}
	return new xmlrpcresp(new xmlrpcval($result, 'struct'));
}