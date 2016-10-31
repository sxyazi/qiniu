<?php

	if(!defined('IN_DISCUZ')) {
		exit('Access Denied');
	}

	$_G['uid'] || exit('Go die...');

	require libfile('function/upload');
	$upcfg = getuploadconfig($_G['uid'], 0, false);

	require template('qiniu:cate_upload');

