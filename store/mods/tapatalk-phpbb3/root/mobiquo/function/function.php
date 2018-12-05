<?php
defined('IN_MOBIQUO') or exit;
require_once TT_ROOT . "include/classTTJson.php";
require_once TT_ROOT . "include/classTTConnection.php";
include_once TT_ROOT . "include/classTTCipherEncrypt.php";
function set_api_key_func()
{
    $code = trim($_REQUEST['code']);
    $key = trim($_REQUEST['key']);
    $format = isset($_REQUEST['format']) ? trim($_REQUEST['format']) : '';
    $connection = new classTTConnection();
    $response = $connection->actionVerification($code,'set_api_key');
    $result = false;
    if($response === true)
    {
        set_config('tapatalk_push_key', $key);
        $result = true;
    }
    $data = array(
         'result' => $result,
         'result_text' => $response,
     );
    
    $response = ($format == 'json') ? json_encode($data) : serialize($data);
    echo $response;
}


function user_subscription_func(){
    global $db;

    $code = trim($_POST['code']);
    $uid = intval(trim($_POST['uid']));
    $format = isset($_POST['format']) ? trim($_POST['format']) : '';

    try {
        $connection = new classTTConnection();
        $response = $connection->actionVerification($code, 'user_subscription');

        $data = array( 'result' => false );
        if ($response !== true){
            $data['result_text'] = $response;
            echo ($format == 'json') ? json_encode($data) : serialize($data);
            exit;
        }

        $forums = array();
        $topics = array();
        
        $sql = 'SELECT w.forum_id, f.forum_name
			FROM ' . FORUMS_WATCH_TABLE . ' AS w
            JOIN ' . FORUMS_TABLE . ' AS f on w.forum_id = f.forum_id
			WHERE w.user_id=' . $uid;
        $result = $db->sql_query($sql);
        
		while ($row = $db->sql_fetchrow($result))
		{
            $forums[] = array(
                           'fid'      => $row['forum_id'],
                           'name'    => $row['forum_name'],
                       );
		}
		$db->sql_freeresult($result);
        
        $sql = 'SELECT w.topic_id
			FROM ' . TOPICS_WATCH_TABLE . ' AS w
			WHERE w.user_id=' . $uid;
        $result = $db->sql_query($sql);
        
		while ($row = $db->sql_fetchrow($result))
		{
            $topics[] = $row['topic_id'];
		}
		$db->sql_freeresult($result);
        
        $data['result'] = true;
        $data['forums'] = $forums;
        $data['topics'] = $topics;
    }
    catch (Exception $e){
        $data = array(
                'result' => false,
                'result_text' => $e->getMessage(),
        );
    }
    $response = ($format == 'json') ? json_encode($data) : serialize($data);
    echo $response;
    exit;
}



function push_content_check_func(){
    global $db;
    $code = trim($_POST['code']);
    $format = isset($_POST['format']) ? trim($_POST['format']) : '';
    $data = unserialize(trim($_POST['data']));

    $result = array( 'result' => false );
    try {
        $connection = new classTTConnection();
        $response = $connection->actionVerification($code, 'push_content_check');
        if ($response !== true){
            $result['result_text'] = $response;
            echo ($format == 'json') ? json_encode($result) : serialize($result);
            exit;
        }
        
        $data = unserialize(base64_decode($data['data']));
        
        $data  = $data[0];
        
        switch($data['type'])
        {
            case 'newtopic':
            case 'sub':
            case 'quote':
            case 'tag':
                {
                    $sql = "SELECT p.topic_id, p.poster_id, u.username FROM " . POSTS_TABLE . " p JOIN " . USERS_TABLE . " u ON u.user_id = p.poster_id WHERE p.post_id = ". intval($data['subid']);
                    $resultdb = $db->sql_query($sql);
                    $row = $db->sql_fetchrow($resultdb);
                    if($row['topic_id'] == $data['id'] && ($row['poster_id'] == $data['authorid'] || $row['username'] == $data['author']))
                    {
                        $result['result'] = true;
                        }
                    $db->sql_freeresult($resultdb);
                    break;
                } 
            case 'pm':
                {
                    $sql = "SELECT p.author_id, u.username FROM " . PRIVMSGS_TABLE . " p JOIN " . USERS_TABLE . " u ON u.user_id = p.author_id WHERE p.msg_id = ". intval($data['id']);
                    $resultdb = $db->sql_query($sql);
                    $row = $db->sql_fetchrow($resultdb);
                    if($row['author_id'] == $data['authorid'] || $row['username'] == $data['author'])
                    {
                        $result['result'] = true;
                        }
                    $db->sql_freeresult($resultdb);
                    break;
                }
            
        }
    }
    catch (Exception $e){
        $result['result_text'] = $e->getMessage();
    }
    echo ($format == 'json') ? json_encode($result) : serialize($result);
    exit;
}

function set_forum_info_func()
{
    $code = trim($_REQUEST['code']);
    $api_key = trim($_REQUEST['api_key']);
    $banner_info = trim($_REQUEST['banner_info']);
    $connection = new classTTConnection();
    $response = $connection->actionVerification($code,'set_forum_info');
    $result = false;
    if($response === true)
    {
        if (!empty($api_key)) 
        {
            set_config('tapatalk_push_key', $api_key);
        }
        
        if (!empty($banner_info)) 
        {
            global $config, $phpbb_home;
            if ($banner_info === true)
            {
                $banner_info = $connection->getForumInfo($phpbb_home, $config['tapatalk_push_key']);
            }
            if (is_string($banner_info)) 
            {
                $banner_info = json_decode($banner_info, true);
            }

            if (isset($banner_info['banner_enable'])) 
            {
                set_config('tapatalk_app_banner_enable', $banner_info['banner_enable']);    
            }

            if (isset($banner_info['google_enable']))
            {
                set_config('tapatalk_google_enable', $banner_info['google_enable']);
            }
            
            set_config('tapatalk_banner_update', time());
        }
        $result = true;
    }
    $data = array(
        'result' => $result,
        'result_text' => $banner_info,
    );

    echo json_encode($data);
}