<?php
defined('IN_MOBIQUO') or exit;
require_once($phpbb_root_path . 'includes/functions_user.' . $phpEx);
require_once TT_ROOT . "include/classTTSSO.php";
require_once TT_ROOT . "include/classTTForum.php";
require_once TT_ROOT . "include/classTTConnection.php";
$_COOKIE = array();
$user->session_begin();
$auth->acl($user->data);
$user->setup('ucp');

function sign_in_func()
{
    global $config, $phpbb_root_path, $phpEx, $user;
    
    include_once($phpbb_root_path . 'includes/functions_profile_fields.' . $phpEx);
    
    $data['token'] = trim($_POST['token']);
    $data['code'] = trim($_POST['code']);
    $data['username'] = trim($_POST['username']);
    $data['password'] = trim($_POST['password']);
    $data['email'] = trim($_POST['email']);

    // it's register if there is password.
    // if the forum disabled register or In-App Registration disabled in tapatalk plugin
    if (!empty($data['password']) && ($config['require_activation'] == USER_ACTIVATION_DISABLE || (isset($config['tapatalk_register_status']) && $config['tapatalk_register_status'] == 0)))
    {
        return new xmlrpcresp(new xmlrpcval(
            [
                'result' => new xmlrpcval(false, 'boolean'),
                'result_text' => new xmlrpcval($user->lang['UCP_REGISTER_DISABLE'], 'base64'),
            ], 'struct'));
    }
    
    $sso = new TTSSOBase(new TTForum());
    $sso->signIn($data);
    if ($sso->result === FALSE)
    {
        $errors = $sso->errors;
        $result = array(
            'result' => new xmlrpcval(false, 'boolean'),
            'result_text' => new xmlrpcval(isset($errors[0]) && !empty($errors[0]) ? $errors[0] : '', 'base64'),
        );
        if(!empty($sso->status))
        {
            $result['status'] = new xmlrpcval($sso->status, 'string');
        }
        return new xmlrpcresp(new xmlrpcval($result, 'struct'));
    }
    return $sso->result;
}

function tt_copy_avatar($uid,$avatar_url)
{
    global $config,$phpbb_root_path,$db,$user, $phpEx;
    $can_upload = $config['allow_avatar_remote'];
    if($can_upload && !empty($avatar_url))
    {
        $avatar['user_id'] = $uid;
        $avatar['uploadurl'] = '';
        $avatar['remotelink'] = $avatar_url;
        $avatar['width'] = $config['avatar_max_width'];
        $avatar['height'] = $config['avatar_max_height'];
        $error = array();
        $upload_response = avatar_remote($avatar, $error);

        if(empty($error))
        {
            list($sql_ary['user_avatar_type'], $sql_ary['user_avatar'], $sql_ary['user_avatar_width'], $sql_ary['user_avatar_height']) = $upload_response;
            $sql = 'UPDATE ' . USERS_TABLE . '
            SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
            WHERE user_id = ' . $uid;
            $db->sql_query($sql);
        }    
    }
}

