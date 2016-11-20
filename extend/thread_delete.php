<?php

	require_once DISCUZ_ROOT . 'source/plugin/qiniu/lib/qiniu.php';
	require_once DISCUZ_ROOT . 'source/plugin/qiniu/lib/attachXML.php';

	if(!function_exists('maile_unlink')){
		function maile_unlink($attach){
			global $_G;

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

			$attachXML = new maile\attachXML($attach['attachment'], DISCUZ_ROOT.'source/plugin/qiniu/attach/');
			$attachXML->find();

			if(!$attachXML->get()){
				maile\qiniu::unlink($attach['attachment']);
				return;
			}

			if($attachXML->getUses() > 1){
				$attachXML->delUses();
			}else{
				$attachXML->del();
				maile\qiniu::unlink($attach['attachment']);
			}
		}
	}

	if(!function_exists('maile_deleteattach')){
		function maile_deleteattach($ids, $idtype = 'aid') {
			global $_G;
			if(!$ids || !in_array($idtype, array('authorid', 'uid', 'tid', 'pid'))) {
				return;
			}
			$idtype = $idtype == 'authorid' ? 'uid' : $idtype;

			$pics = $attachtables = array();

			if($idtype == 'tid') {
				$pollImags = C::t('forum_polloption_image')->fetch_all_by_tid($ids);
				foreach($pollImags as $image) {
					maile_unlink($image);
				}
			}
			foreach(C::t('forum_attachment')->fetch_all_by_id($idtype, $ids) as $attach) {
				$attachtables[$attach['tableid']][] = $attach['aid'];
			}

			foreach($attachtables as $attachtable => $aids) {
				if($attachtable == 127) {
					continue;
				}
				$attachs = C::t('forum_attachment_n')->fetch_all($attachtable, $aids);
				foreach($attachs as $attach) {
					if($attach['picid']) {
						$pics[] = $attach['picid'];
					}
					maile_unlink($attach);
				}
				C::t('forum_attachment_exif')->delete($aids);
				C::t('forum_attachment_n')->delete($attachtable, $aids);
			}
			C::t('forum_attachment')->delete_by_id($idtype, $ids);
			if($pics) {
				$albumids = array();
				C::t('home_pic')->delete($pics);
				$query = C::t('home_pic')->fetch_all($pics);
				foreach($query as $album) {
					if(!in_array($album['albumid'], $albumids)) {
						C::t('home_album')->update($album['albumid'], array('picnum' => C::t('home_pic')->check_albumpic($album['albumid'])));
						$albumids[] = $album['albumid'];
					}
				}
			}
		}
	}

	if(!function_exists('maile_deletepost')){
		function maile_deletepost($ids, $idtype = 'pid', $credit = false, $posttableid = false, $recycle = false) {
			global $_G;
			$recycle = $recycle && $idtype == 'pid' ? true : false;
			if(!$ids || !in_array($idtype, array('authorid', 'tid', 'pid'))) {
				return 0;
			}

			loadcache('posttableids');
			$posttableids = !empty($_G['cache']['posttableids']) ? ($posttableid !== false && in_array($posttableid, $_G['cache']['posttableids']) ? array($posttableid) : $_G['cache']['posttableids']): array('0');

			$count = count($ids);
			$idsstr = dimplode($ids);

			if($credit) {
				$tuidarray = $ruidarray = array();
				foreach($posttableids as $id) {
					$postlist = array();
					if($idtype == 'pid') {
						$postlist = C::t('forum_post')->fetch_all($id, $ids, false);
					} elseif($idtype == 'tid') {
						$postlist = C::t('forum_post')->fetch_all_by_tid($id, $ids, false);
					} elseif($idtype == 'authorid') {
						$postlist = C::t('forum_post')->fetch_all_by_authorid($id, $ids, false);
					}
					foreach($postlist as $post) {
						if($post['invisible'] != -1 && $post['invisible'] != -5) {
							if($post['first']) {
								$tuidarray[$post['fid']][] = $post['authorid'];
							} else {
								$ruidarray[$post['fid']][] = $post['authorid'];
								if($post['authorid'] > 0 && $post['replycredit'] > 0) {
									$replycredit_list[$post['authorid']][$post['tid']] += $post['replycredit'];
								}
							}
							$tids[] = $post['tid'];
						}
					}
					unset($postlist);
				}

				if($tuidarray || $ruidarray) {
					require_once libfile('function/post');
				}
				if($tuidarray) {
					foreach($tuidarray as $fid => $tuids) {
						updatepostcredits('-', $tuids, 'post', $fid);
					}
				}
				if($ruidarray) {
					foreach($ruidarray as $fid => $ruids) {
						updatepostcredits('-', $ruids, 'reply', $fid);
					}
				}
			}

			foreach($posttableids as $id) {
				if($recycle) {
					C::t('forum_post')->update($id, $ids, array('invisible' => -5));
				} else {
					if($idtype == 'pid') {
						C::t('forum_post')->delete($id, $ids);
						C::t('forum_postcomment')->delete_by_pid($ids);
						C::t('forum_postcomment')->delete_by_rpid($ids);
					} elseif($idtype == 'tid') {
						C::t('forum_post')->delete_by_tid($id, $ids);
						C::t('forum_postcomment')->delete_by_tid($ids);
					} elseif($idtype == 'authorid') {
						C::t('forum_post')->delete_by_authorid($id, $ids);
						C::t('forum_postcomment')->delete_by_authorid($ids);
					}
					C::t('forum_trade')->delete_by_id_idtype($ids, ($idtype == 'authorid' ? 'sellerid' : $idtype));
					C::t('home_feed')->delete_by_id_idtype($ids, ($idtype == 'authorid' ? 'uid' : $idtype));
				}
			}
			if(!$recycle && $idtype != 'authorid') {
				if($idtype == 'pid') {
					C::t('forum_poststick')->delete_by_pid($ids);
				} elseif($idtype == 'tid') {
					C::t('forum_poststick')->delete_by_tid($ids);
				}

			}
			if($idtype == 'pid') {
				C::t('forum_postcomment')->delete_by_rpid($ids);
				C::t('common_moderate')->delete($ids, 'pid');
				C::t('forum_post_location')->delete($ids);
				C::t('forum_filter_post')->delete_by_pid($ids);
				C::t('forum_hotreply_number')->delete_by_pid($ids);
				C::t('forum_hotreply_member')->delete_by_pid($ids);
			} elseif($idtype == 'tid') {
				C::t('forum_post_location')->delete_by_tid($ids);
				C::t('forum_filter_post')->delete_by_tid($ids);
				C::t('forum_hotreply_number')->delete_by_tid($ids);
				C::t('forum_hotreply_member')->delete_by_tid($ids);
				C::t('forum_sofa')->delete($ids);
			} elseif($idtype == 'authorid') {
				C::t('forum_post_location')->delete_by_uid($ids);
			}
			if($replycredit_list) {
				foreach(C::t('forum_replycredit')->fetch_all($tids) as $rule) {
					$rule['extcreditstype'] = $rule['extcreditstype'] ? $rule['extcreditstype'] : $_G['setting']['creditstransextra'][10] ;
					$replycredity_rule[$rule['tid']] = $rule;
				}
				foreach($replycredit_list AS $uid => $tid_credit) {
					foreach($tid_credit AS $tid => $credit) {
						$uid_credit[$replycredity_rule[$tid]['extcreditstype']] -= $credit;
					}
					updatemembercount($uid, $uid_credit, true);
				}
			}
			if(!$recycle) {
				maile_deleteattach($ids, $idtype);
			}
			return $count;
		}
	}

	// ========================================================================

	if(!$tids){
		return 0;
	}

	$count = count($tids);
	$arrtids = $tids;
	$tids = dimplode($tids);

	loadcache(array('threadtableids', 'posttableids'));
	$threadtableids = !empty($_G['cache']['threadtableids']) ? $_G['cache']['threadtableids'] : array();
	$posttableids = !empty($_G['cache']['posttableids']) ? $_G['cache']['posttableids'] : array('0');
	if(!in_array(0, $threadtableids)) {
		$threadtableids = array_merge(array(0), $threadtableids);
	}

	C::t('common_moderate')->delete($arrtids, 'tid');
	C::t('forum_threadclosed')->delete($arrtids);
	C::t('forum_newthread')->delete_by_tids($arrtids);

	$cachefids = $atids = $fids = $postids = $threadtables = array();
	foreach($threadtableids as $tableid) {
		foreach(C::t('forum_thread')->fetch_all_by_tid($arrtids, 0, 0, $tableid) as $row) {
			$atids[] = $row['tid'];
			$row['posttableid'] = !empty($row['posttableid']) && in_array($row['posttableid'], $posttableids) ? $row['posttableid'] : '0';
			$postids[$row['posttableid']][$row['tid']] = $row['tid'];
			if($tableid) {
				$fids[$row['fid']][] = $tableid;
			}
			$cachefids[$row['fid']] = $row['fid'];
		}
		if(!$tableid && !$ponly) {
			$threadtables[] = $tableid;
		}
	}

	if($credit || $membercount) {
		$losslessdel = $_G['setting']['losslessdel'] > 0 ? TIMESTAMP - $_G['setting']['losslessdel'] * 86400 : 0;

		$postlist = $uidarray = $tuidarray = $ruidarray = array();
		foreach($postids as $posttableid => $posttabletids) {
			foreach(C::t('forum_post')->fetch_all_by_tid($posttableid, $posttabletids, false) as $post) {
				if($post['invisible'] != -1 && $post['invisible'] != -5) {
					$postlist[] = $post;
				}
			}
		}
		foreach(C::t('forum_replycredit')->fetch_all($arrtids) as $rule) {
			$rule['extcreditstype'] = $rule['extcreditstype'] ? $rule['extcreditstype'] : $_G['setting']['creditstransextra'][10] ;
			$replycredit_rule[$rule['tid']] = $rule;
		}

		foreach($postlist as $post) {
			if($post['dateline'] < $losslessdel) {
				if($membercount) {
					if($post['first']) {
						updatemembercount($post['authorid'], array('threads' => -1, 'post' => -1), false);
					} else {
						updatemembercount($post['authorid'], array('posts' => -1), false);
					}
				}
			} else {
				if($credit) {
					if($post['first']) {
						$tuidarray[$post['fid']][] = $post['authorid'];
					} else {
						$ruidarray[$post['fid']][] = $post['authorid'];
					}
				}
			}
			if($credit || $membercount) {
				if($post['authorid'] > 0 && $post['replycredit'] > 0) {
					if($replycredit_rule[$post['tid']]['extcreditstype']) {
						updatemembercount($post['authorid'], array($replycredit_rule[$post['tid']]['extcreditstype'] => (int)('-'.$post['replycredit'])));
					}
				}
			}
		}

		if($credit) {
			if($tuidarray || $ruidarray) {
				require_once libfile('function/post');
			}
			if($tuidarray) {
				foreach($tuidarray as $fid => $tuids) {
					updatepostcredits('-', $tuids, 'post', $fid);
				}
			}
			if($ruidarray) {
				foreach($ruidarray as $fid => $ruids) {
					updatepostcredits('-', $ruids, 'reply', $fid);
				}
			}
			$auidarray = $attachtables = array();
			foreach($atids as $tid) {
				$attachtables[getattachtableid($tid)][] = $tid;
			}
			foreach($attachtables as $attachtable => $attachtids) {
				foreach(C::t('forum_attachment_n')->fetch_all_by_id($attachtable, 'tid', $attachtids) as $attach) {
					if($attach['dateline'] > $losslessdel) {
						$auidarray[$attach['uid']] = !empty($auidarray[$attach['uid']]) ? $auidarray[$attach['uid']] + 1 : 1;
					}
				}
			}
			if($auidarray) {
				$postattachcredits = !empty($_G['forum']['postattachcredits']) ? $_G['forum']['postattachcredits'] : $_G['setting']['creditspolicy']['postattach'];
				updateattachcredits('-', $auidarray, $postattachcredits);
			}
		}
	}

	$relatecollection = C::t('forum_collectionthread')->fetch_all_by_tids($arrtids);
	if(count($relatecollection) > 0) {
		$collectionids = array();
		foreach($relatecollection as $collection) {
			$collectionids[] = $collection['ctid'];
		}
		$collectioninfo = C::t('forum_collection')->fetch_all($collectionids);
		foreach($relatecollection as $collection) {
			$decthread = C::t('forum_collectionthread')->delete_by_ctid_tid($collection['ctid'], $arrtids);
			$lastpost = null;
			if(in_array($collectioninfo[$collection['ctid']]['lastpost'], $arrtids) && ($collectioninfo[$collection['ctid']]['threadnum'] - $decthread) > 0) {
				$collection_thread = C::t('forum_collectionthread')->fetch_by_ctid_dateline($collection['ctid']);
				if($collection_thread) {
					$thread = C::t('forum_thread')->fetch($collection_thread['tid']);
					$lastpost = array(
						'lastpost' => $thread['tid'],
						'lastsubject' => $thread['subject'],
						'lastposttime' => $thread['dateline'],
						'lastposter' => $thread['authorid']
					);
				}
			}
			C::t('forum_collection')->update_by_ctid($collection['ctid'], -$decthread, 0, 0, 0, 0, 0, $lastpost);
		}
		C::t('forum_collectionrelated')->delete($arrtids);
	}
	if($cachefids) {
		C::t('forum_thread')->clear_cache($cachefids, 'forumdisplay_');
	}
	if($ponly) {
		C::t('forum_thread')->update($arrtids, array('displayorder'=>-1, 'digest'=>0, 'moderated'=>1));
		foreach($postids as $posttableid=>$oneposttids) {
			C::t('forum_post')->update_by_tid($posttableid, $oneposttids, array('invisible' => '-1'));
		}
		return $count;
	}

	C::t('forum_replycredit')->delete($arrtids);
	C::t('forum_post_location')->delete_by_tid($arrtids);
	C::t('common_credit_log')->delete_by_operation_relatedid(array('RCT', 'RCA', 'RCB'), $arrtids);
	C::t('forum_threadhidelog')->delete_by_tid($arrtids);
	deletethreadcover($arrtids);
	foreach($threadtables as $tableid) {
		C::t('forum_thread')->delete_by_tid($arrtids, false, $tableid);
	}

	if($atids) {
		foreach($postids as $posttableid=>$oneposttids) {
			maile_deletepost($oneposttids, 'tid', false, $posttableid);
		}
		maile_deleteattach($atids, 'tid');
	}

	if($fids) {
		loadcache('forums');
		foreach($fids as $fid => $tableids) {
			if(empty($_G['cache']['forums'][$fid]['archive'])) {
				continue;
			}
			foreach(C::t('forum_thread')->count_posts_by_fid($fid) as $row) {
				C::t('forum_forum_threadtable')->insert(array(
						'fid' => $fid,
						'threadtableid' => $tableid,
						'threads' => $row['threads'],
						'posts' => $row['posts']
				), false, true);
			}
		}
	}

	foreach(array('forum_forumrecommend', 'forum_polloption', 'forum_poll', 'forum_polloption_image', 'forum_activity', 'forum_activityapply', 'forum_debate',
		'forum_debatepost', 'forum_threadmod', 'forum_relatedthread',
		'forum_pollvoter', 'forum_threadimage', 'forum_threadpreview') as $table) {
		C::t($table)->delete_by_tid($arrtids);
	}
	C::t('forum_typeoptionvar')->delete_by_tid($arrtids);
	C::t('forum_poststick')->delete_by_tid($arrtids);
	C::t('forum_filter_post')->delete_by_tid($arrtids);
	C::t('forum_hotreply_member')->delete_by_tid($arrtids);
	C::t('forum_hotreply_number')->delete_by_tid($arrtids);
	C::t('home_feed')->delete_by_id_idtype($arrtids, 'tid');
	C::t('common_tagitem')->delete(0, $arrtids, 'tid');
	C::t('forum_threadrush')->delete($arrtids);

	return $count;

