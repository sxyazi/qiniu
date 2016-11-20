<?php

/**
 *      [Discuz!] (C)2001-2099 Comsenz Inc.
 *      This is NOT a freeware, use is subject to license terms
 *
 *      $Id: discuz_upload.php 34648 2014-06-18 02:53:07Z hypowang $
 */

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

class discuz_upload{

	var $attach = array();
	var $type = '';
	var $extid = 0;
	var $errorcode = 0;
	var $forcename = '';

	public function __construct() {

	}

	function init($attach, $type = 'temp', $extid = 0, $forcename = '') {

		if(!is_array($attach) || empty($attach) || !$this->is_upload_file($attach['tmp_name']) || trim($attach['name']) == '' || $attach['size'] == 0) {
			$this->attach = array();
			$this->errorcode = -1;
			return false;
		} else {
			$this->type = $this->check_dir_type($type);
			$this->extid = intval($extid);
			$this->forcename = $forcename;

			$attach['size'] = intval($attach['size']);
			$attach['name'] =  trim($attach['name']);
			$attach['thumb'] = '';
			$attach['ext'] = $this->fileext($attach['name']);

			$attach['name'] =  dhtmlspecialchars($attach['name'], ENT_QUOTES);
			if(strlen($attach['name']) > 90) {
				$attach['name'] = cutstr($attach['name'], 80, '').'.'.$attach['ext'];
			}

			$attach['maile'] = $attach['sha1'] . '.' . $attach['ext'];
			$attach['isimage'] = $this->is_image_ext($attach['ext']);
			$attach['extension'] = $this->get_target_extension($attach['ext']);

			if($attach['isimage']){
				global $_G;
				if($_G['cache']['plugin']['qiniu']['protect']){
					if($attach['imageInfo']['width'] > 300)
						$attach['attachment'] = $attach['maile'].$_G['cache']['plugin']['qiniu']['separator'].$_G['cache']['plugin']['qiniu']['default'];
					else
						$attach['attachment'] = $attach['maile'].$_G['cache']['plugin']['qiniu']['separator'].$_G['cache']['plugin']['qiniu']['thumbnail'];
				}else{
					if($attach['imageInfo']['width'] > 300)
						$attach['attachment'] = $attach['maile'].($_G['cache']['plugin']['qiniu']['default'] ? ($_G['cache']['plugin']['qiniu']['separator'].$_G['cache']['plugin']['qiniu']['default']) : '');
					else
						$attach['attachment'] = $attach['maile'].($_G['cache']['plugin']['qiniu']['thumbnail'] ? ($_G['cache']['plugin']['qiniu']['separator'].$_G['cache']['plugin']['qiniu']['thumbnail']) : '');
				}
			}else{
				$attach['attachment'] = $attach['maile'];
			}

			$attach['target'] = getglobal('setting/attachdir').'./'.$this->type.'/'.$attach['maile'];

			$this->attach = & $attach;
			$this->errorcode = 0;
			return true;
		}

	}

	function save($ignore = 0) {
		if($ignore) {
			if(!$this->save_to_local($this->attach['tmp_name'], $this->attach['maile'])) {
				$this->errorcode = -103;
				return false;
			} else {
				$this->errorcode = 0;
				return true;
			}
		}

		if(empty($this->attach) || empty($this->attach['tmp_name']) || empty($this->attach['target'])) {
			$this->errorcode = -101;
		} elseif(in_array($this->type, array('group', 'album', 'category')) && !$this->attach['isimage']) {
			$this->errorcode = -102;
		} elseif(in_array($this->type, array('common')) && (!$this->attach['isimage'] && $this->attach['ext'] != 'ext')) {
			$this->errorcode = -102;
		} elseif(!$this->save_to_local($this->attach['tmp_name'], $this->attach['maile'])) {
			$this->errorcode = -103;
		} elseif(($this->attach['isimage'] || $this->attach['ext'] == 'swf') && (!$this->attach['imageInfo'] = $this->get_image_info($this->attach['target']))) {
			$this->errorcode = -104;
			maile\qiniu::unlink($this->attach['tmp_name']);
		} else {
			$this->errorcode = 0;
			return true;
		}

		return false;
	}

	function error() {
		return $this->errorcode;
	}

	function errormessage() {
		return lang('error', 'file_upload_error_'.$this->errorcode);
	}

	function fileext($filename) {
		return addslashes(strtolower(substr(strrchr($filename, '.'), 1, 10)));
	}

	function is_image_ext($ext) {
		static $imgext  = array('jpg', 'jpeg', 'gif', 'png', 'bmp');
		return in_array($ext, $imgext) ? 1 : 0;
	}

	function get_image_info($target) {
		$ext = discuz_upload::fileext($target);
		$isimage = discuz_upload::is_image_ext($ext);
		if(!$isimage && ($ext != 'swf' || !$allowswf)) {
			return false;
		}elseif($info = $this->attach['imageInfo']){
			$size = $info['width'] * $info['height'];
			if($size > 16777216 || $size < 16 ) {
				return false;
			}elseif($isimage && !in_array($info['format'], array('jpg','jpeg','png','gif','bmp'))) {
				return false;
			} elseif(!$allowswf && ($ext == 'swf' || $info['format']=='swf')){
				return false;
			}
			return $info;
		} else {
			return false;
		}
	}

	function is_upload_file($source) {
		return $source && ($source != 'none');
	}

	function get_target_extension($ext) {
		static $safeext  = array('attach', 'jpg', 'jpeg', 'gif', 'png', 'swf', 'bmp', 'txt', 'zip', 'rar', 'mp3');
		return strtolower(!in_array(strtolower($ext), $safeext) ? 'attach' : $ext);
	}

	function check_dir_type($type) {
		return !in_array($type, array('forum', 'group', 'album', 'portal', 'common', 'temp', 'category', 'profile')) ? 'temp' : $type;
	}

	function save_to_local($source, $target) {
		if(!discuz_upload::is_upload_file($source)) {
			return false;
		}
		if($source != $target){
			if(!maile\qiniu::rename($source, $target)){
				if(maile\qiniu::getInfo($target))
					maile\qiniu::unlink($source);
				else
					return false;
			}
		}

		// 添加记录
		$axml = new maile\attachXML($target, DISCUZ_ROOT.'source/plugin/qiniu/attach/');
		if($axml->find()){
			if($axml->getUses() < 1)
				$axml->setUses(1);
			else
				$axml->addUses();
		}else{
			$axml->add($target);
		}

		$this->errorcode = 0;
		return true;
	}

}

?>