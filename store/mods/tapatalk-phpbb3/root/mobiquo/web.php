<?php

if(!defined('IN_MOBIQUO')) exit;
include './config/config.php';
$latest_vrsion_link = @file_get_contents('https://api.tapatalk.com/v.php?sys=pb30&link');

if(fileperms('upload.php') < 755)
{
	$upload_acc = 'Inaccessible';
}
else 
{
	$upload_acc = 'OK';
}
if(fileperms('push.php') < 755)
{
	$push_acc = 'Inaccessible';
}
else 
{
	$push_acc = 'OK';
}
echo '<span><b>Forum XMLRPC Interface for Tapatalk Application</b><br>';
echo '<br/>Current Tapatalk plugin version: '.substr($mobiquo_config['version'], 5).'<br>';
echo 'Latest Tapatalk plugin version:<u>'.$latest_vrsion_link.'</u>';
echo '<br>Attachment upload interface status: <a href="upload.php"><u>'.$upload_acc.'</u></a><br>';
echo 'Push notification interface status: <u><a href="push.php">'.$push_acc.'</u></a><br>';
echo '<br/><br/><a href="https://www.tapatalk.com/api/api.php" target="_blank">Tapatalk API for Universal Forum Access</a><br>
    For more details, please visit <a href="https://www.tapatalk.com" target="_blank">https://tapatalk.com</a>';