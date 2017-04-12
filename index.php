<?php

// Kickstart the framework
$f3=require('lib/base.php');
require('search.class.php');

// Load configuration
$f3->config('config.ini');

$f3->route('GET /', 'Search->render_search');

$f3->run();
