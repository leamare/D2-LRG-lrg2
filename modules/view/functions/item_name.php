<?php

function item_icon($iid) {
  global $item_icons_provider;
  return "<img class=\"hero_portrait\" src=\"".str_replace("%HERO%", item_tag($iid), $item_icons_provider)."\" alt=\"".item_tag($iid)."\" />";
}

function item_icon_link($iid) {
  global $item_icons_provider;
  return str_replace("%HERO%", item_tag($iid), $item_icons_provider);
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

function item_full($iid) {
  return item_icon($hid)." ".item_name($hid);
}