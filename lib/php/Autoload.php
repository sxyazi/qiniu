<?php

	$autoload = array('Zone', 'Auth', 'Config', 'Http/Client', 'Http/Request', 'Http/Response', 'functions');

	$path = dirname(__FILE__) . '/';
	foreach($autoload as $v){
		require $path . $v . '.php';
	}

