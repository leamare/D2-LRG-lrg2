<?php
include_once($root."/modules/view/functions/player_name.php");
include_once($root."/modules/view/functions/team_name.php");
include_once($root."/modules/view/functions/links.php");
include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/convert_time.php");

function rg_generator_records(&$context) {
  if(!sizeof($context)) return "";

  $res = "<table id=\"records-module-table\" class=\"list sortable\"><thead>".
            "<th>".locale_string("record")."</th>".
            "<th>".locale_string("match")."</th>".
            "<th>".locale_string("value")."</th>".
            "<th>".locale_string("player")."</th>".
            "<th>".locale_string("hero")."</th></tr></thead>";
  foreach($context as $key => $record) {
    $res .= "<tr><td>".locale_string($key)."</td>
          <td>". ($record['matchid'] ?
                    match_link($record['matchid']) :
               "")."</td>
          <td>".(
            strpos($key, "duration") !== FALSE || strpos($key, "_len") !== FALSE ||
            strpos($key, "shortest") !== FALSE || strpos($key, "longest") !== FALSE ?
              convert_time($record['value']) :
              ( $record['value'] - floor($record['value']) != 0 ? number_format($record['value'], 2) : number_format($record['value'], 0) )
            ).
          "</td><td>". ($record['playerid'] ?
                    (strstr($key, "_team") != FALSE ?
                      team_link($record['playerid']) :
                      player_link($record['playerid'])
                    ) :
               "")."</td>
          <td>".($record['heroid'] ? hero_full($record['heroid']) : "").
         "</td>
      </tr>";
  }

  $res .= "</table>";

  return $res;
}

function rg_generator_records_ext(&$context, &$context_ext) {
  if (is_wrapped($context_ext)) {
    $context_ext = unwrap_data($context_ext);
  }

  $records = [];
  
  foreach ($context as $k => $rec) {
    $records[$k] = array_merge([ $rec ], $context_ext[$k] ?? []);
  }

  $res = "";

  foreach ($records as $k => $vals) {
    $res .= "<table id=\"records-ext-module-table-$k\" class=\"list\">
      <caption>".locale_string($k)."</caption><thead>".
      "<th>".locale_string("match")."</th>".
      "<th>".locale_string("value")."</th>".
      "<th>".locale_string(strpos($k, "_team") != FALSE ? "team" : "player")."</th>".
      "<th>".locale_string("hero")."</th></tr></thead><tbody>";
    foreach ($vals as $record) {
      $res .= "<tr><td>". ($record['matchid'] ?
                    match_link($record['matchid']) :
               "")."</td>
          <td>".(
            strpos($k, "duration") !== FALSE || strpos($k, "_len") !== FALSE ||
            strpos($k, "shortest") !== FALSE || strpos($k, "longest") !== FALSE ?
              convert_time($record['value']) :
              ( $record['value'] - floor($record['value']) != 0 ? number_format($record['value'], 2) : number_format($record['value'], 0) )
            ).
          "</td><td>". ($record['playerid'] ?
                    (strpos($k, "_team") != FALSE ?
                      team_link($record['playerid']) :
                      player_link($record['playerid'])
                    ) :
               "")."</td>
          <td>".($record['heroid'] ? hero_full($record['heroid']) : "").
         "</td>
      </tr>";
    }
    $res .= "</tbody></table>";
  }

  return $res;
}