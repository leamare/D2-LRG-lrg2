<?php

function rg_generator_tickets_list($mod_id, $tickets_data) {
  global $league_logo_banner_provider;

  $res = "<div class=\"content-section\">";
  $res .= "<div class=\"content-header\">".locale_string("tickets")."</div>";
  
  if (empty($tickets_data)) {
    $res .= "<div class=\"content-text\">".locale_string("no_data")."</div>";
    $res .= "</div>";
    return $res;
  }
  
  // Sort by number of matches descending
  uasort($tickets_data, function($a, $b) {
    return $b['matches'] <=> $a['matches'];
  });
  
  $res .= "<table class=\"list sortable\">";
  $res .= "<thead><tr>";
  $res .= "<th>".locale_string("league_logo")."</th>";
  $res .= "<th>".locale_string("league_name")."</th>";
  $res .= "<th>".locale_string("league_id")."</th>";
  $res .= "<th>".locale_string("matches")."</th>";
  $res .= "</tr></thead>";
  $res .= "<tbody>";
  
  foreach ($tickets_data as $lid => $data) {
    global $linkvars;
    $linkvars_str = is_array($linkvars) ? http_build_query($linkvars) : $linkvars;

    $league_name = "none";
    if ($lid && $lid > 0) {
      if (!empty($data['name'])) {
        $league_name = htmlspecialchars($data['name']);
      } else {
        $league_name = "League #$lid";
      }
    }

    $res .= "<tr>";
    $res .= "<td class=\"lid-logo banner-row\">".
      "<img class=\"event-logo-list\" src=\"".str_replace('%LID%', $lid, $league_logo_banner_provider)."\" alt=\"$lid\" />".
    "</td>";
    $res .= "<td>$league_name</td>";
    $res .= "<td><a href=\"?lid=$lid".(empty($linkvars_str) ? "" : "&".$linkvars_str)."\">$lid</a></td>";
    $res .= "<td>".$data['matches']."</td>";
    $res .= "</tr>";
  }
  
  $res .= "</tbody></table>";
  $res .= "</div>";
  
  return $res;
}

