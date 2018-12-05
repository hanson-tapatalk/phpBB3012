<?php
defined('IN_MOBIQUO') or exit;

class TTForum implements TTSSOForumInterface
{
    // return user info array, including key 'email', 'id', etc.
    public function getUserByID($uid)
    {
        return tt_get_user_by_id($uid);
    }
    // return user info array, including key 'email', 'id', etc.
    public function getUserByEmail($email)
    {
        return tt_get_user_by_email($email);
    }
    
    public function getUserByName($username)
    {
        return tt_get_user_by_username($username);
    }

    // the response should be bool to indicate if the username meet the forum requirement
    public function validateUsernameHandle($username)
    {
        if(validate_username($username))
        {
        	return false;
        }
        return true;        
    }

    // the response should be bool to indicate if the password meet the forum requirement
    public function validatePasswordHandle($password)
    {
    	if(empty($password))
    	{
    		return false;
    	}
        return true;
    }

    // create a user, $verified indicate if it need user activation
    public function createUserHandle($email, $username, $password, $verified, $custom_register_fields, $profile, &$errors)
    {
        global $config, $mobiquo_config,$db, $user, $auth, $phpbb_root_path, $phpEx,$user_info;
	    if ($config['require_activation'] == USER_ACTIVATION_DISABLE || $mobiquo_config['sso_signin'] == 0)
		{
			$errors[] = $user->lang['UCP_REGISTER_DISABLE'];
			return false;
		}
        $is_dst = $config['board_dst'];
		$timezone = $config['board_timezone'];    
		$data = array(
			'username'			=> utf8_normalize_nfc($username),
			'new_password'		=> $password,
			'password_confirm'	=> $password,
			'email'				=> strtolower($email),
			'email_confirm'		=> strtolower($email),
			'lang'				=> basename($user->lang_name),
			'tz'				=> (float) $timezone,
		);
		
		//check eve api
		if(!empty($config['eveapi_version']))
		{
			$data['eveapi_keyid'] = 0;
			$data['eveapi_vcode'] = '';
			$data['eveapi_ts'] = '';
			$config['eveapi_validation'] = 0;
		}
		
		//passwrod with any
		$config['pass_complex'] = 'PASS_TYPE_ANY';
				
		$errors = validate_data($data, array(
			'username'			=> array(
				array('string', false, $config['min_name_chars'], $config['max_name_chars']),
				array('username', '')),			
			'email'				=> array(
				array('string', false, 6, 60),
				array('email')),
			'email_confirm'		=> array('string', false, 6, 60),
			'tz'				=> array('num', false, -14, 14),
			'lang'				=> array('language_iso_name'),
		));
			
		// Replace "error" strings with their real, localised form
		$errors = preg_replace('#^([A-Z_]+)$#e', "(!empty(\$user->lang['\\1'])) ? \$user->lang['\\1'] : '\\1'", $errors);
		// DNSBL check
		if ($config['check_dnsbl'])
		{
			if (($dnsbl = $user->check_dnsbl('register')) !== false)
			{
				$errors[] = sprintf($user->lang['IP_BLACKLISTED'], $user->ip, $dnsbl[1]);
			}
		}
			
		$cp = new custom_profile();
		$cp_data = $cp_error = array();
		// validate custom profile fields
		$cp->submit_cp_field('register', $user->get_iso_lang_id(), $cp_data, $errors);
			
		if (!sizeof($errors))
		{
		
			// Which group by default?
			$group_name = 'REGISTERED';
				
			$sql = 'SELECT group_id
				FROM ' . GROUPS_TABLE . "
				WHERE group_name = '" . $db->sql_escape($group_name) . "'
				AND group_type = " . GROUP_SPECIAL;
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
		
			if (!$row)
			{
				$errors[] = $user->lang['NO_GROUP'];
			}
	
			$group_id =  $row['group_id'];
					    
		    $auto_approve = (int) (isset($config['tapatalk_auto_approve']) ? $config['tapatalk_auto_approve'] : 1);
	        if(($auto_approve && $verified) || ($config['require_activation'] == USER_ACTIVATION_NONE )
	        || ($config['require_activation'] == USER_ACTIVATION_SELF && $verified)
	        )
	        {
	        	$user_type = USER_NORMAL;
				$user_actkey = '';
				$user_inactive_reason = 0;
			    $user_inactive_time = 0;
	        }
	        else 
	        {
	        	$user_type = USER_INACTIVE;
				$user_inactive_reason = INACTIVE_REGISTER;
				$user_inactive_time = time();
	        }	
			
		    
			$user_row = array(
				'username'				=> $data['username'],
				'user_password'			=> phpbb_hash($data['new_password']),
				'user_email'			=> $data['email'],
				'group_id'				=> (int) $group_id,
				'user_timezone'			=> (float) $data['tz'],
				'user_dst'				=> $is_dst,
				'user_lang'				=> $data['lang'],
				'user_type'				=> $user_type,
				'user_actkey'			=> $user_actkey,
				'user_ip'				=> $user->ip,
				'user_regdate'			=> time(),
				'user_inactive_reason'	=> $user_inactive_reason,
				'user_inactive_time'	=> $user_inactive_time,
			);
			
			if ($config['new_member_post_limit'])
			{
				$user_row['user_new'] = 1;
			}
			
			if(!empty($profile))
			{
				if(!empty($profile['birthday']) && $config['allow_birthdays'])
				{
					$birth_arr = explode('-', $profile['birthday']);
					$user_row['user_birthday'] = sprintf('%2d-%2d-%4d', $birth_arr[2], $birth_arr[1], $birth_arr[0]);
				}

                if (!empty($profile['location']))
                {
                    $user_row['user_from'] = $profile['location'];
                }
                if (!empty($profile['link']))
                {
                    $user_row['user_website'] = $profile['link'];
                }
                if (!empty($profile['signature']))
                {
                    $user_row['user_sig'] = $profile['signature'];
                }
				
			}
			
			// Register user...
			$user_id = user_add($user_row,$cp_data);	
			if(!empty($config['tapatalk_register_group']) && $config['tapatalk_register_group'] != $group_id)
			{
				group_user_add($config['tapatalk_register_group'], $user_id);
			}
			//copy avatar
			tt_copy_avatar($user_id, $profile['avatar_url']);
			// This should not happen, because the required variables are listed above...
			if ($user_id === false)
			{
				$errors[] = $user->lang['NO_USER'];
			}
			else 
			{
				if ($config['require_activation'] == USER_ACTIVATION_SELF && $config['email_enable'])
				{
					$message = $user->lang['ACCOUNT_INACTIVE'];
					$email_template = 'user_welcome_inactive';
				}
				else if ($config['require_activation'] == USER_ACTIVATION_ADMIN && $config['email_enable'])
				{
					$message = $user->lang['ACCOUNT_INACTIVE_ADMIN'];
					$email_template = 'admin_welcome_inactive';
				}
				
				if ($config['email_enable'] && $user_type == USER_INACTIVE)
				{
					$server_url = generate_board_url();
					include_once($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);

					$messenger = new messenger(false);
		
					$messenger->template($email_template, $data['lang']);
		
					$messenger->to($data['email'], $data['username']);
					
					if(!method_exists($messenger, 'anti_abuse_headers'))
					{
						$messenger->headers('X-AntiAbuse: Board servername - ' . $config['server_name']);
						$messenger->headers('X-AntiAbuse: User_id - ' . $user->data['user_id']);
						$messenger->headers('X-AntiAbuse: Username - ' . $user->data['username']);
						$messenger->headers('X-AntiAbuse: User IP - ' . $user->ip);
					}
					else 
					{
						$messenger->anti_abuse_headers($config, $user);
					}
					
					$messenger->assign_vars(array(
						'WELCOME_MSG'	=> htmlspecialchars_decode(sprintf($user->lang['WELCOME_SUBJECT'], $config['sitename'])),
						'USERNAME'		=> htmlspecialchars_decode($data['username']),
						'PASSWORD'		=> htmlspecialchars_decode($data['new_password']),
						'U_ACTIVATE'	=> "$server_url/ucp.$phpEx?mode=activate&u=$user_id&k=$user_actkey")
					);
		
		
					$messenger->send(NOTIFY_EMAIL);
		
					if ($config['require_activation'] == USER_ACTIVATION_ADMIN)
					{
						// Grab an array of user_id's with a_user permissions ... these users can activate a user
						$admin_ary = $auth->acl_get_list(false, 'a_user', false);
						$admin_ary = (!empty($admin_ary[0]['a_user'])) ? $admin_ary[0]['a_user'] : array();
		
						// Also include founders
						$where_sql = ' WHERE user_type = ' . USER_FOUNDER;
		
						if (sizeof($admin_ary))
						{
							$where_sql .= ' OR ' . $db->sql_in_set('user_id', $admin_ary);
						}
		
						$sql = 'SELECT user_id, username, user_email, user_lang, user_jabber, user_notify_type
							FROM ' . USERS_TABLE . ' ' .
							$where_sql;
						$result = $db->sql_query($sql);
		
						while ($row = $db->sql_fetchrow($result))
						{
							$messenger->template('admin_activate', $row['user_lang']);
							$messenger->to($row['user_email'], $row['username']);
							$messenger->im($row['user_jabber'], $row['username']);
		
							$messenger->assign_vars(array(
								'USERNAME'			=> htmlspecialchars_decode($data['username']),
								'U_USER_DETAILS'	=> "$server_url/memberlist.$phpEx?mode=viewprofile&u=$user_id",
								'U_ACTIVATE'		=> "$server_url/ucp.$phpEx?mode=activate&u=$user_id&k=$user_actkey")
							);
		
							$messenger->send($row['user_notify_type']);
						}
						$db->sql_freeresult($result);
					}				
				}
				$user_info['user_id'] = $user_id;
				$user_info = array_merge($user_info,$user_row);
				return $user_info;												
			}
		}
		return false;
    }

    // login to an existing user, return result as bool
    public function loginUserHandle($user_info, $register)
    {
	    global $config, $db, $user, $phpbb_root_path, $phpEx,$auth;
		header('Set-Cookie: mobiquo_a=0');
	    header('Set-Cookie: mobiquo_b=0');
	    header('Set-Cookie: mobiquo_c=0');
	    $user->session_kill();
	    if($user_info['user_id'] == 1)
	    {
	    	return false;
	    }
		$result = $user->session_create($user_info['user_id'], 0, true, 1);
		
		if($result)
		{
			$usergroup_id = array();
	        $auth->acl($user->data);
	        //add tapatalk_users here,for push service
	        if(push_table_exists())
	        {
	        	global $table_prefix;
	        	$sql = "SELECT * FROM " . $table_prefix . "tapatalk_users where userid = '".$user->data['user_id']."'";
		        $result = $db->sql_query($sql);
		        $userInfo = $db->sql_fetchrow($result);
		        $db->sql_freeresult($result);
		        $time = time();
	        	if(empty($userInfo))
	        	{
		        	$sql_data[$table_prefix . "tapatalk_users"]['sql'] = array(
		        		'userid' => $user->data['user_id'],
		        		'announcement' => 1,
		        		'pm' => 1,
		        		'subscribe' => 1,
		        		'quote' => 1,
		        		'tag' => 1,
		        		'newtopic' => 1,
		        		'updated' => time()
		        	);
		        	$sql = 'INSERT INTO ' . $table_prefix . "tapatalk_users" . ' ' .
					$db->sql_build_array('INSERT', $sql_data[$table_prefix . "tapatalk_users"]['sql']);
					$db->sql_query($sql);    	
		        }
	        }
	        
	        // Compatibility with mod NV who was here
	        if (file_exists($phpbb_root_path . 'includes/mods/who_was_here.' . $phpEx))
	        {
	            include_once($phpbb_root_path . 'includes/mods/who_was_here.' . $phpEx);
	            if (class_exists('phpbb_mods_who_was_here') && method_exists('phpbb_mods_who_was_here', 'update_session'))
	            {
	                @phpbb_mods_who_was_here::update_session();
	            }
	        }
	        
	        if ($config['max_attachments'] == 0) $config['max_attachments'] = 100;
	    
		    $usergroup_id[] = new xmlrpcval($user->data['group_id']);
		    $can_readpm = $config['allow_privmsg'] && $auth->acl_get('u_readpm') && ($user->data['user_allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_'));
		    $can_sendpm = $config['allow_privmsg'] && $auth->acl_get('u_sendpm') && ($user->data['user_allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_'));
		    $can_upload = ($config['allow_avatar_upload'] && file_exists($phpbb_root_path . $config['avatar_path']) && (function_exists('phpbb_is_writable') ? phpbb_is_writable($phpbb_root_path . $config['avatar_path']) : 1) && $auth->acl_get('u_chgavatar') && (@ini_get('file_uploads') || strtolower(@ini_get('file_uploads')) == 'on')) ? true : false;
		    $can_search = $auth->acl_get('u_search') && $auth->acl_getf_global('f_search') && $config['load_search'];
		    $can_whosonline = $auth->acl_gets('u_viewprofile', 'a_user', 'a_useradd', 'a_userdel');
		    $max_filesize   = ($config['max_filesize'] === '0' || $config['max_filesize'] > 10485760) ? 10485760 : $config['max_filesize'];
		    
		    $userPushType = array('pm' => 1,'newtopic' => 1,'sub' => 1,'tag' => 1,'quote' => 1);
		    $push_type = array();
		    
		 	foreach ($userPushType as $name=>$value)
		    {
		    	$push_type[] = new xmlrpcval(array(
		            'name'  => new xmlrpcval($name,'string'),
		    		'value' => new xmlrpcval($value,'boolean'),                    
		            ), 'struct');
		    }   
		    
			$flood_interval = 0;
		    if ($config['flood_interval'] && !$auth->acl_get('u_ignoreflood'))
		    {
		    	$flood_interval = intval($config['flood_interval']);
		    }
		    $response = new xmlrpcval(array(
		        'result'        => new xmlrpcval(true, 'boolean'),
		        'user_id'       => new xmlrpcval($user_info['user_id'], 'string'),
		        'username'      => new xmlrpcval(basic_clean($user_info['username']), 'base64'),
		        'login_name'    => new xmlrpcval(basic_clean($user_info['username']), 'base64'),
		    	'email'         => new xmlrpcval(sha1(strtolower($user_info['user_email'])), 'base64'),
				'user_type'     => check_return_user_type($user_info['user_id']),
				//'tapatalk'      => new xmlrpcval(is_tapatalk_user($user->data['user_id']), 'string'),
		        'usergroup_id'  => new xmlrpcval($usergroup_id, 'array'),
		        'ignored_uids'  => new xmlrpcval(implode(',', tt_get_ignore_users($user->data['user_id'])),'string'),
		        'icon_url'      => new xmlrpcval(get_user_avatar_url($user->data['user_avatar'], $user->data['user_avatar_type']), 'string'),
		        'post_count'    => new xmlrpcval($user->data['user_posts'], 'int'),
		        'can_pm'        => new xmlrpcval($can_readpm, 'boolean'),
		        'can_send_pm'   => new xmlrpcval($can_sendpm, 'boolean'),
		        'can_moderate'  => new xmlrpcval($auth->acl_get('m_') || $auth->acl_getf_global('m_'), 'boolean'),
		        'max_attachment'=> new xmlrpcval($config['max_attachments'], 'int'),
		        'max_png_size'  => new xmlrpcval($max_filesize, 'int'),
		        'max_jpg_size'  => new xmlrpcval($max_filesize, 'int'),
		        'can_search'    => new xmlrpcval($can_search, 'boolean'),
		        'can_whosonline'    => new xmlrpcval($can_whosonline, 'boolean'),
		        'can_upload_avatar' => new xmlrpcval($can_upload, 'boolean'),
		    	'register'          => new xmlrpcval($register, "boolean"),
		    	'push_type'         => new xmlrpcval($push_type, 'array'), 
		    	'post_countdown'    => new xmlrpcval($flood_interval,'int'),
		    	'max_avatar_size'   => new xmlrpcval($config['avatar_filesize'],'int'),
	    		'max_avatar_width'  => new xmlrpcval($config['avatar_max_width'],'int'),
	    		'max_avatar_height'  => new xmlrpcval($config['avatar_max_height'],'int'),
		    
		    ), 'struct');
		    
		    return new xmlrpcresp($response);
		}
		return false;
    }

    // return forum api key
    public function getAPIKey()
    {
        global $config;
        return isset($config['tapatalk_push_key']) ? $config['tapatalk_push_key'] : '';
    }

    // return forum url
    public function getForumUrl()
    {
        return generate_board_url();
    }

    // email obtain from userInfo for compared with TTEmail
    public function getEmailByUserInfo($userInfo)
    {
        return $userInfo['user_email'];
    }
}