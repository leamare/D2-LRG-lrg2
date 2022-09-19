<?php
include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");

function rg_generator_sides($table_id, &$context, $heroes_flag = true) {
  if(!sizeof($context)) return "";

  $elements = [];
  $id = $heroes_flag ? "heroid" : "playerid";
  $keys = [];

  $matches_med = [];

  for ($i=0; $i<2 && empty($keys); $i++) {
      if(isset($context[$i][0])) {
        $keys = array_keys($context[$i][0]);
        break;
      }
  }

  for ($side = 0; $side < 2; $side++) {
    foreach($context[$side] as $el) {
      if (!isset($elements[$el[$id]])) {
        $elements[$el[$id]] = [
          -1 => [
            "matches" => $el['matches']
          ],
          0 => [],
          1 => []
        ];
      } else {
        $elements[$el[$id]][-1]["matches"] += $el['matches'];
      }
      $elements[$el[$id]][$side] = $el;
    }
  }

  foreach($elements as $elid => $el) {
    if(!isset($elements[$elid][1]["winrate"]) || !isset($elements[$elid][0]["winrate"]))
      $elements[$elid][-1]["diff"] = 0;
    else
      $elements[$elid][-1]["diff"] = $elements[$elid][1]["winrate"] - $elements[$elid][0]["winrate"];
    
    $matches_med[] = $el[-1]['matches'];
  }

  uasort($elements, function($a, $b) {
    if($a[-1]['diff'] == $b[-1]['diff']) return 0;
    else return ($a[-1]['diff'] < $b[-1]['diff']) ? 1 : -1;
  });

  sort($matches_med);

  $res = filter_toggles_component($table_id, [
    'match' => [
      'value' => $matches_med[ round(count($matches_med)/2) ] ?? 0,
      'label' => 'data_filter_matches'
    ]
  ], $table_id, 'wide');

  $res .= search_filter_component($table_id, true);

  $res .= "<table id=\"$table_id\" class=\"list wide sortable\"><thead>".
            "<tr class=\"overhead\"><th colspan=\"".(2+$heroes_flag)."\"></th>".
            "<th class=\"separator\" colspan=\"2\">".locale_string("rad_view")."</th>".
            "<th class=\"separator\" colspan=\"".(sizeof($keys)-1)."\">".locale_string("radiant")."</th>".
            "<th class=\"separator\" colspan=\"".(sizeof($keys)-1)."\">".locale_string("dire")."</th></tr>";

  $res .= "<tr>".
            ($heroes_flag ? "<th class=\"sorter-no-parser\" width=\"1%\"></th>" : "").
            "<th data-sortInitialOrder=\"asc\">".locale_string($heroes_flag ? "hero" : "player")."</th>".
            "<th>".locale_string("matches")."</th>".
            "<th class=\"separator\">".locale_string("ratio")."</th>".
            "<th>".locale_string("diff")."</th>";

  for ($side = 1; $side >= 0; $side--) {
    for($k=1, $end=sizeof($keys); $k < $end; $k++) {
      $res .= "<th ".($k==1 ? "class=\"separator\"" : "").">".locale_string($keys[$k])."</th>";
    }
  }
  $res .= "</tr></thead>";
  foreach ($elements as $elid => $el) {
    if(empty($el[0])) {
      $el[0]["matches"] = 0;
      $el[0]["winrate"] = 0;
    }
    if(empty($el[1])) {
      $el[1]["matches"] = 0;
      $el[1]["winrate"] = 0;
    }

    $res .= "<tr data-value-match=\"".$el[-1]['matches']."\">".
      "<td>".($heroes_flag ? hero_portrait($elid)."</td><td>".hero_link($elid) : player_link($elid))."</td>".
      "<td>".$el[-1]['matches']."</td>".
      "<td class=\"separator\">".number_format($el[1]["matches"]*100/$el[-1]["matches"],2)."%</td>".
      "<td>".number_format($el[-1]["diff"]*100,2)."%</td>";

    for ($side = 1; $side >= 0; $side--) {
      $res .= "<td class=\"separator\">".number_format($el[$side][ "matches" ])."</th>";
      $res .= "<td>".number_format($el[$side][ "winrate" ]*100, 2)."%</th>";
      for($k=3, $end=sizeof($keys); $k < $end; $k++) {
        if(!isset($el[$side][ $keys[$k] ])) $el[$side][ $keys[$k] ] = 0;
        $res .= "<td>".number_format($el[$side][ $keys[$k] ], 2)."</th>";
      }
    }
    $res .= "</tr>";
  }
  $res .= "</table>";

  return $res;
}
?>
