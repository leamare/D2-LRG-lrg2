<?php

$modules['items'] = [];

include_once($root."/modules/view/functions/item_name.php");

if (isset($report['items'])) {
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
  if (isset($report['items']['progr']) || isset($report['items']['progrole'])) {
    if (isset($report['items']['progrole'])) {
      // if (!function_exists('itembuild_item_component')) {
      include($root."/modules/view/items/builds.php");
      // }
      include($root."/modules/view/items/buildspowerspikes.php");
    }
    include($root."/modules/view/items/proglist.php");
    include($root."/modules/view/items/progression.php");
  }
}
if (isset($report['starting_items'])) {
  if (isset($report['starting_items']['items'])) {
    include($root."/modules/view/items/starting_items.php");
  }
  if (isset($report['starting_items']['builds'])) {
    include($root."/modules/view/items/starting_builds.php");
  }
  if (isset($report['starting_items']['consumables'])) {
    include($root."/modules/view/items/consumables.php");
  }
}
if (isset($report['items'])) {
  if (isset($report['items']['records'])) {
    include($root."/modules/view/items/irecords.php");
  }
  if (isset($report['items']['stats']) || isset($report['starting_items'])) {
    include($root."/modules/view/items/profiles.php");
  }
}

function rg_view_generate_items() {
  global $report, $mod, $parent, $unset_module, $carryon;

  if($mod == "items") $unset_module = true;
  $parent = "items-";

  $res = [];

  $carryon["/^items-(stitems|stibuilds|sticonsumables|stats|icritical|builds|buildspowerspikes|progression)$/"] = 
    "/^items-(stitems|stibuilds|sticonsumables|stats|icritical|builds|buildspowerspikes|progression)/";

  if (isset($report['items'])) {
    if (isset($report['items']['stats'])) {
      if (check_module($parent."overview")) {
        $res['overview'] = rg_view_generate_items_overview();
      }
      if (check_module($parent."stats")) {
        $res['stats'] = rg_view_generate_items_stats();
      }
      if (check_module($parent."icritical")) {
        $res['icritical'] = rg_view_generate_items_critical();
      }
      if (check_module($parent."bplots")) {
        $res['bplots'] = [
          'boxplots' => [],
          'heroboxplots' => [],
        ];
        
        if($mod == $parent."bplots") $unset_module = true;
        $parent = $parent."bplots-";

        if (check_module($parent."boxplots")) {
          $res['bplots']['boxplots'] = rg_view_generate_items_boxplots();
        }
        if (check_module($parent."heroboxplots")) {
          $res['bplots']['heroboxplots'] = rg_view_generate_items_heroboxplots();
        }
      }
      if (check_module($parent."heroes")) {
        $res['heroes'] = rg_view_generate_items_heroes();
      }
      if (check_module($parent."profiles")) {
        $res['profiles'] = rg_view_generate_items_profiles();
      }
    }
    if (isset($report['items']['combos'])) {
      if (check_module($parent."icombos")) {
        $res['icombos'] = rg_view_generate_items_icombos();
      }
    }
    if (isset($report['items']['progr']) || isset($report['items']['progrole'])) {
      if (check_module($parent."builds") && isset($report['items']['progrole'])) {
        $res['builds'] = rg_view_generate_items_builds();
      }
      if (check_module($parent."buildspowerspikes")) {
        $res['buildspowerspikes'] = rg_view_generate_items_buildspowerspikes();
      }
      if (check_module($parent."progression") || check_module($parent."progrole")) {
        $res['progression'] = rg_view_generate_items_progression();
      }
      if (check_module($parent."proglist")) {
        $res['proglist'] = rg_view_generate_items_proglist();
      }
    }
  }

  if (isset($report['starting_items'])) {
    if (isset($report['starting_items']['builds'])) {
      if (check_module($parent."stibuilds")) {
        $res['stibuilds'] = rg_view_generate_items_sti_builds();
      }
    }
    if (isset($report['starting_items']['items'])) {
      if (check_module($parent."stitems")) {
        $res['stitems'] = rg_view_generate_items_sti_items();
      }
    }
    if (isset($report['starting_items']['consumables'])) {
      if (check_module($parent."sticonsumables")) {
        $res['sticonsumables'] = rg_view_generate_items_sti_consumables();
      }
    }
  }
  
  if (isset($report['items'])) {
    if (isset($report['items']['records'])) {
      if (check_module($parent."irecords")) {
        $res['irecords'] = rg_view_generate_items_irecords();
      }
    }
  }
  
  return $res;
}
