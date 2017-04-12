<?php
class Search {
  private $articles = [];
  private $textStatistics;

  private function flesch_kincaid($text) {
    $total_words = str_word_count($text);
    $total_sentences = preg_match_all('/[.!?\r]/', $text, $tmp );
    $total_syllables = preg_match_all('/[aeiouy]/', $text, $tmp );

    $reading_ease = 206.835 - 1.015 * ($total_words/$total_sentences) - 84.6 * ($total_syllables/$total_words);
    $reading_grade = 0.39 * ($total_words/$total_sentences) + 11.8 * ($total_syllables/$total_words) - 15.59;

    return $reading_ease;
  }

  public function render_search($f3) {
    $q = $f3->get('GET.q');

    if ($q) {
      $this->_do_search($q);
      $this->_sort_by_score();
    }

		$f3->set('q', $q);
    $f3->set('articles', $this->articles);

    $f3->set('content','search.htm');
		echo View::instance()->render('layout.htm');
  }

  private function _query($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, $url);

    $result = curl_exec($ch);

    curl_close($ch);

    return json_decode($result);
  }

  private function _do_search($q) {
    $url = "https://en.wikipedia.org/w/api.php?action=query&list=categorymembers&cmtitle=Category:$q&format=json&cmlimit=5&explaintext";

    $this->articles = array_reduce(
      $this->_query($url)->query->categorymembers,
      function ($all, $item) {
        $all[$item->pageid] = $item;
        return $all;
      });

    $this->_get_extracts();
  }

  private function _get_pages_ids() {
    function _get_article_id($article) {
      return $article->pageid;
    }
    return array_map('_get_article_id', $this->articles);
  }

  private function _get_extracts() {
    $ids = $this->_get_pages_ids();

    $url = "https://en.wikipedia.org/w/api.php?action=query&prop=extracts&exintro&explaintext&format=json&pageids=" . implode("|", $ids);

    $extracts = $this->_query($url)->query->pages;

    foreach($extracts as $id => $content) {
      $this->articles[$content->pageid]->extract = isset($content->extract) ? $content->extract : "";
      $this->articles[$content->pageid]->score = isset($content->extract) ? $this->flesch_kincaid($content->extract) : 0;
    }
  }

  private function _sort_by_score() {
    function cmp_scores($a, $b) {
      if ($a->score == $b->score) {
        return 0;
      }
      return ($a->score < $b->score) ? -1 : 1;
    }

    uasort($this->articles, 'cmp_scores');
  }
}

