<?php

$modules['items']['proglist'] = [];

function rg_view_generate_items_proglist() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings;

  if($mod == $parent."proglist") $unset_module = true;
  $parent_module = $parent."proglist-";
  $res = [];

  if (is_wrapped($report['items']['progr'])) {
    $report['items']['progr'] = unwrap_data($report['items']['progr']);
  }

  $res = [];

  $hnames = $meta["heroes"];

  uasort($hnames, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  foreach($hnames as $hid => $name) {
    $strings['en']["heroid".$hid] = hero_name($hid);
    $res["heroid".$hid] = "";

    if(check_module($parent_module."heroid".$hid)) {
      $hero = $hid;
      $tag = "heroid$hid";
    }
  }

  $pairs = [];
  if (!isset($report['items']['progr'][$hero])) $report['items']['progr'][$hero] = [];
  foreach ($report['items']['progr'][$hero] as $v) {
    if (empty($v)) continue;
    $pairs[] = $v;
  }

  usort($pairs, function($a, $b) {
    return $b['total'] <=> $a['total'];
  });

  if (empty($pairs)) {
    $res[$tag] .= "<div class=\"content-text\">".locale_string("items_stats_empty")."</div>";
    return $res;
  }

  $res[$tag] .= "<div class=\"content-text\">".locale_string("items_progression_list_desc")."</div>";

  $res[$tag] .= "<div class=\"content-text\">".locale_string("total").": ".sizeof($pairs)."</div>";

  $res[$tag] .= "<table id=\"items-proglist-$tag\" class=\"list sortable\">";
  $res[$tag] .= "<thead><tr class=\"overhead\">".
      "<th width=\"18%\" colspan=\"2\">".locale_string("item")." 1</th>".
      "<th width=\"18%\" colspan=\"2\">".locale_string("item")." 2</th>".
      "<th>".locale_string("matches")."</th>".
      "<th>".locale_string("winrate")."</th>".
      "<th>".locale_string("items_minute_diff")."</th>".
    "</tr></thead><tbody>";

  foreach ($pairs as $line) {
    $res[$tag] .= "<tr>".
      "<td>".item_icon($line['item1'])."</td>".
      "<td>".item_name($line['item1'])."</td>".
      "<td>".item_icon($line['item2'])."</td>".
      "<td>".item_name($line['item2'])."</td>".
      "<td>".$line['total']."</td>".
      "<td>".number_format(100*$line['winrate'], 2)."%</td>".
      "<td>".number_format($line['min_diff'], 1)."</td>".
    "</tr>";
  }

  $res[$tag] .= "</tbody></table>";

  return $res;
}

