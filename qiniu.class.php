<?php

	if(!defined('IN_DISCUZ')) {
		exit('Access Denied');
	}

	class plugin_qiniu{

		public function post_upload_extend(){
			global $_G;

			// 获取配置
			require_once libfile('function/upload');
			$swfconfig = getuploadconfig($_G['uid'], $_G['fid']);
			$imgexts = str_replace(array(';', '*.'), array(', ', ''), $swfconfig['imageexts']['ext']);
			$allowpostimg = $_G['group']['allowpostimage'] && $imgexts;

			// 加载模板
			include template('qiniu:forum_upload');
			return $content;
		}

		// 删除主题
		public function deletethread($arr){
			if($arr['step'] != 'check')
				return;
			global $_G;
			set_time_limit(0);
			list($tids, $membercount, $credit, $ponly) = $arr['param'];
			$count = require DISCUZ_ROOT . 'source/plugin/qiniu/extend/thread_delete.php';
			$_G['deletethreadtids'] = array_pad(array(), $count, null);
		}

		// 全局
		public function common(){
			global $_G;
			$_G['setting']['rewritestatus'] = $_G['setting']['rewritestatus'] ?: array();
			$_G['setting']['output']['preg']['search'] = $_G['setting']['output']['preg']['search'] ?: array();
			$_G['setting']['output']['preg']['replace'] = $_G['setting']['output']['preg']['replace'] ?: array();

			$_G['setting']['rewritestatus'][] = 'qiniu';
			$_G['setting']['output']['preg']['search'][] = '#data/attachment/forum/([a-zA-Z0-9_\-\.]{20,})#';
			$_G['setting']['output']['preg']['replace'][] = $_G['cache']['plugin']['qiniu']['url'] . '$1';
		}

	}

	class plugin_qiniu_forum extends plugin_qiniu{

		// 发帖
		public function post(){

			global $_G;
			$_G['forum']['disablewatermark'] = true;

			if($_SERVER['REQUEST_METHOD']!='POST' || empty($_POST['typeoption']))
				return;

			$cate = C::t('forum_typeoptionvar')->fetch_all_by_tid_optionid($_G['tid']);
			if(!$cate)
				return;

			$sortid = $_G['thread']['sortid'];
			loadcache(array('threadsort_option_'.$sortid, 'threadsort_template_'.$sortid));
			$option = $_G['cache']['threadsort_option_'.$sortid];

			$gory = array();
			foreach($cate as $v){
				$gory[$option[$v['optionid']]['identifier']] = dunserialize($v['value']);
			}

			require_once DISCUZ_ROOT . 'source/plugin/qiniu/lib/qiniu.php';
			require_once DISCUZ_ROOT . 'source/plugin/qiniu/lib/attachXML.php';
			foreach($_POST['typeoption'] as $k=>$v){

				if(empty($gory[$k]))
					continue;

				// 去除样式
				$default = $thumbnail = '';
				if($_G['cache']['plugin']['qiniu']['default'])
					$default = $_G['cache']['plugin']['qiniu']['separator'].$_G['cache']['plugin']['qiniu']['default'];
				if($_G['cache']['plugin']['qiniu']['thumbnail'])
					$thumbnail = $_G['cache']['plugin']['qiniu']['separator'].$_G['cache']['plugin']['qiniu']['thumbnail'];

				$s = substr($v['url'], ($i=strrpos($v['url'], $_G['cache']['plugin']['qiniu']['separator'])));
				if($s==$default || $s==$thumbnail)
					$new = substr($v['url'], 0, $i);
				elseif(($i=strrpos($v['url'], '?imageView2/')))
					$new = substr($v['url'], 0, $i);
				else
					$new = $v['url'];

				$s = substr($gory[$k]['url'], ($i=strrpos($gory[$k]['url'], $_G['cache']['plugin']['qiniu']['separator'])));
				if($s==$default || $s==$thumbnail)
					$old = substr($gory[$k]['url'], 0, $i);
				elseif(($i=strrpos($gory[$k]['url'], '?imageView2/')))
					$old = substr($gory[$k]['url'], 0, $i);
				else
					$old = $gory[$k]['url'];

				if($old != $new){

					$key = basename($old);
					$axml = new maile\attachXML($key, DISCUZ_ROOT.'source/plugin/qiniu/attach/');
					if($axml->find()){
						if($axml->getUses() > 1){
							$axml->delUses();
						}else{
							$axml->del();
							maile\qiniu::unlink($key);
						}
					}else{
						maile\qiniu::unlink($key);
					}

				}

			}

		}

		// 缩略图
		public function image(){
			global $_G;
			require DISCUZ_ROOT . 'source/plugin/qiniu/extend/forum_image.php';
		}

		// Ajax
		public function ajax(){
			global $_G;
			require DISCUZ_ROOT . 'source/plugin/qiniu/extend/forum_ajax.php';
		}

		// 附件
		public function attachment(){
			global $_G;
			require DISCUZ_ROOT . 'source/plugin/qiniu/extend/forum_attachment.php';
		}

	}

