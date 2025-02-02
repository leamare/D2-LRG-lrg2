<?php
include_once($root."/modules/view/functions/ranking.php");

function rg_generator_draft($table_id, &$context_pickban, &$context_draft, $context_total_matches, $hero_flag = true, $disable_bans = false, $ratio = false) {
  $res = ""; $draft = [];
  $id_name = $hero_flag ? "heroid" : "playerid";

  if (is_wrapped($context_pickban)) $context_pickban = unwrap_data($context_pickban);
  if (is_wrapped($context_draft)) $context_draft = unwrap_data($context_draft);

  if(!sizeof($context_pickban)) return "";

  $colspan = 3 + ($disable_bans ? 0 : 2) + ($ratio ? 1 : 0);

  for ($i=0; $i<2; $i++) {
    $type = $i ? "pick" : "ban";
    $max_stage = 1;
    if(!isset($context_draft[$i])) continue;
    foreach($context_draft[$i] as $stage_num => $stage) {
      if ($stage_num > $max_stage) $max_stage = $stage_num;
      
      foreach($context_pickban as $id => $pbel) {
        if(empty($draft[ $id ])) $draft[ $id ] = [];
        if(empty($draft[ $id ][$stage_num]))
          $draft[ $id ][$stage_num] = array ("pick" => 0, "pick_wr" => 0, "ban" => 0, "ban_wr" => 0 );
      }

      foreach($stage as $el) {
        $id = $el[ $id_name ];

        if (!empty($el)) {
          $draft[ $id ][$stage_num][$type] = $el['matches'];
          $draft[ $id ][$stage_num][$type."_wr"] = $el['winrate'];
        }
      }
    }
  }

  foreach($context_pickban as $k => $v) {
    if(isset($v['winrate_picked'])) break;

    if($context_pickban[$k]['matches_picked'])
      $context_pickban[$k]['winrate_picked'] = $context_pickban[$k]['wins_picked'] / $context_pickban[$k]['matches_picked'];
    else
      $context_pickban[$k]['winrate_picked'] = 0;

    if($context_pickban[$k]['matches_banned'])
      $context_pickban[$k]['winrate_banned'] = $context_pickban[$k]['wins_banned'] / $context_pickban[$k]['matches_banned'];
    else
      $context_pickban[$k]['winrate_banned'] = 0;
  }

  $ranks = [];
  
  compound_ranking($context_pickban, $context_total_matches);

  uasort($context_pickban, function($a, $b) {
    return $b['wrank'] <=> $a['wrank'];
  });

  $min = end($context_pickban)['wrank'];
  $max = reset($context_pickban)['wrank'];

  foreach ($context_pickban as $id => $el) {
    $ranks[$id] = round(100 * ($el['wrank']-$min) / ($max-$min), 2);
  }

  $ranks_stages = [];
  for ($i = 1; $i <= $max_stage; $i++) {
    $ranks_stages[$i] = [];
    $scores = [];
    foreach ($draft as $id => $stages) {
      if(isset($stages[$i]) && (($stages[$i]['pick'] ?? 0)+($stages[$i]['ban'] ?? 0)))
        $scores[$id] = [
          "matches_picked" => $stages[$i]['pick'] ?? 0,
          "winrate_picked" => $stages[$i]['pick_wr'] ?? 0,
          "matches_banned" => $stages[$i]['ban'] ?? 0,
          "winrate_banned" => $stages[$i]['ban_wr'] ?? 0,
        ];
    }
    compound_ranking($scores, $context_total_matches);

    uasort($scores, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($scores)['wrank'];
    $max = reset($scores)['wrank'];
  
    foreach ($scores as $id => $el) {
      $draft[$id][$i]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      $ranks_stages[$i][$id] = $draft[$id][$i]['rank'];
    }
  }

  foreach ($draft as $id => $stages) {
    $draftline = "";

    $stages_passed = 0;

    foreach($stages as $stage_num => $stage) {
      if($max_stage > 1) {
        $draftline .= "<td class=\"separator\" data-col-group=\"draft-stage-$stage_num\">".
          (isset($ranks_stages[$stage_num][$id]) ? number_format($ranks_stages[$stage_num][$id],2) : "-")."</td>";
        
        if ($ratio) {
          $ratioval = $context_pickban[$id]['matches_picked'] ? $stage['pick']/$context_pickban[$id]['matches_picked'] : 0;
          $draftline .= "<td data-col-group=\"draft-stage-$stage_num\">".($ratioval ? number_format(100*$ratioval, 2).'%' : '-')."</td>";
        }

        if($stage['pick'] ?? false) {
          $draftline .= "<td data-col-group=\"draft-stage-$stage_num\">".$stage['pick']."</td>".
            "<td data-col-group=\"draft-stage-$stage_num\">".number_format($stage['pick_wr']*100, 2)."%</td>";
        } else {
          $draftline .= "<td data-col-group=\"draft-stage-$stage_num\">&zwnj;-</td><td data-col-group=\"draft-stage-$stage_num\">&zwnj;-</td>";
        }

        if (!$disable_bans) {
          if($stage['ban'] ?? false) {
            $draftline .= "<td data-col-group=\"draft-stage-$stage_num\">".$stage['ban']."</td>".
              "<td data-col-group=\"draft-stage-$stage_num\">".number_format($stage['ban_wr']*100, 2)."%</td>";
          } else {
            $draftline .= "<td data-col-group=\"draft-stage-$stage_num\">&zwnj;-</td><td data-col-group=\"draft-stage-$stage_num\">&zwnj;-</td>";
          }
        }
      }

      $stages_passed++;
    }

    if($stages_passed < $max_stage) {
      for ($i=$stages_passed; $i<$max_stage; $i++)
        $draftline .= "<td class=\"separator\" data-col-group=\"draft-stage-$i\">&zwnj;-</td>".
          "<td data-col-group=\"draft-stage-$i\">&zwnj;-</td>".
          "<td data-col-group=\"draft-stage-$i\">&zwnj;-</td>".
          "<td data-col-group=\"draft-stage-$i\">&zwnj;-</td>".
          "<td data-col-group=\"draft-stage-$i\">&zwnj;-</td>";
    }

    if (empty($context_pickban[$id])) {
      $context_pickban[$id] = [
        'matches_total' => 0,
        'matches_picked' => 0,
        'matches_banned' => 0,
        'winrate_picked' => 0,
        'winrate_banned' => 0,
      ];
    }

    $draft[$id] = array ("out" => "", "matches" => $context_pickban[$id]['matches_total']);
    if($hero_flag)
      $draft[$id]['out'] .= "<td data-col-group=\"_index\">".hero_portrait($id)."</td><td data-col-group=\"_index\">".hero_link($id)."</td>";
    else
      $draft[$id]['out'] .= "<td data-col-group=\"_index\">".player_link($id, true, true)."</td>";

    $draft[$id]['out'] .= "<td class=\"separator\" data-col-group=\"total\">".$context_pickban[$id]['matches_total']."</td>";
    $draft[$id]['out'] .= "<td data-col-group=\"total\">".number_format($ranks[$id] ?? 0, 2)."</td>";

    if($context_pickban[$id]['matches_picked']) {
      $draft[$id]['out'] .= "<td data-col-group=\"total\">".$context_pickban[$id]['matches_picked']."</td>".
        "<td data-col-group=\"total\">".number_format($context_pickban[$id]['winrate_picked']*100, 2)."%</td>";
    } else {
      $draft[$id]['out'] .= "<td data-col-group=\"total\">-</td><td data-col-group=\"total\">-</td>";
    }

    if (!$disable_bans) {
      if($context_pickban[$id]['matches_banned']) {
        $draft[$id]['out'] .= "<td data-col-group=\"total\">".$context_pickban[$id]['matches_banned']."</td>".
          "<td data-col-group=\"total\">".number_format($context_pickban[$id]['winrate_banned']*100, 2)."%</td>";
      } else {
        $draft[$id]['out'] .= "<td data-col-group=\"total\">-</td><td data-col-group=\"total\">-</td>";
      }
    }

    $draft[$id]['out'] .= $draftline."</tr>";
  }


  uasort($draft, function($a, $b) {
    if($a['matches'] == $b['matches']) return 0;
    else return ($a['matches'] < $b['matches']) ? 1 : -1;
  });


  $colgroups = ['total'];
  $priorities = [1];
  for($i=1; $i<=$max_stage; $i++) {
    $colgroups[] = "draft-stage-$i";
    $priorities[] = $i==1 ? 2 : ($i == $max_stage ? 3 : 4);
    register_locale_string( locale_string("stage")." ".$i, "draft-stage-$i" );
  }
  $res .= table_columns_toggle($table_id, $colgroups, true, $priorities);

  $res .= search_filter_component($table_id, true);
  $res .= "<table id=\"$table_id\" class=\"list wide sortable\"><thead><tr class=\"overhead\"><th width=\"11%\"".($hero_flag ? " colspan=\"2\"" : "")."></th>".
          "<th colspan=\"".($colspan + 1 - (int)($ratio))."\" class=\"separator\" data-col-group=\"total\">".locale_string("total")."</th>";
  $heroline = "<tr>".
                ($hero_flag ? "<th class=\"sorter-no-parser\" width=\"1%\" data-col-group=\"_index\"></th>" : "").
                "<th data-sorter=\"text\" data-col-group=\"_index\">".locale_string($hero_flag ? "hero" : "player")."</th>".
                "<th class=\"separator\" data-sorter=\"digit\" data-col-group=\"total\">".locale_string("matches_s")."</th>".
                "<th data-sorter=\"digit\" data-col-group=\"total\">".locale_string("rank")."</th>".
                "<th data-sorter=\"digit\" data-col-group=\"total\">".locale_string("picks_s")."</th>".
                "<th data-sorter=\"digit\" data-col-group=\"total\">".locale_string("winrate_s")."</th>".
                ($disable_bans ? "" :
                  "<th data-sorter=\"digit\" data-col-group=\"total\">".locale_string("bans_s")."</th>".
                  "<th data-sorter=\"digit\" data-col-group=\"total\">".locale_string("winrate_s")."</th>"
                );

  if($max_stage > 1)
    for($i=1; $i<=$max_stage; $i++) {
      $res .= "<th class=\"separator\" colspan=\"".($colspan)."\" data-sorter=\"digit\" data-col-group=\"draft-stage-$i\">".locale_string("draft-stage-$i")."</th>";
      $heroline .= "<th class=\"separator\" data-sorter=\"digit\" data-col-group=\"draft-stage-$i\">".locale_string("rank")."</th>".
                  ($ratio ?
                    "<th data-sorter=\"digit\" data-col-group=\"draft-stage-$i\">".locale_string("ratio")."</th>" : ""
                  ).
                  "<th data-sorter=\"digit\" data-col-group=\"draft-stage-$i\">".locale_string("picks_s")."</th>".
                  "<th data-sorter=\"digit\" data-col-group=\"draft-stage-$i\">".locale_string("winrate_s")."</th>".
                  ($disable_bans ? "" :
                    "<th data-sorter=\"digit\" data-col-group=\"draft-stage-$i\">".locale_string("bans_s")."</th>".
                    "<th data-sorter=\"digit\" data-col-group=\"draft-stage-$i\">".locale_string("winrate_s")."</th>"
                  );
    }
  $res .= "</tr>".$heroline."</tr></thead><tbody>";

  unset($heroline);

  foreach($draft as $hero)
    $res .= $hero['out'];

  $res .= "</tbody></table>";

  if ($hero_flag)
    $res = rg_draft_accuracy_test($context_pickban, $context_draft).$res;

  return $res;
}

define("INACCURATE_DRAFT_MESSAGE", "<div class=\"content-text\">".locale_string("desc_inaccurate_draft")."</div>");

function rg_draft_accuracy_test(&$context_pickban, &$context_draft) {
  if (empty($context_draft[1][3])) return INACCURATE_DRAFT_MESSAGE;

  $ratios = [];
  
  foreach ($context_draft[1][3] as $dr) {
    $total = $context_pickban[ $dr['heroid'] ]['matches_picked'];
    $stage = $dr['matches'];
    $ratios[] = $stage/$total;
  }
  $res = array_sum($ratios) / sizeof($ratios);
  if ($res < 0.15) return INACCURATE_DRAFT_MESSAGE;
  return "";
}

function rg_pbdraft_accuracy_test(&$context_pickban) {
  $set = 0;
  
  foreach ($context_pickban as $dr) {
    if (isset($dr['matches_banned'])) $set++;
  }
  
  if (!$set) return INACCURATE_DRAFT_MESSAGE;
  return "";
}

?>
