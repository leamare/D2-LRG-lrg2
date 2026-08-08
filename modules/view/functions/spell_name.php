<?php

function spell_tag($sid) {
  global $meta;
  $tag = $meta['spells_tags'][$sid] ?? null;
  if (is_array($tag)) $tag = $tag[0] ?? null;
  return $tag ?? "unknown";
}

function spell_name($sid) {
  global $locale;
  include_locale($locale, "spells");
  $tag = spell_tag($sid);
  $key = "#spell::".$tag;
  $name = locale_string($key);

  return $name === $key ? spell_humanize_tag($tag) : $name;
}

function spell_humanize_tag($tag) {
  $t = preg_replace('/^special_bonus_unique_/', '', $tag);
  $t = preg_replace('/^special_bonus_/', '', $t);
  $t = trim(str_replace('_', ' ', $t));
  return $t !== '' ? ucwords($t) : $tag;
}

function spell_icon_link($sid, $tag_override = null) {
  global $spell_icons_provider;
  return str_replace("%HERO%", $tag_override ?? spell_tag($sid), $spell_icons_provider);
}

function spell_icon($sid, $classes = '', $tag_override = null) {
  $tag = $tag_override ?? spell_tag($sid);
  return "<img class=\"spell_icon $classes\" src=\"".spell_icon_link($sid, $tag_override)."\" alt=\"$tag\" />";
}
