<?php
defined('IN_MOBIQUO') or exit;
include_once($phpbb_root_path . 'includes/functions_profile_fields.' . $phpEx);
function prefetch_account_func()
{
	global $db, $user;
	$user_row = tt_get_user_by_email(trim($_POST['email']));
	if(empty($user_row['user_id']))
	{
		$result_xmlrpc = array(
			'result'            => new xmlrpcval(false, 'boolean'),
			'result_text'       => new xmlrpcval('Can\'t find the user', 'base64'),
		);
	}
	else 
	{
		$result_xmlrpc = array(
			'result'            => new xmlrpcval(true, 'boolean'),
			'result_text'       => new xmlrpcval('', 'base64'),
			'user_id'           => new xmlrpcval($user_row['user_id'], 'string'),
			'login_name'        => new xmlrpcval(basic_clean($user_row['username']), 'base64'),
			'display_name'      => new xmlrpcval(basic_clean($user_row['username']), 'base64'),
			'avatar'            => new xmlrpcval(get_user_avatar_url($user_row['user_avatar'], $user_row['user_avatar_type']), 'string'),
		);
	}
	$lang_id = $user->get_iso_lang_id();
	$sql = 'SELECT l.*, f.*
	FROM ' . PROFILE_LANG_TABLE . ' l, ' . PROFILE_FIELDS_TABLE . " f
	WHERE f.field_active = 1
		AND f.field_show_on_reg = 1
		AND l.lang_id = $lang_id
		AND l.field_id = f.field_id
		AND f.field_required = 1
	ORDER BY f.field_order";
	$result = $db->sql_query($sql);
	$cp = new custom_profile();
	while ($row = $db->sql_fetchrow($result))
	{
		$type = (int) $row['field_type'];
		$field_id = (int) $row['field_id'];
		$custom_field_data = array(
			'name'          => new xmlrpcval(basic_clean($row['lang_name']), 'base64'),
	        'description'   => new xmlrpcval(basic_clean($row['lang_explain']), 'base64'),
			'key'           => new xmlrpcval('pf_' . $row['field_ident']),
			'type'          => new xmlrpcval('input'),
			'default'       => new xmlrpcval($row['field_default_value'], 'base64'),
	    );
	    
	    if($type === FIELD_DROPDOWN)
	    {
	    	$custom_field_data['type'] = new xmlrpcval('drop');
	    }
	    if($type === FIELD_BOOL)	 
	    {
	    	if($row['field_length'] == 1)
	    		$custom_field_data['type'] = new xmlrpcval('radio');
	    	else 
	    		$custom_field_data['type'] = new xmlrpcval('cbox');
	    }   
	    if($type  === FIELD_TEXT)
	    {
	    	$custom_field_data ['type'] = new xmlrpcval('textarea');
	    }  
	    if($type === FIELD_DATE) 
	    {
	    	$custom_field_data['key'] = new xmlrpcval('date_pf_' . $row['field_ident']);
	    	$default_value = explode('-', $row['field_default_value']);
	    	$default_value[0] = (int) $default_value[0];
	    	if(empty($default_value[0]))
	    	{
	    		$custom_field_data['default'] =  new xmlrpcval(date("Y-m-d",time()), 'base64');
	    	}
	    	$custom_field_data['format'] = new xmlrpcval("nnnn-nn-nn");
	    }       
	    $cp->get_option_lang($field_id, $lang_id, $type, false);          
	    $options = isset($cp->options_lang[$field_id][$lang_id]) ? $cp->options_lang[$field_id][$lang_id] : array();
	    $option_str = '';
		foreach ($options as $key => $value)
		{
			if($key == count($options)) $option_str .= $key."=".$value;	
		    else $option_str .= $key."=".$value."|";		    	
		}
		if(!empty($option_str)) $custom_field_data['options'] = new xmlrpcval($option_str, 'base64');	
        $required_custom_fields[] = new xmlrpcval($custom_field_data, 'struct');
		
	}

	$db->sql_freeresult($result);
	$result_xmlrpc['custom_register_fields'] = new xmlrpcval($required_custom_fields, 'array');

	return new xmlrpcresp(new xmlrpcval($result_xmlrpc, 'struct'));
}
