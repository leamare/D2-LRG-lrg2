<?php

// $modules['items']['proglist'] = [];

function rg_view_generate_items_proglist() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $leaguetag;

  if($mod == $parent."proglist") $unset_module = true;
  $parent_module = $parent."proglist-";

  if (is_wrapped($report['items']['progr'])) {
    $report['items']['progr'] = unwrap_data($report['items']['progr']);
  }

  $res = [
    'overview' => [ 'all' => '' ]
  ];

  $hnames = $meta["heroes"];

  uasort($hnames, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  if(check_module($parent_module."overview")) {
    $hero = 'total';
    $tag = "overview";
  }
  foreach($hnames as $hid => $name) {
    $strings['en']["heroid".$hid] = hero_name($hid);
    if (empty($report['items']['progr'][$hid]) && empty($report['items']['progrole']['data'][$hid])) {
      $res["heroid".$hid] = "";
    } else {
      $res["heroid".$hid] = [];
      $res["heroid".$hid]["all"] = "";
    }

    if(check_module($parent_module."heroid".$hid)) {
      if (empty($report['items']['progr'][$hid]) && empty($report['items']['progrole']['data'][$hid])) {
        $res["heroid".$hid] = [ 'all' => "" ];
      }

      $hero = $hid;
      $tag = "heroid$hid";

      if (empty($report['items']['progrole']['data'][$hid])) continue;

      if($mod == $parent_module."heroid".$hid) $unset_module = true;
      $parent_module = $parent_module."heroid".$hid."-";

      $crole = null;

      if (check_module($parent_module."all")) {
        $crole = null;
      }

      if (isset($report['items']['progrole']) && !empty($report['items']['progrole']['data'][$hid])) {
        generate_positions_strings();
        foreach($report['items']['progrole']['data'][$hid] as $role => $pairs) {
          $res["heroid".$hid]["position_".$role] = "";

          if(check_module($parent_module."position_".$role)) {
            $crole = $role;
          }
        }
      }
    }
  }

  $pairs = [];
  if (empty($crole)) {
    if (!isset($report['items']['progr'][$hero])) $context = [];
    else $context =& $report['items']['progr'][$hero];
  } else {
    if (!isset($report['items']['progrole']['data'][$hero][$crole])) $context = [];
    else $context =& $report['items']['progrole']['data'][$hero][$crole];
  }
  
  foreach ($context as $v) {
    if (empty($v)) continue;

    if ($crole ?? false) {
      if (!is_array($v)) continue;
      $v = array_combine($report['items']['progrole']['keys'], $v);
    }
    $pairs[] = $v;
  }

  usort($pairs, function($a, $b) {
    return $b['total'] <=> $a['total'];
  });


  if (empty($pairs)) {
    $res[$tag][($crole ?? false) ? "position_".$crole : "all"] = "<div class=\"content-text\">".locale_string("items_stats_empty")."</div>";
    return $res;
  }

  $reslocal = "";


  $reslocal .= "<div class=\"selector-modules-level-5\">".
    "<span class=\"selector\">".
      "<a href=\"?league=".$leaguetag."&mod=items-progression-$tag-".(($crole ?? false) ? "position_".$crole : "all").(empty($linkvars) ? "" : "&".$linkvars)."\">".
        locale_string("items_progression_as_tree").
      "</a>".
    "</span>".
    " | ".
    "<span class=\"selector active\">".
      "<a href=\"?league=".$leaguetag."&mod=items-proglist-$tag-".(($crole ?? false) ? "position_".$crole : "all").(empty($linkvars) ? "" : "&".$linkvars)."\">".
        locale_string("items_progression_as_list").
      "</a>".
    "</span>".
  "</div>";

  $reslocal .= "<div class=\"content-text\">".locale_string("items_progression_list_desc")."</div>";

  $reslocal .= "<div class=\"content-text\">".locale_string("total").": ".sizeof($pairs)."</div>";

  $reslocal .= "<table id=\"items-proglist-$tag\" class=\"list sortable\">";
  $reslocal .= "<thead><tr class=\"overhead\">".
      "<th width=\"18%\" colspan=\"2\">".locale_string("item")." 1</th>".
      "<th width=\"18%\" colspan=\"2\">".locale_string("item")." 2</th>".
      "<th>".locale_string("matches")."</th>".
      "<th>".locale_string("winrate")."</th>".
      "<th>".locale_string("items_minute_diff")."</th>".
      (isset($pairs[0]['avgord1']) ? "<th>".locale_string("avgord1")."</th>" : "").
      (isset($pairs[0]['avgord2']) ? "<th>".locale_string("avgord2")."</th>" : "").
    "</tr></thead><tbody>";

  foreach ($pairs as $line) {
    $reslocal .= "<tr>".
      "<td>".item_icon($line['item1'])."</td>".
      "<td>".item_link($line['item1'])."</td>".
      "<td>".item_icon($line['item2'])."</td>".
      "<td>".item_link($line['item2'])."</td>".
      "<td>".$line['total']."</td>".
      "<td>".number_format(100*$line['winrate'], 2)."%</td>".
      "<td>".number_format($line['min_diff'], 1)."</td>".
      (isset($line['avgord1']) ? "<td>".number_format($line['avgord1'], 1)."</td>" : "").
      (isset($line['avgord2']) ? "<td>".number_format($line['avgord2'], 1)."</td>" : "").
    "</tr>";
  }

  $reslocal .= "</tbody></table>";

  $res[$tag][($crole ?? false) ? "position_".$crole : "all"] .= $reslocal;

  return $res;
}

