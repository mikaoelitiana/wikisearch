<?php
/**
* This class will handle search query and returning result or error for each query
**/
class Search {
  private $articles = [];

  /**
  * Renders a seqrch result using the search.htm template.
  **/
  public function render_search($f3) {
    // the search query is taken from $_GET['q']
    $q = $f3->get('GET.q');

    // If a search query is given, make the API calls and sort articles by their score
    if ($q) {
      $this->_do_search($q);
      $this->_sort_by_score();
    }

    // Send data to template
    $f3->set('q', $q);
    $f3->set('articles', $this->articles);

    $f3->set('content','search.htm');
    echo View::instance()->render('layout.htm');
  }

  /**
  * Makes an GET request to a give URL
  **/
  private function _query($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, $url);

    $result = curl_exec($ch);

    curl_close($ch);

    return json_decode($result);
  }

  /**
  * Query Wikipedia search API to find all categories
  **/
  private function _do_search($q) {
    // API url to get search results of articles in a category
    $url = "https://en.wikipedia.org/w/api.php?action=query&list=categorymembers&cmtitle=Category:$q&format=json&cmlimit=50&cmtype=page&iwurl";
    // call the API url and get the article list
    // The articles are then stored in a key-value array where the pageid is the key
    $this->articles = array_reduce(
      $this->_query($url)->query->categorymembers,
      function ($all, $item) {
        $all[$item->pageid] = $item;
        return $all;
      }, []);
    // When page query is done, we try to get extracts for each article found
    $this->_get_extracts();
  }

  /**
  * Utility function to store articles in a page id keyd array
  **/
  private function _get_pages_ids() {
    function _get_article_id($article) {
      return $article->pageid;
    }
    return array_map('_get_article_id', $this->articles);
  }

  /**
  * Query pages extract from Wikipedia API and store them in their respective article'
  **/
  private function _get_extracts() {
    // Get id of pages to get their intrp extract
    $ids = $this->_get_pages_ids();
    // API url to get extracts
    $url = "https://en.wikipedia.org/w/api.php?action=query&prop=extracts&exintro&exsentences=10&explaintext&format=json&exlimit=50&pageids=" . implode("|", $ids);
    // Make API request and get each pages if found
    $extracts = $this->_query($url)->query->pages;
    // If any extract found, assign them to their respective article using the id
    if ($extracts) {
      foreach($extracts as $id => $content) {
        $this->articles[$content->pageid]->extract = isset($content->extract) ? $content->extract : "";
        $this->articles[$content->pageid]->score = isset($content->extract) && $content->extract ? $this->_flesch_kincaid($content->extract) : 0;
      }
    }
  }

  /*
  * Sort articles using their score property
  */
  private function _sort_by_score() {
    // Utility function to compare score of 2 entry
    function cmp_scores($a, $b) {
      if ($a->score == $b->score) {
        return 0;
      }
      return ($a->score < $b->score) ? -1 : 1;
    }

    uasort($this->articles, 'cmp_scores');
  }

  /*
  * This function calculates the Flesch-Kincaid score of a given text and returns it's reading ease score'
  * The code was inspired by https://sourceforge.net/projects/fleschkincaid
  */
  private function _flesch_kincaid($text) {
    // the formula details can be found at https://en.wikipedia.org/wiki/Flesch%E2%80%93Kincaid_readability_tests
    $total_words = str_word_count($text);
    $total_sentences = preg_match_all('/[.!?\r]/', $text, $tmp );
    $total_syllables = preg_match_all('/[aeiouy]/', $text, $tmp );

    // Avoid division by 0 by checking $total_sentences and $total_words
    if ($total_sentences && $total_words) {
      $reading_ease = 206.835 - 1.015 * ($total_words/$total_sentences) - 84.6 * ($total_syllables/$total_words);
      $reading_grade = 0.39 * ($total_words/$total_sentences) + 11.8 * ($total_syllables/$total_words) - 15.59;
    } else {
      return 0;
    }
    return $reading_ease;
  }
}

