<?php

function locale_string($string_id, $vars = []) {
  global $strings, $locale, $localesMap, $fallback_locale;

  if(empty($fallback_locale) && isset($locale) && isset($localesMap[$locale]) && isset($localesMap[$locale]['fallback'])) {
    $fallback_locale = $localesMap[$locale]['fallback'];
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
  global $strings, $localesMap, $isBetaLocale;

  if (isset($localesMap[ $locale ]) && ($localesMap[ $locale ]['alias'] ?? false)) {
    $locale = $localesMap[ $locale ]['alias'];
  }

  if (!isset($localesMap[ $locale ])) return false;

  if (($localesMap[$locale]['beta'] ?? false) && !$isBetaLocale) {
    return false;
  }

  $file = $localesMap[$locale]['file'] ?? 'locales/'.$locale.'.json';

  if (!isset($strings[$locale])) $strings[$locale] = [];

  // if (file_exists('locales/'.$locale.'.php')) {
  //   require_once('locales/'.$locale.'.php');
  // } else
  if (file_exists($file)) {
    $strings[$locale] = array_merge(
      $strings[$locale],
      json_decode(file_get_contents('locales/'.$locale.'.json'), true)
    );
    
  } else return false;

  return true;
}

function register_locale_string($string, $tag, $reglocale = null) {
  global $strings, $locale, $fallback_locale, $def_locale;

  if (!$reglocale) $reglocale = $def_locale ?? $locale ?? $fallback_locale;

  if (!isset($strings[$locale])) $strings[$reglocale] = [];

  $strings[$reglocale][$tag] = $string;
}