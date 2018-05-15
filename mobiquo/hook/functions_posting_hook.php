<?php
if(!defined('IN_PHPBB') && !defined("IN_MOBIQUO")) exit;
if(!isset($tapatalk_push_run)) $tapatalk_push_run = true;
if(!isset($post_approval)) $post_approval = true;
if ($url && $post_approval && $tapatalk_push_run)
{
    if(!defined('IN_MOBIQUO')) define('IN_MOBIQUO', true);
    if(!defined('TT_ROOT')) 
    {
        if(empty($config['tapatalkdir'])) $config['tapatalkdir'] = 'mobiquo';
        define('TT_ROOT', $phpbb_root_path . $config['tapatalkdir'] . '/');
    }

    require_once(TT_ROOT . 'push/TapatalkPush.' . $phpEx);
    
    $tapatalk_push = new TapatalkPush($config['tapatalk_push_key'], generate_board_url());
    switch ($mode)
    {
        case 'reply':
            $tapatalk_push->doPushReply($data);
            $tapatalk_push->doPushQuote($data);
            $tapatalk_push->doPushTag($data);
            break;
        case 'post':
            $tapatalk_push->doPushPost($data);
            $tapatalk_push->doPushTag($data);
            break;
        case 'quote':
            $tapatalk_push->doPushQuote($data);
            $tapatalk_push->doPushTag($data);
            $tapatalk_push->doPushReply($data);
            break;
    }           
}
$tapatalk_push_run = false;