<?php
include_once("$root/modules/view/functions/team_name.php");

function rg_generator_tvt_grid($table_id, &$context, $teams_interest = []) {
  if(!sizeof($context)) return "";
  
  $team_ids = array_keys($context);

  $res = "<table id=\"$table_id\" class=\"pvp wide\">";

  $res .= "<thead><tr><th></th>";
  foreach($context as $tid => $data) {
    if (!empty($teams_interest) && !in_array($tid, $teams_interest)) continue;
    $res .= "<th><span>".team_tag($tid)."</span></th>";
  }
  $res .= "</tr></thead>";

  foreach($context as $tid => $teamline) {
    if (!empty($teams_interest) && !in_array($tid, $teams_interest)) continue;
    $res .= "<tr><td>".team_name($tid)."</td>";
    for($i=0, $end = sizeof($team_ids); $i<$end; $i++) {
      if (!empty($teams_interest) && !in_array($team_ids[$i], $teams_interest)) continue;
      if($tid == $team_ids[$i]) {
        $res .= "<td class=\"transparent\"></td>";
      } else if($teamline[$team_ids[$i]]['matches'] == 0) {
        $res .= "<td>-</td>";
      } else {
        $alert = locale_string("matches").": ".$context[$tid][$team_ids[$i]]['matches']
        ."<br />".locale_string("winrate").": ".number_format($context[$tid][$team_ids[$i]]['winrate'] * 100, 2)
        ."%<br />".locale_string("won")." ".$context[$tid][$team_ids[$i]]['won']." - "
          .locale_string("lost")." ".$context[$tid][$team_ids[$i]]['lost'];

        if (isset($context[$tid][$team_ids[$i]]['matchids'])) {
          $alert .= "<br />".locale_string("matches").": <br />";
          
          $sz = $context[$tid][$team_ids[$i]]['matches'] - 1;
          foreach($context[$tid][$team_ids[$i]]['matchids'] as $n => $mid) {
            $alert .= match_link($mid).($n < $sz ? ", " : "");
          }
        }
        
        $res .= "<td".
                ($teamline[$team_ids[$i]]['winrate'] > 0.55 ? " class=\"high-wr\"" : (
                      $teamline[$team_ids[$i]]['winrate'] < 0.45 ? " class=\"low-wr\"" : ""
                    )
                  )." onclick=\"showModal('".addcslashes(htmlspecialchars($alert), "'").
                        "','".addcslashes(team_name($tid)." ".locale_string("vs")." ".team_name($team_ids[$i]), "'")."')\">".
                        number_format($teamline[$team_ids[$i]]['winrate']*100,0)."</td>";
      }
    }
    $res .= "</tr>";
  }

  $res .= "</table>";

  return $res;
}

?>
