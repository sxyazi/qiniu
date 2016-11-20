<?php

	if(!defined('IN_DISCUZ')) {
		exit('Access Denied');
	}

	if(!$_G['uid'] || $_POST['hash']!=md5(substr(md5($_G['config']['security']['authkey']), 8).$_G['uid']))
		exit();

	require dirname(__FILE__) . '/lib/php/Autoload.php';

	// 安全码
	$mltk = time().mt_rand();

	// 生成KEY
	$auth = new Qiniu\Auth($_G['cache']['plugin']['qiniu']['ak'], $_G['cache']['plugin']['qiniu']['sk']);

	$policy = array(
		'returnBody'	=>	'{"name":$(fname), "size":$(fsize), "hash":$(etag), "type":$(mimeType), "key":$(key), "ext":$(ext), "imageInfo":$(imageInfo)}',
		'returnUrl'	=>	$_G['siteurl'] . 'plugin.php?id=qiniu:handle&mltk=' . $mltk . '&maile=' . intval($_POST['maile'])
	);

	// 设置Cookie
	dsetcookie('maile_upload_token', authcode(md5($mltk), 'ENCODE'), 300, -1, true);

	echo $auth->uploadToken($_G['cache']['plugin']['qiniu']['bucket'], null, 300, $policy);

