﻿<?php
$f3 = require('etc/fatfree/lib/base.php');
require('etc/recaptcha/src/autoload.php');
require('etc/Mobile-Detect-2.8.24/Mobile_Detect.php');
$f3->set('DEBUG', 0);
$f3->set('CACHE', TRUE);
$f3->set('TZ','America/Bahia');
$f3->set('LOCALES','etc/dict/');
$f3->set('log', new Log('etc/archivesworldmap.log')); // put in a directory hidden from webserver
$f3->set('LANGUAGE', $_COOKIE['language']);
$f3->set('FALLBACK','en-US');

// Force SSL
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') {
	header("Location: https://map.arquivista.net" . $_SERVER['REQUEST_URI']);
}

// SQLite database - you can put in a directory without access from public by webserver
$f3->set('mapdb', new \DB\SQL('sqlite:/var/www/ArchivesMap.db'));

// COOKIE LANGUAGE
if($f3->get('GET.language')){
	setcookie('language', $f3->get('GET.language'));
	header('Location: '.$_SERVER['HTTP_REFERER']);  
}

$f3->route('GET /',
   function($f3) {
      $f3->set('page','mapa');
	  
	  $f3->set('sql', new DB\SQL\Mapper($f3->get('mapdb'), 'arquivos'));
	  $registros = $f3->get('sql')->find(array('status = ?', 'verified'));
	  
	  $device = new Mobile_Detect;

	  if($device->isMobile()){
		$f3->set('sizemap', 'width: 325px; height: 450px;');
	  } else {
		$f3->set('sizemap', 'width: 700px; height: 450px;');
	  }
	  
	  foreach ($registros as $locais){
		  $pinos .= 'var marker' . 
		    $locais['id'] . ' = L.marker([' . 
			$locais['latitude'] . ',' . 
			$locais['longitude'] . ']).addTo(mymap)'.
			'.bindPopup(\'' . html_entity_decode($locais['nome']) . 
			'<br><a href=\"./info/' . $locais['id'] . '\">info</a>' . '\');';
	  }
	  $f3->set('pinagem', $pinos);
	  
	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->route('GET /add',
   function($f3) {
      $f3->set('page','addmap');

	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->route('POST /proc-addmap',
   function($f3) {
      $f3->set('page','proc-addmap');

		$recaptcha = new \ReCaptcha\ReCaptcha('SERVER SIDE KEY'); // https://www.google.com/recaptcha/
		$resp = $recaptcha->verify($_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
		if ($resp->isSuccess()) {
		  // verified!

		  // Setting up the database
		  $f3->set('sql', new DB\SQL\Mapper($f3->get('mapdb'), 'arquivos'));

		  $f3->get('sql')->latitude = 			$f3->get('POST.latitude');
		  $f3->get('sql')->longitude = 			$f3->get('POST.longitude');
		  $f3->get('sql')->nome = 			$f3->get('POST.nome');
		  $f3->get('sql')->identificador = 		$f3->get('POST.identificador');
		  $f3->get('sql')->logradouro = 		$f3->get('POST.logradouro');
		  $f3->get('sql')->cidade = 			$f3->get('POST.cidade');
		  $f3->get('sql')->estado = 			$f3->get('POST.estado');
		  $f3->get('sql')->pais = 			$f3->get('POST.pais');
		  $f3->get('sql')->url = 			$f3->get('POST.url');
		  $f3->get('sql')->email = 			$f3->get('POST.email');
		  $f3->get('sql')->contributor = 		$f3->get('POST.contributor');
		  $f3->get('sql')->contributoremail = 		$f3->get('POST.contributoremail');
		  $f3->get('sql')->status = 			'waiting';
		  $f3->get('sql')->save();
			 
		  // Criar log de inserção
		$f3->get('log')->write($f3->get('POST.nome') . ' (' . $f3->get('POST.latitude') . ',' . 
			$f3->get('POST.longitude') . ') was added or changed.');

			 
	} else {
		//$errors = $resp->getErrorCodes();
		$f3->reroute('/add-fail');
	}
	
	  $f3->reroute('/add-done');
   }
);

$f3->route('POST|GET /list',
   function($f3) {
      $f3->set('page','listmap');

	  // Setting up the database
	  $f3->set('sql', new DB\SQL\Mapper($f3->get('mapdb'), 'arquivos'));

	  // Logando area administrativa
	  $f3->set('admin', new DB\SQL\Mapper($f3->get('mapdb'), 'usuarios'));
	  $crypt = \Bcrypt::instance();
	  $f3->set('auth', new \Auth($f3->get('admin'), array('id'=>'login', 'pw'=>'hash'))); // login e hash

	  // Verificando login e senha do admin
	  	  // Verificando se é admin
		if (!$f3->get('SESSION.logon')){

		  // REMEMBER TO PUT A BCRYPT SALT IN NEXT LINE
		  if($f3->get('auth')->login($f3->get('POST.user'),$crypt->hash($f3->get('POST.pass'), 'BCRYPT-SALT-22-CHARACTERS')) == FALSE){
			$f3->clear('SESSION.logon');
			session_commit();
			$f3->reroute('/');
		  } else {
			new \DB\SQL\Session($f3->get('mapdb'));
			$f3->set('SESSION.logon', 'sim');
		  };
	  }

	  $f3->set('lista', $f3->get('sql')->find(array('status = ?', 'waiting')));

	  $lista = $f3->get('sql')->find(array());

	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->route('POST|GET /listall',
   function($f3) {
      $f3->set('page','listallmap');

	  // Checking if is admin
	  if($f3->get('SESSION.logon') != 'sim'){
		$f3->reroute('/');
	  }
	  // Setting up the database
	  $f3->set('sql', new DB\SQL\Mapper($f3->get('mapdb'), 'arquivos'));
	  
	  $f3->set('lista', $f3->get('sql')->find(array()));
	  
	  
	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->route('GET /edit/@id',
   function($f3) {
      $f3->set('page','editmap');

	  // Verificando se é admin
	  if($f3->get('SESSION.logon') != 'sim'){
		$f3->reroute('/');
	  }

	  // Setting up the database
	  $f3->set('sql', new DB\SQL\Mapper($f3->get('mapdb'), 'arquivos'));

	  $f3->set('lista', $f3->get('sql')->find(array('id = ?', $f3->get('PARAMS.id'))));

	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->route('POST /proc-editmap/@id',
   function($f3) {
      $f3->set('page','proc-editmap');

	  // Verificando se é admin
	  if($f3->get('SESSION.logon') != 'sim'){
		$f3->reroute('/');
	  }

	  // Setting up the database
	  $f3->set('sql', new DB\SQL\Mapper($f3->get('mapdb'), 'arquivos'));

	  $f3->get('sql')->load(array('id = ?', $f3->get('PARAMS.id')));

	  $f3->get('sql')->latitude = 			$f3->get('POST.latitude');
	  $f3->get('sql')->longitude = 			$f3->get('POST.longitude');
	  $f3->get('sql')->nome = 			$f3->get('POST.nome');
	  $f3->get('sql')->identificador = 		$f3->get('POST.identificador');
	  $f3->get('sql')->logradouro = 		$f3->get('POST.logradouro');
	  $f3->get('sql')->cidade = 			$f3->get('POST.cidade');
	  $f3->get('sql')->estado = 			$f3->get('POST.estado');
	  $f3->get('sql')->pais = 			$f3->get('POST.pais');
	  $f3->get('sql')->url = 			$f3->get('POST.url');
	  $f3->get('sql')->email = 			$f3->get('POST.email');
	  $f3->get('sql')->contributor = 		$f3->get('POST.contributor');
	  $f3->get('sql')->contributoremail = 		$f3->get('POST.contributoremail');
	  $f3->get('sql')->status = 			$f3->get('POST.status');
	  $f3->get('sql')->save();

	  // Log
		$f3->get('log')->write($f3->get('POST.nome') . ' (' . $f3->get('POST.latitude') . 
		',' . $f3->get('POST.longitude') . ') was added or changed.');

	  $f3->reroute('/list');
   }
);

$f3->route('GET /info/@id',
   function($f3) {
      $f3->set('page','infomap');

	  // Setting up the database
	  $f3->set('sql', new DB\SQL\Mapper($f3->get('mapdb'), 'arquivos'));

	  $f3->set('lista', $f3->get('sql')->find(array('id = ?', $f3->get('PARAMS.id'))));
	  	  
	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->route('GET /del/@id',
   function($f3) {
      $f3->set('page','proc-delmap');

	  // Verificando se é admin
	  if($f3->get('SESSION.logon') != 'sim'){
		$f3->reroute('/');
	  }
	  
	  // Setting up the database
	  $f3->set('sql', new DB\SQL\Mapper($f3->get('mapdb'), 'arquivos'));

	  $f3->get('sql')->load(array('id = ?', $f3->get('PARAMS.id')));
	  $f3->get('sql')->erase();
	  
	  // Criar log de remoção
			
	  $f3->reroute('/list');
   }
);

$f3->route('GET /logout',
   function($f3) {
      $f3->set('page','logout');

	  $f3->clear('SESSION.logon');
	  session_commit();
	  
	  $f3->reroute('/');
   }
);

$f3->route('GET|POST /login',
   function($f3) {
      $f3->set('page','login');
	  
	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->route('GET /about',
   function($f3) {
      $f3->set('page','about');
	  
	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->route('GET /api-dev',
   function($f3) {
      $f3->set('page','api');
	  
	  $f3->set('sql', new DB\SQL\Mapper($f3->get('mapdb'), 'arquivos'));

	  $res = $f3->get('sql')->load(array('status = ?', 'verified'));

	  foreach ($res as $row) 
		echo json_encode($res->cast());
   }
);

$f3->route('GET /contact',
   function($f3) {
      $f3->set('page','contact');
	  
	  
	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->route('GET /add-done',
   function($f3) {
      $f3->set('page','add-done');
	  
	  
	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->route('GET /add-fail',
   function($f3) {
      $f3->set('page','add-fail');
	  
	  
	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->route('GET /thankyou',
   function($f3) {
      $f3->set('page','thankyou');
	  
	  
	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->route('GET /search',
   function($f3) {
      $f3->set('page','search');
	  
	  
	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->route('GET /search/result',
   function($f3) {
      $f3->set('page','search-result');
	  
	  // Setting up the database
	  $f3->set('sql', new DB\SQL\Mapper($f3->get('mapdb'), 'arquivos'));
	  
	  $f3->set('lista', $f3->get('sql')->find(array('nome LIKE ? AND status = "verified"', '%'.$f3->get('GET.q').'%')));
	  
	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->route('GET /api',
   function($f3) {
      $f3->set('page','api');
	  
	  // Setting up the database
	  $f3->set('apidb', new \DB\SQL('sqlite:/var/www/ArchivesMap.db'));

	  $f3->set('result',$f3->get('apidb')->exec(array('SELECT id,nome FROM arquivos WHERE status = "verified"'), NULL));
	  header('Content-Type: application/json');
	  header('charset=utf-8');
	  header('Access-Control-Allow-Origin: *');
	  echo html_entity_decode(json_encode($f3->get('result')));
	  
   }
);

$f3->route('GET /stats',
   function($f3) {
      $f3->set('page','stats');
	  
	  // Setting up the database
	  $f3->set('statsdb', new \DB\SQL('sqlite:/var/www/ArchivesMap.db'));

	  // Quantidade de instituicoes verificadas
	  $f3->set('res', $f3->get('statsdb')->exec('SELECT id FROM arquivos WHERE status = "verified"')); 
	  $f3->set('qtdinstituicoes', strval((count($f3->get('res')))));
	  
	  // 
	  $f3->set('res', $f3->get('statsdb')->exec('SELECT pais, count(pais) AS visits FROM arquivos WHERE status = "verified" GROUP BY pais ORDER BY visits DESC LIMIT 10')); 
	  $f3->set('paisesverificados', $f3->get('res'));
	  
	  
	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->run();
