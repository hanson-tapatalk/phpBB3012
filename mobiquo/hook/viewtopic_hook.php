<?php
if(!defined('IN_PHPBB')) exit;
//add tapatalk thumbnail
/*$message = preg_replace_callback('/(\[img(.*?)\])(http&#58;\/\/img&#46;tapatalk&#46;com\/d\/[0-9]{2}\/[0-9]{2}\/[0-9]{2})(.*?)(\[\/img\2\])/i',
    create_function(
	'$matches',
	'
		if(!strstr($matches[4],\'/thumbnail\'))
		{
			return \'<a target="_blank" href="http&#58;//tapatalk&#46;com/tapatalk_image&#46;php?img=\'.base64_encode($matches[3].\'/original\'.$matches[4]).\'">\'.$matches[1].$matches[3].\'/thumbnail\'.$matches[4].$matches[5].\'</a>\';
		}
		else
		{
			return $matches[0];
		}
	'
    ),
$message);*/
$message = preg_replace("#\[url='([^']+)'\]([^\[]+)\[\/url]#si", "<a href=\"$1\" class=\"postlink\">$2</a>",$message);

$message = preg_replace('#<a [^>]*?href="https?://(www\.)?vimeo\.com/(\d+)"[^>]*?>[^>]*?</a>#si',
'<iframe src="https://player.vimeo.com/video/$2" width="500" height="300" frameborder="0"></iframe>', $message);
// display emoji from app
$protocol = ($config['cookie_secure'])  ? 'https' : 'http';
$message = preg_replace('/\[emoji(\d+)\]/i', '<img src="'.$protocol.'://emoji.tapatalk-cdn.com/emoji\1.png" />', $message);

