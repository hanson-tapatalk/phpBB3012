<?php

define('MBQ_PUSH_BLOCK_TIME', 60);    /* push block time(minutes) */
if(!class_exists('TapatalkBasePush'))
{
    require_once TT_ROOT . 'push/TapatalkBasePush.php';
}

if(!function_exists("post_bbcode_clean") && file_exists(TT_ROOT . "mobiquo_common.php"))
{
    require_once TT_ROOT . "mobiquo_common.php";    
}

/**
 * push class

 */
Class TapatalkPush extends TapatalkBasePush {
    
    //init
    public function __construct($push_key, $site_url) 
    {
        $this->pushKey = $push_key;
        $this->siteUrl = $site_url;
    
        parent::__construct($this);
    }
    
    function get_push_slug()
    {
        global $config;
        return $config['tapatalk_push_slug'];
    }
    
    function set_push_slug($slug)
    {
        set_config('tapatalk_push_slug', $slug);
        return true;
    }
    
    function doAfterAppLogin($userId)
    {
        global $config, $db;
        //add tapatalk_users here,for push service
        if(push_table_exists())
        {
            global $table_prefix;
            $sql = "SELECT * FROM " . $table_prefix . "tapatalk_users where userid = '".$userId."'";
            $result = $db->sql_query($sql);
            $userInfo = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);
            if(empty($userInfo))
            {
                $sql_data[$table_prefix . "tapatalk_users"]['sql'] = array(
                    'userid' => $userId,
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
    }
    
    public function doPushPm($data, $recipients)
    {
        global $table_prefix, $db;
        
        foreach($recipients as $key => $value)
        {
            if($this->isIgnoreUser($key)) continue;
            $recipients[] = $key;
        }
        
        if(sizeof($recipients))
        {
            $sql = "SELECT userid FROM " . $table_prefix . "tapatalk_users WHERE " . $db->sql_in_set('userid',$recipients) ." and pm =1";
            $result = $db->sql_query($sql);
            $ttrecipients = array();
            while($row = $db->sql_fetchrow($result))
            {
                $ttrecipients[] = $row['userid'];
            }
            $db->sql_freeresult($result);
            
            $ttp_data = array(
                'id'        => $data['msg_id'],
                'title'     => self::push_clean($data['message_subject']),
                'content'   => $data['message']
            );
            
            $this->push($ttp_data, $ttrecipients, 'pm', $data);
            
        }
        
    }
    
    public function doPushPost($data)
    {
        global $table_prefix, $db;
           
        $sql = "SELECT w.user_id FROM " . FORUMS_WATCH_TABLE . " w JOIN " . $table_prefix . "tapatalk_users ttu ON ttu.userid = w.user_id WHERE w.forum_id=" . $data['forum_id'];
        $result = $db->sql_query($sql);
        $subscribedUsers = array();
        while($row = $db->sql_fetchrow($result))
        {
            if($this->isIgnoreUser($row['user_id'])) continue;
            
            $subscribedUsers[] = $row['user_id'];
        }
        
        $db->sql_freeresult($result);
            
        $ttp_data = array(                
            'id'             => $data['topic_id'],
            'subid'          => $data['post_id'],
            'subfid'         => $data['forum_id'],
            'sub_forum_name' => self::push_clean($data['forum_name']),
            'title'          => self::push_clean($data['topic_title']),
            'content'        => $data['message'],
        );
            
        $this->push($ttp_data, $subscribedUsers, 'newtopic', $data);
        
    }
    
    public function doPushReply($data)
    {
        global $table_prefix, $db;
                                  
        $sql = "SELECT w.user_id FROM " . TOPICS_WATCH_TABLE . " w JOIN " . $table_prefix . "tapatalk_users ttu ON ttu.userid = w.user_id WHERE w.topic_id=" . $data['topic_id'];
            
        $result = $db->sql_query($sql);
        $subscribedTopicUsers = array();
        while($row = $db->sql_fetchrow($result))
        {
            if($this->isIgnoreUser($row['user_id'])) continue;
                
            $subscribedTopicUsers[] = $row['user_id'];           
        }

        $db->sql_freeresult($result);
        
        $ttp_data = array(               
            'id'             => $data['topic_id'],
            'subid'          => $data['post_id'],
            'subfid'         => $data['forum_id'],
            'sub_forum_name' => self::push_clean($data['forum_name']),
            'title'          => self::push_clean($data['topic_title']),
            'content'        => $data['message'],
        );          
      
        $this->push($ttp_data, $subscribedTopicUsers, 'sub', $data);        
    }
    
    public function doPushQuote($data)
    {
        global $table_prefix, $db;
  
        preg_match_all('/quote=&quot;(.*?)&quot;/is', $data['message'],$matches);
        $user_name_arr = array_unique($matches[1]);
        if(empty($user_name_arr)) return false;                       
        $sql = "SELECT w.user_id FROM " . USERS_TABLE . " w JOIN " . $table_prefix . "tapatalk_users ttu ON ttu.userid = w.user_id WHERE " . $db->sql_in_set('w.username',$user_name_arr);
        $result = $db->sql_query($sql);
        $quotedUsers = array();
        while($row = $db->sql_fetchrow($result))
        {
            if($this->isIgnoreUser($row['user_id'])) continue;
            $quotedUsers[] = $row['user_id'];
        }
        $db->sql_freeresult($result);
        
        $ttp_data = array(               
            'id'             => $data['topic_id'],
            'subid'          => $data['post_id'],
            'subfid'         => $data['forum_id'],
            'sub_forum_name' => self::push_clean($data['forum_name']),
            'title'          => self::push_clean($data['topic_title']),
            'content'        => $data['message'],
        );   
        
        $this->push($ttp_data, $quotedUsers, 'quote', $data);                   
    }
    
    public function doPushSubTopic($data)
    {
        global $table_prefix, $db;
        try
        {
            if(isset($data['topic_poster']) && !empty($data['topic_poster']))
            {
                $sql = "SELECT ttu.userid FROM " . $table_prefix . "tapatalk_users ttu WHERE ttu.userid =" . $data['topic_poster'];
                $result = $db->sql_query($sql);
                $pushUsers = array();
                while($row = $db->sql_fetchrow($result))
                {
                    if($this->isIgnoreUser($row['userid'])) continue;
                    $pushUsers[] = $row['userid'];
                }
                $db->sql_freeresult($result);
                
                $ttp_data = array(               
                    'id'             => $data['topic_id'],
                    'subid'          => $data['topic_first_post_id'],
                    'subfid'         => $data['forum_id'],
                    'sub_forum_name' => self::push_clean($data['forum_name']),
                    'title'          => self::push_clean($data['topic_title']),
                    'content'        => '',
                );   
                
                $this->push($ttp_data, $pushUsers, 'newsub', $data);                   
            }
        }
        catch(Exception $ex)
        {}
    }

    public function isIgnoreUser($uid)
    {
        global $user;   
                
        if ($uid == $user->data['user_id']) return true;
        
        if(defined("TAPATALK_PUSH" . $uid))
        {
            return true;
        }
        
        define("TAPATALK_PUSH" . $uid, 1);
        
        return false;   
    }
    
    public function doPushTag($data)
    {
        global $user, $config, $table_prefix, $db, $auth;
           
        $user_name_arr = $this->getTagList($data['message']);    
        if(empty($user_name_arr)) return false;                  
        $sql = "SELECT w.user_id FROM " . USERS_TABLE . " w JOIN " . $table_prefix . "tapatalk_users ttu ON ttu.userid = w.user_id WHERE " . $db->sql_in_set('w.username',$user_name_arr);
        $result = $db->sql_query($sql);
        $quotedUsers = array();
        while($row = $db->sql_fetchrow($result))
        {
            if($this->isIgnoreUser($row['user_id'])) continue;
            
            $quotedUsers[] = $row['user_id'];
        }
        $db->sql_freeresult($result);
        
        $auth_read = $auth->acl_raw_data($quotedUsers, 'f_read', $data['forum_id']);
        
        if (empty($auth_read))
		{
			return false;
		}
		
		 $allowedQuotedUsers = array();
		
		foreach($auth_read as $user_id => $perm)
		{
			if($perm[$data['forum_id']]['f_read'] == ACL_YES)
			{
				$allowedQuotedUsers[] = $user_id;
			}
		}
		
		if (empty($allowedQuotedUsers))
		{
			return false;
		}
        
        $ttp_data = array(               
            'id'             => $data['topic_id'],
            'subid'          => $data['post_id'],
            'subfid'         => $data['forum_id'],
            'sub_forum_name' => self::push_clean($data['forum_name']),
            'title'          => self::push_clean($data['topic_title']),
            'content'        => $data['message'],
        );   
        
        $this->push($ttp_data, $quotedUsers, 'tag', $data);                   
    }
    
    public function convertContent($data)
    {
        global $user,$config,$phpbb_root_path,$phpEx;
        
        // Define the global bbcode bitfield, will be used to load bbcodes
        $bbcode_bitfield = '';
        $bbcode_bitfield = $bbcode_bitfield | base64_decode($data['bbcode_bitfield']);
        $bbcode = '';
        // Is a signature attached? Are we going to display it?
        if ($data['enable_sig'] && $config['allow_sig'] && $user->optionget('viewsigs') && isset($data['user_sig_bbcode_bitfield']))
        {
            $bbcode_bitfield = $bbcode_bitfield | base64_decode($data['user_sig_bbcode_bitfield']);
        }
        if ($bbcode_bitfield !== '')
        {
            $bbcode = new bbcode(base64_encode($bbcode_bitfield));
        }
        
        // Parse the message and subject       
        $message = censor_text($data['message']);            
        
        $message = process_bbcode($message, $data['bbcode_uid']);
        
        // Second parse bbcode here
        if ($data['bbcode_bitfield'] && $bbcode)
        {
            if(!class_exists("bbcode"))
            {
                include_once($phpbb_root_path . 'includes/bbcode.' . $phpEx);
            }
            $bbcode->bbcode_second_pass($message, $data['bbcode_uid'], $data['bbcode_bitfield']);
        }
    
        $message = bbcode_nl2br($message);
        $message = smiley_text($message);
        $message = post_html_clean($message);
        return $message;
    }
    
    public function push($data, $push_user, $type, $origin_data=array())
    {
        global $user, $config;
                        
        if(empty($this->pushKey)) return false;
        
        $data['type']        = $type;
        $data['key']         = $this->pushKey;
        $data['url']         = $this->siteUrl;
        $data['dateline']    = time();
        $data['author_ua']   = self::getClienUserAgent();
        $data['author_type'] = check_return_user_type($user->data['user_id'], false);
        $data['from_app']    = self::getIsFromApp();
        $data['authorid']    = $user->data['user_id'];
        $data['author']      = $user->data['username'];
         
        if(!empty($data['content']) && !empty($config['tapatalk_push_type']))
        {
            $data['content'] = self::convertContent($origin_data);
        }
        else if(isset($data['content']))
        {
            unset($data['content']);
        }

        if(!empty($push_user))
        {
            $data['userid'] = implode(',', $push_user);
            $data['push'] = 1;
        }
        else
        {
            $data['push'] = 0;
        }
        
        self::do_push_request($data);       
    }
    
}
