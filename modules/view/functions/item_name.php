<?php

function item_icon($iid, $classes = '') {
  return "<img class=\"hero_portrait $classes\" src=\"".item_icon_link($iid)."\" alt=\"".item_tag($iid)."\" data-aliases=\"".item_name($iid)."\" />";
}

function item_big_icon($iid) {
  global $item_profile_icons_provider, $item_icons_provider;
  return "<img class=\"hero_portrait\" src=\"".str_replace("%HERO%", item_tag($iid), $item_profile_icons_provider ?? $item_icons_provider)."\" alt=\"".item_tag($iid)."\" />";
}

function item_icon_link($iid) {
  global $item_icons_provider;
  $tag = item_tag($iid);
  if (strpos($tag, "recipe") === 0) {
    $tag = "recipe";
  }
  return str_replace("%HERO%", $tag, $item_icons_provider);
}

function item_tag($iid) {
  global $meta;
  if (isset($meta->items[ $iid ]))
    return $meta->items[ $iid ];
  return "unknown";
}

function item_name($iid) {
  global $meta;
  $meta['items_full'];
  if (isset($meta['items_full'][$iid]))
    return $meta['items_full'][$iid]['localized_name'] ?? $meta['items_full'][$iid]['name'];
  return "Unknown";
}

function item_link($iid) {
  global $leaguetag, $linkvars;
  return "<a href=\"?league=$leaguetag&mod=items-profiles-itemid$iid".(empty($linkvars) ? "" : "&".$linkvars)."\">".
    item_name($iid)."</a>";
}

function item_full($iid) {
  return item_icon($iid)." ".item_name($iid);
}

function item_full_link($iid) {
  return item_icon($iid)." ".item_link($iid);
}