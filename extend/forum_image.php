<?php

/**
 *      [Discuz!] (C)2001-2099 Comsenz Inc.
 *      This is NOT a freeware, use is subject to license terms
 *
 *      $Id: forum_image.php 32531 2013-02-06 10:15:19Z zhangguosheng $
 */

if(!defined('IN_DISCUZ') || empty($_GET['aid']) || empty($_GET['size']) || empty($_GET['key'])) {
	header('Location: '.$_G['siteurl'].'static/image/common/none.gif');
	exit;
}

$daid = intval($_GET['aid']);

$type = !empty($_GET['type']) ? $_GET['type'] : 'fixwr';
$arr = array('fixnone'=>1, 'fixwr'=>2);
$type = $arr[$type] ?: 1;

list($w, $h) = explode('x', $_GET['size']);
$dw = intval($w);
$dh = intval($h);

/*

$thumbfile = 'image/'.helper_attach::makethumbpath($daid, $dw, $dh);$attachurl = helper_attach::attachpreurl();

$thumbfile			image/000/00/00/34_300_300.jpg
$attachurl			http://localhost/discuz/data/attachment/
$_G['setting']['attachdir']	/var/www/html/discuz/./data/attachment/

*/

define('NOROBOT', TRUE);

// 安全检查
$id = !empty($_GET['atid']) ? $_GET['atid'] : $daid;
if(dsign($id.'|'.$dw.'|'.$dh) != $_GET['key']) {
	dheader('Location: '.$_G['siteurl'].'static/image/common/none.gif');
}

// http://localhost/discuz/forum.php?mod=image&aid=36&size=300x300&key=5fa6ba5e4133b53d&nocache=yes&type=fixnone

if($attach = C::t('forum_attachment_n')->fetch('aid:'.$daid, $daid, array(1, -1))) {

	// 没图片
	if(!$dw && !$dh && $attach['tid'] != $id) {
	       dheader('Location: '.$_G['siteurl'].'static/image/common/none.gif');
	}

	dheader('Content-Type: image');
	dheader('Expires: '.gmdate('D, d M Y H:i:s', TIMESTAMP + 3600).' GMT');

	$default = $thumbnail = '';
	if($_G['cache']['plugin']['qiniu']['default'])
		$default = '-' . $_G['cache']['plugin']['qiniu']['default'];
	if($_G['cache']['plugin']['qiniu']['thumbnail'])
		$thumbnail = '-' . $_G['cache']['plugin']['qiniu']['thumbnail'];
	else
		$thumbnail = '?imageView2/' . $type . '/w/' . $w . '/h/' . $h;

	$s = substr($attach['attachment'], ($i=strrpos($attach['attachment'], '-')));
	if($s == $default)
		$attach = substr($attach['attachment'], 0, $i) . $thumbnail;
	elseif($s == '')
		$attach = $attach['attachment'] . $thumbnail;
	else
		$attach = $attach['attachment'];

	dheader('Location: ' . $_G['cache']['plugin']['qiniu']['url'].$attach);

}

