<?php 

function process_menu($menu, $list = false) {
  $r = "";

  $tag = $list ? "li" : "div";

  foreach ($menu as $link) {
    if (isset($link['children'])) {
      $r .= "<$tag class=\"navItem submenu\"><a href=\"#\" title=\"".($link['text'] ?? $link['title'] ?? "")."\" class=\"parent\">".
        (isset($link['icon']) ? "<img src=\"".$link['icon']."\" class=\"topbar-icon\"> " : "").$link['text']."</a>".
        "<ul>".process_menu($link['children'], true).
      "</$tag>";
    } else {
      $r .= "<$tag class=\"navItem\"><a href=\"".$link['link']."\" target=\"_blank\" rel=\"noopener\" title=\"".($link['text'] ?? $link['title'] ?? "")."\">".
        (isset($link['icon']) ? "<img src=\"".$link['icon']."\" class=\"topbar-icon\"> " : "").$link['text'].
      "</a></$tag>";
    }
  }

  return $r;
}