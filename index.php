<?php

// Kickstart the framework
$f3=require('lib/base.php');

// Load configuration
$f3->config('config.ini');

function get_articles($title) {
  $url = "https://en.wikipedia.org/w/api.php?action=query&list=categorymembers&cmtitle=Category:$title&format=json";

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_URL, $url);
  $result = curl_exec($ch);
  curl_close($ch);

  return json_decode($result);
}

$f3->route('GET /',
  function($f3) {
    $q = $f3->get('GET.q');
    $articles = [];

    if ($q) {
      $articles = get_articles($q)->query->categorymembers;
    }

		$f3->set('q', $q);
    $f3->set('articles', $articles);

    $f3->set('content','search.htm');
		echo View::instance()->render('layout.htm');
	}
);

$f3->run();
