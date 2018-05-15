<?php
if(!defined('IN_PHPBB')) exit;
// display emoji from app
if(!defined('IN_MOBIQUO'))
{
	$protocol = ($config['cookie_secure'])  ? 'https' : 'http';
	$message = preg_replace('/\[emoji(\d+)\]/i', '<img src="'.$protocol.'://emoji.tapatalk-cdn.com/emoji\1.png" />', $message);
}
