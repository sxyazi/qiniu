<?php

	if(!defined('IN_DISCUZ')) {
		exit('Access Denied');
	}

	if(!$_G['uid'] || $_POST['hash']!=md5(substr(md5($_G['config']['security']['authkey']), 8).$_G['uid']))
		exit();

	require dirname(__FILE__) . '/lib/php/Autoload.php';

	// 生成KEY
	$auth = new Qiniu\Auth($_G['cache']['plugin']['qiniu']['ak'], $_G['cache']['plugin']['qiniu']['sk']);

	$policy = array(
		'returnBody'	=>	'{"name":$(fname), "size":$(fsize), "hash":$(etag), "type":$(mimeType), "key":$(key), "ext":$(ext), "imageInfo":$(imageInfo)}',
		'returnUrl'	=>	$_G['siteurl'] . 'plugin.php?id=qiniu:handle&maile=' . intval($_POST['maile'])
	);

	echo $auth->uploadToken($_G['cache']['plugin']['qiniu']['bucket'], null, 300, $policy);

