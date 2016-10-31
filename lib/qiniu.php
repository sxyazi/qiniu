<?php

	namespace maile;

	global $_G;
	require_once DISCUZ_ROOT . 'source/plugin/qiniu/lib/php/Autoload.php';
	require_once DISCUZ_ROOT . 'source/plugin/qiniu/lib/php/Http/Error.php';
	require_once DISCUZ_ROOT . 'source/plugin/qiniu/lib/php/Storage/BucketManager.php';

	qiniu::$config = $_G['cache']['plugin']['qiniu'];

	qiniu::$auth = new \Qiniu\Auth(qiniu::$config['ak'], qiniu::$config['sk']);
	qiniu::$bucket = new \Qiniu\Storage\BucketManager(qiniu::$auth);

	class qiniu{

		public static $auth;
		public static $config;
		public static $bucket;

		/**
		 * 七牛-删除
		 * @param  [type] $key 文件名
		 * @return [type]      [description]
		 */
		public static function unlink($key){
			return (self::$bucket->delete(self::$config['bucket'], $key) === null);
		}

		/**
		 * 七牛-文件重命名
		 * @param  [type] $key 旧名称
		 * @param  [type] $new 新名称
		 * @return [type]      [description]
		 */
		public static function rename($key, $new){
			return (self::$bucket->move(self::$config['bucket'], $key, self::$config['bucket'], $new) === null);
		}

		/**
		 *七牛-获取文件信息
		 * @param  [type] $key [description]
		 * @return [type]      [description]
		 */
		public static function getInfo($key){
			$res = self::$bucket->stat(self::$config['bucket'], $key);
			return $res[0] ?: false;
		}

		/**
		 * 获取图片信息
		 * @param  [type] $key [description]
		 * @return [type]      [description]
		 */
		public static function getImgInfo($key){
			if(($url=self::$config['url'].$key.'?imageInfo') && self::$config['protect'])
				$url = self::getDownURL($url);
			if($res=self::curl($url)){
				$res = json_decode($res, true);
				if($res['error'])
					return false;
				else
					return $res;
			}else{
				return false;
			}
		}

		/**
		 * 七牛-抓取远程文件
		 * @return [type] [description]
		 */
		public static function fetch($url, $key){
			list($ret, $err) = self::$bucket->fetch($url, self::$config['bucket'], $key);
			if($err === null)
				return $ret;
			else
				return false;
		}

		/**
		 * curl模拟提交
		 * @param	string			$url		网址
		 * @param	array/string	$opt		提交参数
		 * @param	string			&$header	取回的头信息
		 * @param	string			$redirect	是否重定向
		 * @param	boolean			$ssl		验证https证书
		 * @return	[type]						返回信息
		 */
		public static function curl($url, $opt='GET', &$header=null, $redirect=true, $ssl=false){

			//初始化
			$ch = curl_init($url);

			//配置设置
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $ssl);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $ssl);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $redirect);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);		#返回结果
			curl_setopt($ch, CURLOPT_HEADER, true);				#显示协议头

			if(is_array($opt)){

				//转小写
				$opt = array_change_key_case($opt, CASE_LOWER);

				//POST
				if(isset($opt['type']) && strtoupper($opt['type'])=='POST'){
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_POSTFIELDS, (isset($opt['data']) ? $opt['data'] : ''));
				}

				//User-Agent
				if(array_key_exists('ua', $opt))
					curl_setopt($ch, CURLOPT_USERAGENT, $opt['ua']);

				//Header
				if(array_key_exists('header', $opt)){
					curl_setopt($ch, CURLOPT_HTTPHEADER, (array)$opt['header']);
				}

				//Cookie
				if(array_key_exists('cookie', $opt))
					curl_setopt($ch, CURLOPT_COOKIE, $opt['cookie']);

				//Referer
				if(array_key_exists('referer', $opt))
					curl_setopt($ch, CURLOPT_REFERER, $opt['referer']);

			}else{

				//仅POST
				if(strtoupper((string)$opt) == 'POST')
					curl_setopt($ch, CURLOPT_POST, true);

			}

			$result = curl_exec($ch);

			if(curl_errno($ch)){
				$result = curl_error($ch);
			}else{

				//获取头长度
				$length = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

				//取出头信息
				$header = substr($result, 0, $length);

				//去掉头信息
				$result = substr($result, $length);

			}

			//释放
			curl_close($ch);

			return $result;

		}

		/**
		 * 获取下载地址
		 * @param  [type] $url [description]
		 * @return [type]      [description]
		 */
		public static function getDownURL($url, $expire=300){
			return self::$auth->privateDownloadUrl($url, $expire);
		}

	}

