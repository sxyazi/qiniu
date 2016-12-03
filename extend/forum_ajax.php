<?php

/**
 *      [Discuz!] (C)2001-2099 Comsenz Inc.
 *      This is NOT a freeware, use is subject to license terms
 *
 *      $Id: forum_ajax.php 34303 2014-01-15 04:32:19Z hypowang $
 */

if(!in_array($_GET['action'], array('deleteattach', 'downremoteimg'))){
	require DISCUZ_ROOT . 'source/module/forum/forum_ajax.php';
}

// 安全检查
if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}
define('NOROBOT', TRUE);

// 引入类库
require_once DISCUZ_ROOT . 'source/plugin/qiniu/lib/qiniu.php';
require_once DISCUZ_ROOT . 'source/plugin/qiniu/lib/attachXML.php';

switch($_GET['action']){

	/**
	 * 删除附件
	 */
	case 'deleteattach':

		$count = 0;
		if($_GET['aids']) {
			foreach($_GET['aids'] as $aid) {
				$attach = C::t('forum_attachment_n')->fetch('aid:'.$aid, $aid);
				if($attach && ($attach['pid'] && $attach['pid'] == $_GET['pid'] && $_G['uid'] == $attach['uid'])) {
					updatecreditbyaction('postattach', $attach['uid'], array(), '', -1, 1, $_G['fid']);
				}
				if($attach && ($attach['pid'] && $attach['pid'] == $_GET['pid'] && $_G['uid'] == $attach['uid'] || $_G['forum']['ismoderator'] || !$attach['pid'] && $_G['uid'] == $attach['uid'])) {
					C::t('forum_attachment_n')->delete('aid:'.$aid, $aid);
					C::t('forum_attachment')->delete($aid);

					if($attach['isimage']){
						$default = $thumbnail = '';
						if($_G['cache']['plugin']['qiniu']['default'])
							$default = $_G['cache']['plugin']['qiniu']['separator'].$_G['cache']['plugin']['qiniu']['default'];
						if($_G['cache']['plugin']['qiniu']['thumbnail'])
							$thumbnail = $_G['cache']['plugin']['qiniu']['separator'].$_G['cache']['plugin']['qiniu']['thumbnail'];
						$s = substr($attach['attachment'], ($i=strrpos($attach['attachment'], $_G['cache']['plugin']['qiniu']['separator'])));
						if($s==$default || $s==$thumbnail)
							$attach['attachment'] = substr($attach['attachment'], 0, $i);
					}

					// 删除记录
					$axml = new maile\attachXML($attach['attachment'], DISCUZ_ROOT.'source/plugin/qiniu/attach/');
					if($axml->find()){
						if($axml->getUses() < 2){
							$axml->del();
							maile\qiniu::unlink($attach['attachment']);
						}else{
							$axml->delUses();
						}
					}else{
						maile\qiniu::unlink($attach['attachment']);
					}

					$count++;
				}
			}
		}

		include template('common/header_ajax');
		echo $count;
		include template('common/footer_ajax');
		dexit();

	break;


	/**
	 * 远程图片下载
	 */
	case 'downremoteimg':

		set_time_limit(0);

		$_GET['message'] = str_replace(array("\r", "\n"), array($_GET['wysiwyg'] ? '<br />' : '', "\\n"), $_GET['message']);
		preg_match_all("/\[img\]\s*([^\[\<\r\n]+?)\s*\[\/img\]|\[img=\d{1,4}[x|\,]\d{1,4}\]\s*([^\[\<\r\n]+?)\s*\[\/img\]/is", $_GET['message'], $image1, PREG_SET_ORDER);
		preg_match_all("/\<img.+src=('|\"|)?(.*)(\\1)([\s].*)?\>/ismUe", $_GET['message'], $image2, PREG_SET_ORDER);
		$temp = $aids = $existentimg = array();
		if(is_array($image1) && !empty($image1)) {
			foreach($image1 as $value) {
				$temp[] = array(
					'0' => $value[0],
					'1' => trim(!empty($value[1]) ? $value[1] : $value[2])
				);
			}
		}
		if(is_array($image2) && !empty($image2)) {
			foreach($image2 as $value) {
				$temp[] = array(
					'0' => $value[0],
					'1' => trim($value[2])
				);
			}
		}

		// 引入类库
		require_once DISCUZ_ROOT.'source/plugin/qiniu/lib/attachXML.php';
		require_once DISCUZ_ROOT.'source/plugin/qiniu/extend/discuz_upload.php';
		// print_r($temp);die;
		// require_once libfile('class/image');
		// var_dump(getglobal('setting/attachurl'));die;
		// print_r(maile\qiniu::getImgInfo('FhSvPvZ216pWlOcwYWq_vNRp1Mzo.jpg'));die;

		if(is_array($temp) && !empty($temp)) {
			$upload = new discuz_upload();
			$attachaids = array();

			foreach($temp as $value) {
				$imageurl = $value[1];

				$hash = md5($imageurl).'.~temp';
				if(strlen($imageurl)) {
					$imagereplace['oldimageurl'][] = $value[0];
					if(!isset($existentimg[$hash])) {

						$existentimg[$hash] = $imageurl;
						$attach['ext'] = $upload->fileext($imageurl);
						if(!$upload->is_image_ext($attach['ext']))
							continue;

						if(!preg_match('/^(http:\/\/|\.)/i', $imageurl) && preg_match('/^('.preg_quote(getglobal('setting/attachurl'), '/').')/i', $imageurl)) {
							$imagereplace['newimageurl'][] = $value[0];
							continue;
						}

						if($res = maile\qiniu::fetch($imageurl, $hash)){
							if(!maile\qiniu::rename($res['key'], $res['hash'].'.'.$attach['ext'])){
								maile\qiniu::unlink($res['key']);
								if(!maile\qiniu::getInfo($res['hash'].'.'.$attach['ext']))
									continue;
							}
						}else{
							continue;
						}

						$attach['size'] = $res['fsize'];
						$attach['name'] = basename($imageurl);
						$attach['thumb'] = '';

						$attach['isimage'] = $upload -> is_image_ext($attach['ext']);
						$attach['extension'] = $upload -> get_target_extension($attach['ext']);
						$attach['attachment'] = $res['hash'].'.'.$attach['ext'];
						// $attach['target'] = getglobal('setting/attachdir').'./forum/'.$attach['attachment'];
						$attach['target'] = getglobal('setting/attachdir').'./'.$attach['attachment'];

						// print_r($attach);
						// print_r($res);
						// die;

						$upload->attach = $attach;
						$thumb = $width = 0;
						if($upload->attach['isimage']){
							if(!($inf=maile\qiniu::getImgInfo($attach['attachment']))){
								maile\qiniu::unlink($res['key']);
								continue;
							}
							$width = $inf['width'];
							if($_G['cache']['plugin']['qiniu']['protect']){
								if($width > 300)
									$style = $_G['cache']['plugin']['qiniu']['separator'].$_G['cache']['plugin']['qiniu']['default'];
								else
									$style = $_G['cache']['plugin']['qiniu']['separator'].$_G['cache']['plugin']['qiniu']['thumbnail'];
							}else{
								if($width > 300)
									$style = ($_G['cache']['plugin']['qiniu']['default'] ? ($_G['cache']['plugin']['qiniu']['separator'].$_G['cache']['plugin']['qiniu']['default']) : '');
								else
									$style = ($_G['cache']['plugin']['qiniu']['thumbnail'] ? ($_G['cache']['plugin']['qiniu']['separator'].$_G['cache']['plugin']['qiniu']['thumbnail']) : '');
							}
						}else{
							maile\qiniu::unlink($res['key']);
							continue;
						}

						// 添加记录
						$axml = new maile\attachXML($upload->attach['attachment'], DISCUZ_ROOT.'source/plugin/qiniu/attach/');
						if($axml->find()){
							if($axml->getUses() < 1)
								$axml->setUses(1);
							else
								$axml->addUses();
						}else{
							$axml->add($upload->attach['attachment']);
						}

						$aids[] = $aid = getattachnewaid();
						$setarr = array(
							'aid' => $aid,
							'dateline' => $_G['timestamp'],
							'filename' => $upload->attach['name'],
							'filesize' => $upload->attach['size'],
							'attachment' => $upload->attach['attachment'].$style,
							'isimage' => $upload->attach['isimage'],
							'uid' => $_G['uid'],
							'thumb' => $thumb,
							'remote' => '0',
							'width' => $width
						);
						C::t("forum_attachment_unused")->insert($setarr);
						$attachaids[$hash] = $imagereplace['newimageurl'][] = '[attachimg]'.$aid.'[/attachimg]';

					} else {
						$imagereplace['newimageurl'][] = $attachaids[$hash];
					}
				}
			}
			if(!empty($aids)) {
				require_once libfile('function/post');
			}
			$_GET['message'] = str_replace($imagereplace['oldimageurl'], $imagereplace['newimageurl'], $_GET['message']);
		}
		$_GET['message'] = addcslashes($_GET['message'], '/"\'');
		print <<<EOF
			<script type="text/javascript">
				parent.ATTACHORIMAGE = 1;
				parent.updateDownImageList('$_GET[message]');
			</script>
EOF;
		dexit();

	break;



}

