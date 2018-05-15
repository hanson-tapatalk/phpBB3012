<?php
if(!defined('IN_PHPBB') && !defined("IN_MOBIQUO")) exit;
if(!isset($tapatalk_push_run)) $tapatalk_push_run = true;

if($tapatalk_push_run && $mode != 'forum' && $is_watching)
{
    global $config, $topic_data;
    
    if(!defined('IN_MOBIQUO')) define('IN_MOBIQUO', true);
    if(!defined('TT_ROOT')) 
    {
        if(empty($config['tapatalkdir'])) $config['tapatalkdir'] = 'mobiquo';
        define('TT_ROOT', $phpbb_root_path . $config['tapatalkdir'] . '/');
    }

    require_once(TT_ROOT . 'push/TapatalkPush.' . $phpEx);
    
    $tapatalk_push = new TapatalkPush($config['tapatalk_push_key'], generate_board_url());
    $tapatalk_push->doPushSubTopic($topic_data);
}

$tapatalk_push_run = false;