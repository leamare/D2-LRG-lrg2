<?php

function locale_string($string_id, $vars = []) {
  global $strings;
  global $locale;
  global $fallback_locale;

  if(empty($fallback_locale) && isset($locale) && isset($strings[$locale]['__fallback'])) {
    $fallback_locale = $strings[$locale]['__fallback'];
  }

  if(isset($locale) && isset($strings[$locale][$string_id])) {
    $string = $strings[$locale][$string_id];
  } else if (isset($locale) && isset($fallback_locale) && isset($strings[$fallback_locale][$string_id])) {
    $string = $strings[$fallback_locale][$string_id];
  } else if (isset($strings['en'][$string_id]))
    $string = $strings['en'][$string_id];
  else $string = $string_id;

  foreach($vars as $k => $v) {
    $string = str_ireplace("%$k%", $v, $string);
  }

  return $string;
}

function include_locale($locale) {
  global $strings;

  if (file_exists('locales/'.$locale.'.php')) {
    require_once('locales/'.$locale.'.php');
  } elseif (file_exists('locales/'.$locale.'.json')) {
    $strings[$locale] = json_decode(file_get_contents('locales/'.$locale.'.json'), true);
  } else return false;

  return true;
}