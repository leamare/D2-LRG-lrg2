<?php
function hero_portrait($hid) {
  global $meta, $portraits_provider;
  if (isset($meta->heroes[ $hid ]['tag']))
    return "<img class=\"hero_portrait\" src=\"".str_replace("%HERO%", $meta['heroes'][ $hid ]['tag'], $portraits_provider)."\" alt=\"".$meta['heroes'][ $hid ]['tag']."\"".
      " data-aliases=\"".hero_aliases($hid)."\" />";
  else return "<img class=\"hero_portrait\" alt=\"undefined\" />";
}

function hero_icon_link($hid) {
  global $meta, $icons_provider;
  if (isset($meta->heroes[ $hid ]['tag']))
    return str_replace("%HERO%", $meta['heroes'][ $hid ]['tag'], $icons_provider);
  return str_replace("%HERO%", 'default', $icons_provider);
}

function hero_icon($hid) {
  global $meta, $icons_provider;
  if (isset($meta->heroes[ $hid ]['tag']))
    return "<img class=\"hero_icon\" src=\"".hero_icon_link($hid)."\" alt=\"".$meta['heroes'][ $hid ]['tag'].
      "\" data-aliases=\"".hero_aliases($hid)."\" />";
  else return "<img class=\"hero_icon\" alt=\"undefined\" />";
}

function hero_full($hid) {
  return hero_portrait($hid)." ".hero_link($hid);
}

function hero_full_icon($hid) {
  return hero_icon($hid)." ".hero_link($hid);
}

function hero_name($hid) {
  global $meta, $locale;

  if (is_special_locale($locale)) {
    include_locale($locale, "heroes");
    $hname = locale_string("heroid$hid", [], $locale);
    return ($hname == "heroid$hid") ? $meta->heroes[ $hid ]['name'] : $hname;
  }

  if (isset($meta->heroes[ $hid ]['name'])) return $meta->heroes[ $hid ]['name'];
  return "undefined";
}

function hero_tag($hid) {
  global $meta;
  if (isset($meta->heroes[ $hid ]['tag'])) return $meta->heroes[ $hid ]['tag'];
  return "undefined";
}

function hero_link($hid) {
  global $leaguetag, $linkvars;

  return "<a href=\"?league=$leaguetag&mod=heroes-profiles-heroid$hid".(empty($linkvars) ? "" : "&".$linkvars)."\">".hero_name($hid)."</a>";
}

function hero_aliases($hid) {
  global $meta, $locale;

  $aliases = "";

  if (is_special_locale($locale)) {
    $aliases = $meta->heroes[ $hid ]['name'] ?? "";
  }

  return $aliases." ".(
    ($meta['heroes'][ $hid ]['alt'] ?? "") . " " .
    ($meta['heroes'][ $hid ]['aliases'] ?? "")
  );
}