<?php
/** 
 * Generating team tag
 */

if (!function_exists('mb_ucfirst')) {
  function mb_ucfirst($string, $encoding = "UTF-8") {
    $firstChar = mb_substr($string, 0, 1, $encoding);
    $then = mb_substr($string, 1, null, $encoding);
    return mb_strtoupper($firstChar, $encoding) . $then;
  }
}

if (!function_exists('mb_ctype_upper')) {
  function mb_ctype_upper($string) {
    // return $string == mb_strtoupper($string, $encoding = "UTF-8");
    return preg_match('~^\p{Lu}~u', $string);
  }
}

if (!function_exists('mb_ucwords')) {
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
}

// Polyfill PHP < 7.4 based on package "symfony/polyfill-mbstring"
if (!function_exists('mb_str_split')) {
  function mb_str_split($string, $split_length = 1, $encoding = null) {
    if (null !== $string && !\is_scalar($string) && !(\is_object($string) && \method_exists($string, '__toString'))) {
      trigger_error('mb_str_split(): expects parameter 1 to be string, '.\gettype($string).' given', E_USER_WARNING);
      return null;
    }
    if (null !== $split_length && !\is_bool($split_length) && !\is_numeric($split_length)) {
      trigger_error('mb_str_split(): expects parameter 2 to be int, '.\gettype($split_length).' given', E_USER_WARNING);
      return null;
    }
    $split_length = (int) $split_length;
    if (1 > $split_length) {
      trigger_error('mb_str_split(): The length of each segment must be greater than zero', E_USER_WARNING);
      return false;
    }
    if (null === $encoding) {
      $encoding = mb_internal_encoding();
    } else {
      $encoding = (string) $encoding;
    }
    
    if (! in_array($encoding, mb_list_encodings(), true)) {
      static $aliases;
      if ($aliases === null) {
        $aliases = [];
        foreach (mb_list_encodings() as $encoding) {
          $encoding_aliases = mb_encoding_aliases($encoding);
          if ($encoding_aliases) {
            foreach ($encoding_aliases as $alias) {
              $aliases[] = $alias;
            }
          }
        }
      }
      if (! in_array($encoding, $aliases, true)) {
        trigger_error('mb_str_split(): Unknown encoding "'.$encoding.'"', E_USER_WARNING);
        return null;
      }
    }
    
    $result = [];
    $length = mb_strlen($string, $encoding);
    for ($i = 0; $i < $length; $i += $split_length) {
      $result[] = mb_substr($string, $i, $split_length, $encoding);
    }
    return $result;
  }
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

