<?php

$_locales_imported = [];

function locale_string($string_id, $vars = [], $loc = null) {
  global $strings, $locale, $localesMap, $fallback_locale;

  if (!$loc) $loc = $locale ?? null;

  if(empty($fallback_locale) && isset($loc) && isset($localesMap[$locale]) && isset($localesMap[$loc]['fallback'])) {
    $fallback_locale = $localesMap[$loc]['fallback'];
  }

  if(isset($loc) && isset($strings[$loc][$string_id])) {
    $string = $strings[$loc][$string_id];
  } else if (isset($loc) && isset($fallback_locale) && isset($strings[$fallback_locale][$string_id])) {
    $string = $strings[$fallback_locale][$string_id];
  } else if (isset($strings['en'][$string_id]))
    $string = $strings['en'][$string_id];
  else $string = $string_id;

  foreach($vars as $k => $v) {
    $string = str_ireplace("%$k%", (string)($v ?? ''), $string);
  }

  return $string;
}

function include_locale($locale, $component = null) {
  global $strings, $localesMap, $isBetaLocale, $root, $fallback_locale, $rootLocale, $_locales_imported;

  if (isset($localesMap[ $locale ]) && ($localesMap[ $locale ]['alias'] ?? false)) {
    $locale = $localesMap[ $locale ]['alias'];
  }

  if (!isset($localesMap[ $locale ])) return false;

  if (($localesMap[$locale]['beta'] ?? false) && !$isBetaLocale) {
    return false;
  }

  $file = [];

  if (isset($component)) {
    $fpath = $root."/locales_additional/$component";

    if ($rootLocale != $locale && file_exists($fpath."_".$rootLocale.".json")) {
      $file[] = [ $fpath."_".$rootLocale.".json", $rootLocale ];
    }
    if ($fallback_locale && file_exists($fpath."_".$fallback_locale.".json")) {
      $file[] = [ $fpath."_".$fallback_locale.".json", $fallback_locale ];
    }
    if (file_exists($fpath."_".$locale.".json")) {
      $file[] = [ $fpath."_".$locale.".json", $locale ];
    }
    if (file_exists($fpath.".json")) {
      $file[] = [ $fpath.".json", $rootLocale ];
    } 
    
    if (empty($file)) {
      return false;
    }

    $_locales_imported[$component.'.'.$locale] = true;
  } else {
    $file[] = [ $localesMap[$locale]['file'] ?? $root.'/locales/'.$locale.'.json', $locale ];
  }
  

  if (!isset($strings[$locale])) $strings[$locale] = [];

  // if (file_exists('locales/'.$locale.'.php')) {
  //   require_once('locales/'.$locale.'.php');
  // } else
  foreach ($file as [ $f, $l ]) {
    if (file_exists($f) && !isset($_locales_imported[$f])) {
      $decoded = json_decode(file_get_contents($f), true);
      $strings[$l] = array_merge(
        $strings[$l] ?? [],
        $decoded ?? []
      );
      $_locales_imported[$f] = true;
    }
  }

  return true;
}

function register_locale_string($string, $tag, $reglocale = null) {
  global $strings, $locale, $fallback_locale, $def_locale;

  if (!$reglocale) $reglocale = $def_locale ?? $locale ?? $fallback_locale;

  if (!isset($strings[$locale])) $strings[$reglocale] = [];

  $strings[$reglocale][$tag] = $string;
}

function is_special_locale($locale) {
  return in_array($locale, ['emoji', 'def']);
}