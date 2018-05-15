<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function get_raw_post_func($xmlrpc_params)
{
    global $db, $auth, $user, $config, $template, $cache, $phpEx, $phpbb_root_path, $phpbb_home;
    
    $user->setup('posting');
    
    include($phpbb_root_path . 'includes/message_parser.' . $phpEx);

    $params = php_xmlrpc_decode($xmlrpc_params);
    
    // get post id from parameters
    $post_id = intval($params[0]);

    $post_data = array();
    $sql = 'SELECT p.*, t.*, f.*
            FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t, ' . FORUMS_TABLE . " f
            WHERE p.post_id = $post_id
            AND t.topic_id = p.topic_id
            AND (f.forum_id = t.forum_id OR (t.topic_type = " . POST_GLOBAL . ' AND f.forum_type = ' . FORUM_POST . '))';
    
    $result = $db->sql_query_limit($sql, 1);
    $post_data = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    
    if (!$post_data) trigger_error('NO_POST');

    // Use post_row values in favor of submitted ones...
    $forum_id = (int) $post_data['forum_id'];
    $topic_id = (int) $post_data['topic_id'];
    $post_id  = (int) $post_id;
    
    // Need to login to passworded forum first?
    if ($post_data['forum_password'] && !check_forum_password($forum_id))
    {
        trigger_error('LOGIN_FORUM');
    }

    // Is the user able to read within this forum?
    if (!$auth->acl_get('f_read', $forum_id))
    {
        trigger_error('USER_CANNOT_READ');
    }

    // Permission to do the action asked?
    if (!($user->data['is_registered'] && $auth->acl_gets('f_edit', 'm_edit', $forum_id)))
    {
        trigger_error('USER_CANNOT_EDIT');
    }

    // Forum/Topic locked?
    if (($post_data['forum_status'] == ITEM_LOCKED || (isset($post_data['topic_status']) && $post_data['topic_status'] == ITEM_LOCKED)) && !$auth->acl_get('m_edit', $forum_id))
    {
        trigger_error(($post_data['forum_status'] == ITEM_LOCKED) ? 'FORUM_LOCKED' : 'TOPIC_LOCKED');
    }

    // Can we edit this post ... if we're a moderator with rights then always yes
    // else it depends on editing times, lock status and if we're the correct user
    if (!$auth->acl_get('m_edit', $forum_id))
    {
        if ($user->data['user_id'] != $post_data['poster_id'])
        {
            trigger_error('USER_CANNOT_EDIT');
        }
    
        if (!($post_data['post_time'] > time() - ($config['edit_time'] * 60) || !$config['edit_time']))
        {
            trigger_error('CANNOT_EDIT_TIME');
        }
    
        if ($post_data['post_edit_locked'])
        {
            trigger_error('CANNOT_EDIT_POST_LOCKED');
        }
    }

    $message_parser = new parse_message();
    
    if (isset($post_data['post_text']))
    {
        $message_parser->message = &$post_data['post_text'];
        
        unset($post_data['post_text']);
    }

    // Do we want to edit our post ?
    if ($post_data['bbcode_uid'])
    {
        $message_parser->bbcode_uid = $post_data['bbcode_uid'];
    }

    // Decode text for message display
    $message_parser->decode_message($post_data['bbcode_uid']);
    $post_data['post_text'] = $message_parser->message;
    
	// Always check if the submitted attachment data is valid and belongs to the user.
	// Further down (especially in submit_post()) we do not check this again.
	$message_parser->get_submitted_attachment_data($post_data['poster_id']);
	
	if ($post_data['post_attachment'])
	{
		// Do not change to SELECT *
		$sql = 'SELECT attach_id, is_orphan, attach_comment, real_filename
			FROM ' . ATTACHMENTS_TABLE . "
			WHERE post_msg_id = $post_id
				AND in_message = 0
				AND is_orphan = 0
			ORDER BY filetime DESC";
		$result = $db->sql_query($sql);
		$message_parser->attachment_data = array_merge($message_parser->attachment_data, $db->sql_fetchrowset($result));
		$db->sql_freeresult($result);
	}
	// Attachment Preview
	
	if (sizeof($message_parser->attachment_data))
	{
		$update_count = array();
		$attachment_data = $attachments = $message_parser->attachment_data;		
		parse_attachments($forum_id, $post_data['post_text'], $attachment_data, $update_count,true);
		
		foreach($attachment_data as $i => $attachment)
		{
			$attach_id = $attachments[$i]['attach_id'];
			$thumbnail_url = '';
			$new_attachment_data = array();
			$sql = 'SELECT *
				FROM ' . ATTACHMENTS_TABLE . '
				WHERE attach_id = '.$attach_id;
			$result = $db->sql_query($sql);
	
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			$attachments[$i] = $row;
			
			if(preg_match('/<img src=\".*?\/(download\/file\.php\?id=(\d+).*?)\"/is', $attachment, $matches))
			{
				$file_url = basic_clean($phpbb_home.$matches[1]);

				if ($config['img_create_thumbnail'] && $attachments[$i]['thumbnail'])
				{
					$thumbnail_url = preg_replace('/file\.php\?/is', 'file.php?t=1&', $file_url);
				}
				
				unset($matches);
			}
			if (strpos($attachments[$i]['mimetype'], 'image') === 0)
                $content_type = 'image';
            else 
            	$content_type = $attachments[$i]['extension'];
			$xmlrpc_attachment = new xmlrpcval(array(
				'filename'      => new xmlrpcval($attachments[$i]['real_filename'], 'base64'),
				'filesize'      => new xmlrpcval($attachments[$i]['filesize'], 'int'),
				'content_type'  => new xmlrpcval($content_type),
				'thumbnail_url' => new xmlrpcval($thumbnail_url),
				'url'           => new xmlrpcval($file_url),
				'attachment_id' => new xmlrpcval($attach_id),
			), 'struct');
			$attachments_arr[] = $xmlrpc_attachment;
			unset($xmlrpc_attachment);
		}    
	}
	
	$group_id = base64_encode(serialize($message_parser->attachment_data));
    return new xmlrpcresp(
        new xmlrpcval(array(
                'post_id'       => new xmlrpcval($post_id),
                'post_title'    => new xmlrpcval(html_entity_decode(strip_tags($post_data['post_subject'])), 'base64'),
                'post_content'  => new xmlrpcval(html_entity_decode($post_data['post_text']), 'base64'),
            	'attachments'   => new xmlrpcval($attachments_arr, 'array'),
        		'show_reason'   => new xmlrpcval(($auth->acl_get('m_edit', $forum_id) ? true : false),'boolean'),
        		'edit_reason'   => new xmlrpcval($post_data['post_edit_reason'], 'base64'),
        		'group_id'      => new xmlrpcval($group_id),
        	),
            'struct'
        )
    );
}
