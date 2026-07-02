<?php 

function itembuild_item_component(&$build, $item, $flags = [], $item_stats = null) {
  if (empty($item)) return '';
  $stats = $item_stats ?? ($build['stats'][$item] ?? null);
  if (empty($stats)) return '';
  $big = $flags['big'] ?? false;
  $critical = $flags['critical'] ?? (!$item_stats && isset($build['critical'][$item]));
  $prate = $flags['prate'] ?? true;

  $tags = [ "build-item-component" ];
  if ($big) $tags[] = "big";
  if ($stats['prate'] > 0.8) $tags[] = "common";
  if ($stats['wo_wr_incr'] > 0.1 || ( !$item_stats && isset($build['critical'][$item]) && $build['critical'][$item]['grad'] > 0 )) $tags[] = "strong";
  if ($critical && ( !$item_stats && isset($build['critical'][$item]) && $build['critical'][$item]['grad'] < 0 )) $tags[] = "critical";

  if ($flags['small'] ?? false) $tags[] = "small";
  if ($flags['smallest'] ?? false) $tags[] = "smallest";

  if (isset($flags['at_time'])) {
    $time = round($flags['at_time'] * 60);
    if (isset($build['critical'][$item])) {
      $wr = $build['critical'][$item]['early_wr'] + $build['critical'][$item]['grad']*(($time - $build['critical'][$item]['q1'])/60);
      $wr_incr = $wr - ($build['critical'][$item]['early_wr'] - $build['critical'][$item]['early_wr_incr']);
    } else {
      $wr = $stats['winrate'];
      $wr_incr = $stats['wo_wr_incr'];
    }
  }

  $incr = $wr_incr ?? ($critical && !$item_stats ? $build['critical'][$item]['early_wr_incr'] : $stats['wo_wr_incr']);
  if ($incr > 0.125) $tags[] = "winrate-strong";
  if ($incr < -0.1) $tags[] = "winrate-weak";

  $is_enchantment = $item_stats !== null;
  if ($is_enchantment) $tags[] = "enchantment";

  $labels = "<div class=\"labels\">".
    (
      $critical && !$item_stats && isset($build['critical'][$item]) && $build['critical'][$item]['grad'] < 0 ?
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
          "<a class=\"item-stat-tooltip item-time-median\" title=\"".addcslashes(locale_string('item_time_median_long'), '"')."\">~ ".convert_time_seconds($stats['med_time'])."</a>".
        "</span>".
        "<a class=\"item-winrate item-stat-tooltip-line item-stat-tooltip item-winrate-increase\" title=\"".addcslashes(locale_string('items_winrate_increase'), '"')."\">".
          (($wr_incr ?? $stats['wo_wr_incr']) > 0 ? '+' : '').number_format(($wr_incr ?? $stats['wo_wr_incr']) * 100, 2).
        "%</a>".
        (
          $critical && !$item_stats ?
          "<a class=\"item-stat-tooltip item-stat-tooltip-line item-winrate-gradient\" title=\"".addcslashes(locale_string('items_wr_gradient'), '"')."\">+".
            number_format($build['critical'][$item]['grad'] * 100, 1).
          "%/min</a>" :
          "<a class=\"item-stat-tooltip item-winrate-avg item-stat-tooltip-line\" title=\"".addcslashes(locale_string('winrate'), '"')."\">".
            number_format(($wr ?? $stats['winrate']) * 100, 2).
          "%</a>"
        )
    ).
    "</div>";

  return "<div class=\"".implode(" ", $tags)."\">".
    "<a class=\"item-image\" title=\"".
      addcslashes(
        item_name($item)." - ".
        locale_string('purchase_rate').": ".number_format($stats['prate'] * 100, 2)."%, ".
        locale_string('items_winrate_increase').": ".number_format($stats['wo_wr_incr'] * 100, 2)."%, ".
        locale_string('winrate').": ".number_format($stats['winrate'] * 100, 2)."%, ".
        locale_string('item_time_median_long').": ".convert_time_seconds($stats['med_time']).
        (!$item_stats && isset($build['critical'][$item]) ? ", ".locale_string('items_wr_gradient').": ".number_format($build['critical'][$item]['grad'] * 100, 1)."%" : "")
      , '"').
      "\">".
      ($big ? item_big_icon($item) : item_icon($item)).
      ($prate ? "<span class=\"item-prate\">".number_format($stats['prate'] * 100, 2)."%</span>" : "").
    "</a>".
    $labels.
  "</div>";
}

function itembuild_item_component_simple($item, $params = []) {
  $big = $params['big'] ?? false;

  $tags = [ "build-item-component" ];
  $stats = [];

  if (isset($params['wr_incr'])) {
    if ($params['wr_incr'] > 0.125) $tags[] = "winrate-strong";
    if ($params['wr_incr'] < -0.1) $tags[] = "winrate-weak";
    $stats[] = locale_string('items_winrate_increase').": ".number_format($params['wr_incr'] * 100, 2)."%";
  }

  if (isset($params['prate'])) {
    $stats[] = locale_string('purchase_rate').": ".number_format($params['prate'] * 100, 2)."%";
  }
  if (isset($params['winrate'])) {
    $stats[] = locale_string('winrate').": ".number_format($params['winrate'] * 100, 2)."%";
  }
  if (isset($params['lane_wr'])) {
    $stats[] = locale_string('lane_wr').": ".number_format($params['lane_wr'] * 100, 2)."%";
  }

  return "<div class=\"".implode(" ", $tags)."\">".
    "<a class=\"item-image\" title=\"".
      addcslashes(
        item_name($item).
        (($params['pcnt'] ?? 1) > 1 ? " #".$params['pcnt'] : "").
        (empty($stats) ? "" : " - ".implode(", ", $stats))
      , '"').
      "\">".
      ($big ? item_big_icon($item) : item_icon($item)).
      (isset($params['prate']) ? "<span class=\"item-prate\">".number_format($params['prate'] * 100, 2)."%</span>" : "").
    "</a>".
  "</div>";
}