<?php
class Search {
  function render_search($f3) {
    $q = $f3->get('GET.q');
    $articles = [];

    if ($q) {
      $articles = $this->do_search($q)->query->categorymembers;
    }

		$f3->set('q', $q);
    $f3->set('articles', $articles);

    $f3->set('content','search.htm');
		echo View::instance()->render('layout.htm');
  }

  function do_search($q) {
    $url = "https://en.wikipedia.org/w/api.php?action=query&list=categorymembers&cmtitle=Category:$q&format=json&cmlimit=50 ";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result);
  }

}
