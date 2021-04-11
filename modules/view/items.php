<?php

$modules['items'] = [];

include_once($root."/modules/view/functions/item_name.php");

include($root."/modules/view/items/overview.php");
if (isset($report['items']['stats'])) {
  include($root."/modules/view/items/stats.php");
  include($root."/modules/view/items/icritical.php");
  include($root."/modules/view/items/boxplots.php");
  include($root."/modules/view/items/heroes.php");
  include($root."/modules/view/items/hboxplots.php");
}
if (isset($report['items']['combos'])) {
  include($root."/modules/view/items/icombos.php");
}
if (isset($report['items']['progr'])) {
  include($root."/modules/view/items/proglist.php");
  include($root."/modules/view/items/progression.php");
}
if (isset($report['items']['records'])) {
  include($root."/modules/view/items/irecords.php");
}

function rg_view_generate_items() {
  global $report, $mod, $parent, $unset_module;

  if($mod == "items") $unset_module = true;
  $parent = "items-";

  $res = [];

  if (check_module($parent."overview")) {
    $res['overview'] = rg_view_generate_items_overview();
  }
  if (isset($report['items']['stats'])) {
    if (check_module($parent."stats")) {
      $res['stats'] = rg_view_generate_items_stats();
    }
    if (check_module($parent."icritical")) {
      $res['icritical'] = rg_view_generate_items_critical();
    }
    if (check_module($parent."boxplots")) {
      $res['boxplots'] = rg_view_generate_items_boxplots();
    }
    if (check_module($parent."heroes")) {
      $res['heroes'] = rg_view_generate_items_heroes();
    }
    if (check_module($parent."heroboxplots")) {
      $res['heroboxplots'] = rg_view_generate_items_heroboxplots();
    }
  }
  if (isset($report['items']['combos'])) {
    if (check_module($parent."icombos")) {
      $res['icombos'] = rg_view_generate_items_icombos();
    }
  }
  if (isset($report['items']['progr'])) {
    if (check_module($parent."proglist")) {
      $res['proglist'] = rg_view_generate_items_proglist();
    }
    if (check_module($parent."progression")) {
      $res['progression'] = rg_view_generate_items_progression();
    }
  }
  if (isset($report['items']['records'])) {
    if (check_module($parent."irecords")) {
      $res['irecords'] = rg_view_generate_items_irecords();
    }
  }
  
  return $res;
}
