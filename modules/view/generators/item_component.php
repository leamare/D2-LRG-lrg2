<?php 

function itembuild_item_component(&$build, $item, $flags = []) {
  $big = $flags['big'] ?? false;
  $critical = $flags['critical'] ?? isset($build['critical'][$item]);
  $prate = $flags['prate'] ?? true;

  $tags = [ "build-item-component" ];
  if ($big) $tags[] = "big";
  if ($build['stats'][$item]['prate'] > 0.8) $tags[] = "common";
  if ($build['stats'][$item]['wo_wr_incr'] > 0.1 || ( isset($build['critical'][$item]) && $build['critical'][$item]['grad'] > 0 )) $tags[] = "strong";
  if ($critical && ( isset($build['critical'][$item]) && $build['critical'][$item]['grad'] < 0 )) $tags[] = "critical";

  if ($flags['small'] ?? false) $tags[] = "small";
  if ($flags['smallest'] ?? false) $tags[] = "smallest";

  if (isset($flags['at_time'])) {
    $time = round($flags['at_time'] * 60);
    if (isset($build['critical'][$item])) {
      $wr = $build['critical'][$item]['early_wr'] + $build['critical'][$item]['grad']*(($time - $build['critical'][$item]['q1'])/60);
      $wr_incr = $wr - ($build['critical'][$item]['early_wr'] - $build['critical'][$item]['early_wr_incr']);
    } else {
      $wr = $build['stats'][$item]['winrate'];
      $wr_incr = $build['stats'][$item]['wo_wr_incr'];
    }
  }

  $incr = $wr_incr ?? ($critical ? $build['critical'][$item]['early_wr_incr'] : $build['stats'][$item]['wo_wr_incr']);
  if ($incr > 0.125) $tags[] = "winrate-strong";
  if ($incr < -0.1) $tags[] = "winrate-weak";

  return "<div class=\"".implode(" ", $tags)."\">".
    "<a class=\"item-image\" title=\"".
      addcslashes(
        item_name($item)." - ".
        locale_string('purchase_rate').": ".number_format($build['stats'][$item]['prate'] * 100, 2)."%, ".
        locale_string('items_winrate_increase').": ".number_format($build['stats'][$item]['wo_wr_incr'] * 100, 2)."%, ".
        locale_string('winrate').": ".number_format($build['stats'][$item]['winrate'] * 100, 2)."%, ".
        locale_string('item_time_median_long').": ".convert_time_seconds($build['stats'][$item]['med_time']).
        (isset($build['critical'][$item]) ? ", ".locale_string('items_wr_gradient').": ".number_format($build['critical'][$item]['grad'] * 100, 1)."%" : "")
      , '"').
      "\">".
      ($big ? item_big_icon($item) : item_icon($item)).
      ($prate ? "<span class=\"item-prate\">".number_format($build['stats'][$item]['prate'] * 100, 2)."%</span>" : "").
    "</a>".
    "<div class=\"labels\">".
    (
      $critical && isset($build['critical'][$item]) && $build['critical'][$item]['grad'] < 0 ?
        "<span class=\"item-time item-stat-tooltip-line\">".
          ( isset($time) ? 
            "<a class=\"item-stat-tooltip item-time-median\" title=\"".addcslashes(locale_string('item_time_median_long'), '"')."\">~ ".convert_time_seconds($time)."</a>" : 
            "<a class=\"item-stat-tooltip item-time-early\" title=\"".addcslashes(locale_string('item_time_q1_long'), '"')."\">".convert_time_seconds($build['critical'][$item]['q1'])."</a> - ".
            "<a class=\"item-stat-tooltip item-time-critical\" title=\"".addcslashes(locale_string('item_time_critical_long'), '"')."\">".convert_time_seconds($build['critical'][$item]['critical_time'])."</a>"
          ).
        "</span>".
        "<a class=\"item-stat-tooltip item-stat-tooltip-line item-winrate item-winrate-increase\" title=\"".addcslashes(locale_string('items_early_wr_long'), '"')."\">".
          (($wr_incr ?? $build['critical'][$item]['early_wr_incr']) > 0 ? '+' : '').number_format(($wr_incr ?? $build['critical'][$item]['early_wr_incr']) * 100, 1).
        "% ▼</a>".
        (isset($time) ? '' :
          "<a class=\"item-stat-tooltip item-stat-tooltip-line item-winrate-gradient\" title=\"".addcslashes(locale_string('items_wr_gradient'), '"')."\">".
            ($build['critical'][$item]['grad'] > 0 ? '+' : '').number_format($build['critical'][$item]['grad'] * 100, 1).
          "%/min</a>"
        )
      : 
        "<span class=\"item-time item-stat-tooltip-line\">".
          "<a class=\"item-stat-tooltip item-time-median\" title=\"".addcslashes(locale_string('item_time_median_long'), '"')."\">~ ".convert_time_seconds($build['stats'][$item]['med_time'])."</a>".
        "</span>".
        "<a class=\"item-winrate item-stat-tooltip-line item-stat-tooltip item-winrate-increase\" title=\"".addcslashes(locale_string('items_winrate_increase'), '"')."\">".
          (($wr_incr ?? $build['stats'][$item]['wo_wr_incr']) > 0 ? '+' : '').number_format(($wr_incr ?? $build['stats'][$item]['wo_wr_incr']) * 100, 2).
        "%</a>".
        (
          $critical ? 
          "<a class=\"item-stat-tooltip item-stat-tooltip-line item-winrate-gradient\" title=\"".addcslashes(locale_string('items_wr_gradient'), '"')."\">+".
            number_format($build['critical'][$item]['grad'] * 100, 1).
          "%/min</a>" :
          "<a class=\"item-stat-tooltip item-winrate-avg item-stat-tooltip-line\" title=\"".addcslashes(locale_string('winrate'), '"')."\">".
            number_format(($wr ?? $build['stats'][$item]['winrate']) * 100, 2).
          "%</a>"
        )
    ).
    "</div>".
  "</div>";
}