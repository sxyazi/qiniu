<?php

	namespace maile;

	class attachXML{

		protected $xml, $dom, $name, $element;

		public function __construct($name, $path){
			if(!isset($name{2}))
				return;

			if(!is_file($this->xml = $path.strtolower(substr($name, 0, 2)).'.xml')){
				if(!file_put_contents($this->xml, '<?xml version="1.0" encoding="utf-8"?><attach></attach>'))
					throw new \Exception('麦乐七牛插件: 请检查权限', 1);
			}

			$this->dom = new \DOMDocument();
			$this->dom->load($this->xml);

			$this->name = $name;
		}

		/**
		 * 获取元素
		 * @return [type] [description]
		 */
		public function get(){
			return $this->element->item(0);
		}

		/**
		 * 添加元素
		 * @param [type] $name [description]
		 */
		public function add($name){
			$i = $this->dom->createElement('info');
			$n = $this->dom->createAttribute('name');
			$u = $this->dom->createAttribute('uses');

			$n->value = $name;
			$u->value = 1;

			$i->appendChild($n);
			$i->appendChild($u);

			$this->dom->getElementsByTagName('attach')->item(0)->appendChild($i);
			return $this->dom->save($this->xml);
		}

		/**
		 * 删除元素
		 * @return [type] [description]
		 */
		public function del(){
			($node=$this->get()) && $node->parentNode->removeChild($this->get());
			return $this->dom->save($this->xml);
		}

		/**
		 * 查找元素
		 * @param  string $name [description]
		 * @return [type]       [description]
		 */
		public function find($name=''){
			!$name && $name=$this->name;

			$xpath = new \DOMXpath($this->dom);
			$this->element = $xpath->query('/attach/info[@name="' . $name . '"]');
			return $this->element->length > 0;
		}

		/**
		 * 获取记录
		 * @return [type] [description]
		 */
		public function getUses(){
			return $this->get()->getAttribute('uses');
		}

		/**
		 * 添加记录
		 * @param integer $num [description]
		 */
		public function addUses($num=1){
			$this->setUses($this->getUses() + $num);
		}

		/**
		 * 删除记录
		 * @return [type] [description]
		 */
		public function delUses($num=1){
			return $this->addUses(-$num);
		}

		/**
		 * 修改记录
		 * @param [type] $num [description]
		 */
		public function setUses($num){
			$this->get()->setAttribute('uses', $num);
			return $this->dom->save($this->xml);
		}

	}

/*
	try{
		$test = new test('FkRsIOKizjRdSE9lqUs9ri7AbDjvtest.png');
	}catch(Exception $e){
		exit($e->getMessage());
	}
*/
	// $test->add('yazi.avi');
	// $test->find('yazi.avi');
	// var_dump($test->getUses());
	// $test->addUses();
	// var_dump($test->del());

