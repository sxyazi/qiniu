<?php

	if(!defined('IN_DISCUZ')) {
		exit('Access Denied');
	}

	// require_once libfile('function/post');
	// print_r(getattach(0, 0, 31));die;

	// require_once libfile('function/attachment');
	// echo getattachexif(0, DISCUZ_ROOT . 'data/attachment/forum/2.png');die;

	(empty($_GET['upload_ret']) || empty($_SERVER['HTTP_REFERER'])) && exit();

	// 载入类库
	require_once DISCUZ_ROOT . 'source/plugin/qiniu/lib/qiniu.php';
	require_once DISCUZ_ROOT . 'source/plugin/qiniu/lib/attachXML.php';

	// 安全检查
	$call = $_G['siteurl'] . 'plugin.php?id=qiniu:handle&maile=' . intval($_GET['maile']);
	$verify = maile\qiniu::$auth->verifyCallback('application/x-www-form-urlencoded', $_SERVER['HTTP_AUTHORIZATION'], $call, file_get_contents('php://input'));
	$verify || exit();

	// 获取fid
	$url = parse_url($_SERVER['HTTP_REFERER']);
	parse_str($url['query'], $url);
	empty($url['fid']) && exit();

	// {"name":"test.png", "size":1418, "hash":"FkRsIOKizjRdSb9lqUs9ri7AbDjv", "type":"image/png", "key":"FkRsIOKizjRdSb9lqUs9ri7AbDjv", "ext":".png", "imageInfo":{"colorModel":"nrgba","format":"png","height":52,"width":56}}

	// 伪造值
	$_GET['fid'] = $url['fid'];
	$_FILES['Filedata'] = array(
		'name'		=>	$result['name'],
		'type'		=>	$result['ext'],
		'sha1'		=>	$result['hash'],
		'tmp_name'	=>	$result['key'],
		'error'		=>	0,
		'size'		=>	$result['size'],
		'imageInfo'	=>	$result['imageInfo']
	);
	$_GET['type'] = 'image';
	$_GET['operation'] = 'upload';
	if($_GET['maile'] == 1){
		unset($_GET['type']);
	}elseif($_GET['maile'] == 2){
		$_GET['simple'] = 2;
	}

	// 组织JSON
	ob_start();
	require DISCUZ_ROOT . 'source/plugin/qiniu/extend/upload_handle.php';
	$id = ob_get_clean();

	if($_GET['maile'] == 2){
		$str = explode('|', $id);
		if(!is_numeric($str[3]) || $str[3]<1)
			maile\qiniu::unlink($result['key']);
		echo $id;
		return;
	}

	if(!is_numeric($id) || $id<1)
		maile\qiniu::unlink($result['key']);

	echo json_encode(array(
		'id'	=>	$id,
		'name'	=>	$result['name'],
	));

