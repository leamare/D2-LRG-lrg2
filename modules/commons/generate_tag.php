<?php
/** 
 * League Report Generator - Generate Team Tag - Fetcher Module
 * ported from D2-LRG-lrg2 v 2.*
 * @author Darien "leamare" Fawkes
 * @license GNU GPL 3.0
 * 
 * @param string $name team's name
 * @return string team tag, based on team's name
 */

function mb_ucfirst($string, $encoding = "UTF-8") {
    $firstChar = mb_substr($string, 0, 1, $encoding);
    $then = mb_substr($string, 1, null, $encoding);
    return mb_strtoupper($firstChar, $encoding) . $then;
}

function mb_ctype_upper($string) {
  // return $string == mb_strtoupper($string, $encoding = "UTF-8");
  return preg_match('~^\p{Lu}~u', $string);
}

function mb_ucwords($string, $delimiters = array(" ", "-", ".", "'", "O'", "Mc")) {
    $string = mb_convert_case($string, MB_CASE_TITLE, "UTF-8");
    foreach ($delimiters as $dlnr => $delimiter) {
        $words = explode($delimiter, $string);
        $newwords = [];
        foreach ($words as $wordnr => $word) {
          $newwords[] = mb_ucfirst($word);
        }
        $string = implode($delimiter, $newwords);
    }
    return $string;
}

function generate_tag($name) {
  $name_chars = mb_str_split($name);

  $words_count = mb_substr_count($name, " ");
  if(count($name_chars) && $words_count === 0) {
    return $name;
  } else if (count($name_chars) && $words_count > 0  && $words_count < 2) {
    $name = mb_ucwords($name);
    $tag = "";

    for ($i=0, $sz=count($name_chars); $i < $sz; $i++) {
      if (mb_ctype_upper($name_chars[$i])) {
        $tag .= $name_chars[$i];
        if(isset($name_chars[$i+1]) && $name_chars[$i+1] != " " && !mb_ctype_upper($name_chars[$i+1]))
          $tag .= $name_chars[$i+1];
      }
    }
  } else if (count($name_chars)) {
    $name = mb_ucwords($name);
    $tag = "";

    for ($i=0, $sz=count($name_chars); $i < $sz; $i++) {
      if (mb_ctype_upper($name_chars[$i])) {
        $tag .= $name_chars[$i];
        var_dump($name_chars[$i]);
      }
    }
  } else {
    $tag = "( )";
  }

  return $tag;
}

?>
