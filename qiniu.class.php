<?php

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
			list($tids, $membercount, $credit, $ponly) = $arr['param'];
			$count = require DISCUZ_ROOT . 'source/plugin/qiniu/extend/thread_delete.php';
			$_G['deletethreadtids'] = array_pad(array(), $count, null);
		}

	}

	class plugin_qiniu_forum extends plugin_qiniu{

		// 发帖
		public function post(){

			if($_SERVER['REQUEST_METHOD']!='POST' || empty($_POST['typeoption']))
				return;

			global $_G;

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
				if(isset($gory[$k]) && $gory[$k]['url']!=$v['url']){

					if($v['url'] == $gory[$k]['url'].'-'.$_G['cache']['plugin']['qiniu']['thumbnail']){

						$_GET['typeoption'][$k]['url'] = substr($v['url'], 0, -strlen('-'.$_G['cache']['plugin']['qiniu']['thumbnail']));
						$_POST['typeoption'][$k]['url'] = $_GET['typeoption'][$k]['url'];
						$_REQUEST['typeoption'][$k]['url'] = $_GET['typeoption'][$k]['url'];
						$_G['gp_typeoption'][$k]['url'] = $_GET['typeoption'][$k]['url'];

						continue;
					}

					$key = basename($gory[$k]['url']);
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
/*
		// 主题管理
		public function topicadmin(){
			global $_G;
			require DISCUZ_ROOT . 'source/plugin/qiniu/extend/forum_topicadmin.php';
		}
*/
		// 附件
		public function attachment(){
			global $_G;
			require DISCUZ_ROOT . 'source/plugin/qiniu/extend/forum_attachment.php';
		}

		// viewthread_bottom 挂载点
		public function viewthread_bottom_output(){
			global $_G;

			if(!$_G['cache']['plugin']['qiniu']['protect'])
				return;

			global $threadsortshow;
			if($threadsortshow){
				$i = 0;
				$imgs = C::t('forum_attachment_n')->fetch_all('pid:'.$_G['forum_firstpid'], $threadsortshow['sortaids']);
				foreach($threadsortshow['optionlist'] as $k=>$v){
					if($v['type'] != 'image')
						continue;
					$style = $imgs[$i++]['width']<300 ? $_G['cache']['plugin']['qiniu']['thumbnail'] : $_G['cache']['plugin']['qiniu']['default'];
					$threadsortshow['optionlist'][$k]['value'] = preg_replace('/<img src="(.*?)" (.+)>/', '<img src="${1}-'.$style.'" ${2}>', $v['value']);
				}
			}

			global $ignore, $postlist;
			foreach($postlist as $id=>$lv){

				$postlist[$id]['message'] = preg_replace_callback('/(<img id="aimg_(\d+)".*?src=".*?") zoomfile="(.*?)" file="(.*?)" (.*?\/>)/', function($m) use($id){

					global $_G, $ignore, $postlist;

					$style = '-' . ($postlist[$id]['attachments'][$m[2]]['width']<300 ? $_G['cache']['plugin']['qiniu']['thumbnail'] : $_G['cache']['plugin']['qiniu']['default']);

					$ignore[$m[2]] = true;
					$m[3] .= $style; $m[4] .= $style;

					return $m[1] . ' zoomfile="' . $m[3] . '" file="' . $m[4] . '" ' . $m[5];

				}, $lv['message']);

				if(count($ignore) < count($lv['attachments'])){
					foreach($lv['attachments'] as $k => $v){
						if(!$ignore[$k])
							$postlist[$id]['attachments'][$k]['attachment'] .= '-' . ($v['width']<300 ? $_G['cache']['plugin']['qiniu']['thumbnail'] : $_G['cache']['plugin']['qiniu']['default']);
					}
				}

			}

		}

		// 分类信息
		public function post_sortoption_output(){

			global $_G;

			if(!$_G['cache']['plugin']['qiniu']['protect'])
				return;

			$t = &$_G['forum_optionlist'];

			if($t){
				/*
					$i = 0;
					$aids = array_values(array_map(function($v){
						return $v['value']['aid'];
					}, $t));
					$imgs = C::t('forum_attachment_n')->fetch_all('pid:'.intval($_GET['tid']), $aids);
				*/

				foreach($t as $k=>$v){
					if(empty($v['value']))
						continue;
					// $style = $imgs[$i++]['width']<300 ? $_G['cache']['plugin']['qiniu']['thumbnail'] : $_G['cache']['plugin']['qiniu']['default'];
					// $t[$k]['value']['url'] = $v['value']['url'] . '-' . $style;
					$t[$k]['value']['url'] = $v['value']['url'] . '-' . $_G['cache']['plugin']['qiniu']['thumbnail'];
				}

			}

		}

		public function post_top(){
		}

	}

