<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function subscribe_topic_func($xmlrpc_params)
{
    global $db, $user, $config, $phpEx;
    
    $user->setup('viewtopic');
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    // get topic id from parameters
    $topic_id = intval($params[0]);
    if (!$topic_id) trigger_error('NO_TOPIC');
    $user_id = $user->data['user_id'];
    $s_result = false;

    // Is user login?
    if ($user_id != ANONYMOUS)
    {
        $sql = 'SELECT notify_status
                FROM ' . TOPICS_WATCH_TABLE . "
                WHERE topic_id = $topic_id
                AND user_id = $user_id";
        $result = $db->sql_query($sql);

        $notify_status = ($row = $db->sql_fetchrow($result)) ? $row['notify_status'] : NULL;
        $db->sql_freeresult($result);

        if (!is_null($notify_status) && $notify_status !== '')
        {
            if ($notify_status)
            {
                $sql = 'UPDATE ' . TOPICS_WATCH_TABLE . "
                        SET notify_status = 0
                        WHERE topic_id = $topic_id
                        AND user_id = $user_id";
                $db->sql_query($sql);
            }
        }
        else
        {
            $sql = 'INSERT INTO ' . TOPICS_WATCH_TABLE . " (user_id, topic_id, notify_status)
                    VALUES ($user_id, $topic_id, 0)";
            $db->sql_query($sql);
            $s_result = true;
        }
        
        require_once(TT_ROOT . '/push/TapatalkPush.' . $phpEx);
        
        $topic_data = get_topic_data(array($topic_id));
        
        if(isset($topic_data[$topic_id]))
        {
            $tapatalk_push = new TapatalkPush($config['tapatalk_push_key'], generate_board_url());
            $tapatalk_push->doPushSubTopic($topic_data[$topic_id]);
        }
    }
    
    $response = new xmlrpcval(
        array(
            'result'        => new xmlrpcval($s_result, 'boolean'),
            'result_text'   => new xmlrpcval($s_result ? '' : 'Subscribe failed', 'base64'),
        ),
        'struct'
    );
    
    return new xmlrpcresp($response);
}

/**
* Get simple topic data
*/
function get_topic_data($topic_ids, $acl_list = false, $read_tracking = false)
{
    global $auth, $db, $config, $user;
    static $rowset = array();

    $topics = array();

    if (!sizeof($topic_ids))
    {
        return array();
    }

    // cache might not contain read tracking info, so we can't use it if read
    // tracking information is requested
    if (!$read_tracking)
    {
        $cache_topic_ids = array_intersect($topic_ids, array_keys($rowset));
        $topic_ids = array_diff($topic_ids, array_keys($rowset));
    }
    else
    {
        $cache_topic_ids = array();
    }

    if (sizeof($topic_ids))
    {
        $sql_array = array(
            'SELECT'    => 't.*, f.*',

            'FROM'        => array(
                TOPICS_TABLE    => 't',
            ),

            'LEFT_JOIN'    => array(
                array(
                    'FROM'    => array(FORUMS_TABLE => 'f'),
                    'ON'    => 'f.forum_id = t.forum_id'
                )
            ),

            'WHERE'        => $db->sql_in_set('t.topic_id', $topic_ids)
        );

        if ($read_tracking && $config['load_db_lastread'])
        {
            $sql_array['SELECT'] .= ', tt.mark_time, ft.mark_time as forum_mark_time';

            $sql_array['LEFT_JOIN'][] = array(
                'FROM'    => array(TOPICS_TRACK_TABLE => 'tt'),
                'ON'    => 'tt.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = tt.topic_id'
            );

            $sql_array['LEFT_JOIN'][] = array(
                'FROM'    => array(FORUMS_TRACK_TABLE => 'ft'),
                'ON'    => 'ft.user_id = ' . $user->data['user_id'] . ' AND t.forum_id = ft.forum_id'
            );
        }

        $sql = $db->sql_build_query('SELECT', $sql_array);
        $result = $db->sql_query($sql);

        while ($row = $db->sql_fetchrow($result))
        {
            if (!$row['forum_id'])
            {
                // Global Announcement?
                $row['forum_id'] = request_var('f', 0);
            }

            $rowset[$row['topic_id']] = $row;

            if ($acl_list && !$auth->acl_gets($acl_list, $row['forum_id']))
            {
                continue;
            }

            $topics[$row['topic_id']] = $row;
        }
        $db->sql_freeresult($result);
    }

    foreach ($cache_topic_ids as $id)
    {
        if (!$acl_list || $auth->acl_gets($acl_list, $rowset[$id]['forum_id']))
        {
            $topics[$id] = $rowset[$id];
        }
    }

    return $topics;
}
