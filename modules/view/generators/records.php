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
            strpos($key, "time_dead") !== FALSE || strpos($key, "_time_") !== FALSE ||
            strpos($key, "shortest") !== FALSE || strpos($key, "longest") !== FALSE ?
              convert_time($record['value']) :
              (
                strpos($key, "time_dead") !== FALSE || strpos($key, "_time_") !== FALSE ?
                convert_time_seconds($record['value']) :
                ( $record['value'] - floor($record['value']) != 0 ? number_format($record['value'], 2) : number_format($record['value'], 0) )
              )
            ).
          "</td><td>". ($record['playerid'] ?
                    ((strstr($key, "_team") !== FALSE || strstr($key, "team_") !== FALSE) ?
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

// <caption>".locale_string($k)."</caption>

  $res .= "<table id=\"records-ext-module-table\" class=\"list\"><thead>
      <tr>".
        "<th width=\"20%\">".locale_string("record")."</th>".
        "<th>".locale_string("match")."</th>".
        "<th>".locale_string("value")."</th>".
        "<th>".locale_string(strpos($k, "_team") != FALSE ? "team" : "player")."</th>".
        "<th width=\"15%\">".locale_string("hero")."</th>".
        (!empty($context_ext) ? "<th width=\"50px\"></th>" : "").
      "</th></thead><tbody>";
  
  foreach ($context as $k => $rec) {
    $res .= "<tr class=\"expandable primary closed\" data-toggle=\"collapse\" data-group=\"records-$k\">".
        "<td>".locale_string($k)."</td>".
        "<td>". ($rec['matchid'] ?
          match_link($rec['matchid']) :
        "")."</td>
        <td>".(
          strpos($k, "duration") !== FALSE || strpos($k, "_len") !== FALSE ||
          strpos($k, "shortest") !== FALSE || strpos($k, "longest") !== FALSE ?
          convert_time($rec['value']) :
          (
            strpos($k, "time_dead") !== FALSE || strpos($k, "_time_") !== FALSE ?
            convert_time_seconds($rec['value']) :
            ( $rec['value'] - floor($rec['value']) != 0 ? number_format($rec['value'], 2) : number_format($rec['value'], 0) )
          )
        )."</td>".
        "<td>". ($rec['playerid'] ?
          ((strpos($k, "_team") !== FALSE || strpos($k, "team_") !== FALSE) ?
            team_link($rec['playerid']) :
            player_link($rec['playerid'])
          ) :
        "")."</td>".
        "<td>".($rec['heroid'] ? hero_full($rec['heroid']) : "")."</td>".
        (!empty($context_ext) ? (
          empty($context_ext[$k]) ? "<td></td>" : "<td><span class=\"expand\"></span></td>"
         ) : "").
      "</tr>";

    if (empty($context_ext[$k])) continue;
    
    foreach ($context_ext[$k] as $i => $record) {
      if (empty($record)) continue;
      $res .= "<tr class=\"collapsed secondary tablesorter-childRow\" data-group=\"records-$k\">".
        "<td>".locale_string($k)." #".($i+2)."</td>".
        "<td>". ($record['matchid'] ?
          match_link($record['matchid']) :
        "")."</td>
        <td>".(
          strpos($k, "duration") !== FALSE || strpos($k, "_len") !== FALSE ||
          strpos($k, "shortest") !== FALSE || strpos($k, "longest") !== FALSE ?
          convert_time($record['value']) :
            (
              strpos($k, "time_dead") !== FALSE || strpos($k, "_time_") !== FALSE ?
              convert_time_seconds($record['value']) :
              ( $record['value'] - floor($record['value']) != 0 ? number_format($record['value'], 2) : number_format($record['value'], 0) )
            )
        )."</td>".
        "<td>". ($record['playerid'] ?
          ((strpos($k, "_team") !== FALSE || strpos($k, "team_") !== FALSE) ?
            team_link($record['playerid']) :
            player_link($record['playerid'])
          ) :
        "")."</td>".
        "<td>".($record['heroid'] ? hero_full($record['heroid']) : "")."</td>".
        "<td></td>".
      "</tr>";
    }

    // $res .= "</tbody>";
  }

  $res .= "</tbody></table>";

  return $res;
}