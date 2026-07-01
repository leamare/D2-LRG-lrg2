<?php

global $root;

foreach (['helpers', 'config', 'series', 'roster', 'split', 'rounds', 'phases', 'boundaries', 'stage', 'analyze', 'postpass', 'group', 'bracket'] as $f) {
  require_once $root . "/modules/view/functions/bracket/$f.php";
}

function bracket_available() {
  global $report;
  return is_array($report) && !empty($report['teams']) && !empty($report['tvt'])
    && !empty($report['match_participants_teams']) && !empty($report['matches_additional']);
}

function bracket_team_index() {
  global $report;

  $teams = [];

  foreach (($report['teams'] ?? []) as $id => $t) {
    $id = (int)$id;

    if (!$id) {
      continue;
    }

    $teams[$id] = [
      'id'   => $id,
      'name' => html_entity_decode($t['name'] ?? "Team $id", ENT_QUOTES | ENT_HTML5, 'UTF-8'),
      'tag'  => html_entity_decode($t['tag'] ?? (string)$id, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    ];
  }

  return $teams;
}

function bracket_report_matches() {
  global $report, $meta;

  $teams    = $report['teams'] ?? [];
  $mpt      = $report['match_participants_teams'] ?? [];
  $add      = $report['matches_additional'] ?? [];
  $slots    = $report['matches'] ?? [];
  $pst      = $report['match_parts_series_tag'] ?? [];

  $clusters = $meta['clusters'];

  $tinfo = function ($tid) use ($teams) {
    $tid = (int)$tid;
    if (!$tid) return ['team_id' => 0];
    $t = $teams[$tid] ?? null;
    return [
      'team_id'   => $tid,
      'team_name' => $t['name'] ?? "Team $tid",
      'team_tag'  => $t['tag'] ?? (string)$tid,
    ];
  };

  $matches = [];
  foreach ($mpt as $mid => $sides) {
    $m = $add[$mid] ?? null;
    if (!$m) continue;
    $rid = (int)($sides['radiant'] ?? 0);
    $did = (int)($sides['dire'] ?? 0);
    if (!$rid || !$did) continue;

    $players = ['radiant' => [], 'dire' => []];
    foreach (($slots[$mid] ?? []) as $pl) {
      $side = !empty($pl['radiant']) ? 'radiant' : 'dire';
      $players[$side][] = ['player_id' => (int)($pl['player'] ?? 0)];
    }

    $matches[] = [
      'match_id'    => (int)$mid,
      'date'        => (int)($m['date'] ?? 0),
      'region'      => (int)($clusters[(int)($m['cluster'] ?? 0)] ?? 0),
      'lid'         => (int)($m['lid'] ?? 0),
      'series_id'   => (int)($m['seriesid'] ?? 0),
      'series_tag'  => (string)($pst[$mid] ?? ''),
      'series_num'  => 0,
      'string'      => '',
      'radiant_win' => !empty($m['radiant_win']),
      'teams'       => ['radiant' => $tinfo($rid), 'dire' => $tinfo($did)],
      'players'     => $players,
    ];
  }
  return $matches;
}

// Render precomputed bracket
function bracket_precomputed() {
  global $report;
  $pre = $report['bracket'] ?? null;
  if (empty($pre['events'])) return null;

  $cards  = bracket_team_index();
  $events = [];
  foreach ($pre['events'] as $ev) {
    $stages = [];
    foreach (($ev['stages'] ?? []) as $st) {
      $type = $st['type'] ?? 'bracket';
      $stages[] = in_array($type, ['group', 'group_stage'], true)
        ? bracket_pre_group($st)
        : bracket_pre_playoff($st);
    }
    $events[] = [
      'name'       => $ev['name'] ?? $report['league_name'] ?? 'Tournament',
      'report'     => $report['league_tag'] ?? '',
      'stages'     => $stages,
      'team_cards' => $cards,
    ];
  }
  return [
    'events' => $events,
  ];
}

// One precomputed series -> internal series object. Accepts {teams:[a,b],
// score:[x,y], bo?, mids?, date?, winner?, flags?}.
function bracket_pre_series($s) {
  static $n = 0;
  $teams = array_values(array_map('intval', $s['teams'] ?? []));
  $sc    = array_values($s['score'] ?? []);
  $score = [];
  foreach ($teams as $i => $t) $score[$t] = (int)($sc[$i] ?? 0);
  $a = $teams[0] ?? 0;
  $b = $teams[1] ?? 0;
  $winner = $s['winner'] ?? (($score[$a] ?? 0) !== ($score[$b] ?? 0)
    ? (($score[$a] ?? 0) > ($score[$b] ?? 0) ? $a : $b) : null);
  return [
    'key'    => 'pre' . (++$n),
    'teams'  => $teams,
    'score'  => $score,
    'winner' => $winner,
    'bo'     => (int)($s['bo'] ?? 0),
    'start'  => (int)($s['date'] ?? $s['start'] ?? 0),
    'mids'   => array_values(array_map('intval', $s['mids'] ?? [])),
    'flags'  => array_values($s['flags'] ?? []),
  ];
}

// Precomputed group stage. standings rows: {team,w,d,l,mw,ml}.
function bracket_pre_group($st) {
  $groups = [];
  foreach (($st['groups'] ?? [$st]) as $g) {
    $standings = [];
    foreach (($g['standings'] ?? []) as $r) {
      $standings[] = [
        'team' => (int)($r['team'] ?? 0),
        'w'  => (int)($r['w'] ?? 0), 'd' => (int)($r['d'] ?? 0), 'l' => (int)($r['l'] ?? 0),
        'mw' => (int)($r['mw'] ?? 0), 'ml' => (int)($r['ml'] ?? 0),
      ];
    }
    $groups[] = [
      'name'          => $g['name'] ?? '',
      'format'        => $g['format'] ?? 'round_robin',
      'teams'         => array_column($standings, 'team'),
      'standings'     => $standings,
      'round_results' => [],
      'grid'          => null,
    ];
  }
  return [
    'type' => 'group_stage',
    'phase_type' => 'group',
    'name' => $st['name'] ?? 'bracket_group_stage',
    'groups' => $groups,
  ];
}

// Precomputed playoff. rounds: [{name, series:[...]}]; keys upper/lower/grand_final.
function bracket_pre_playoff($st) {
  $rounds = function ($list) {
    $out = [];
    foreach (($list ?? []) as $r) {
      $out[] = [
        'name' => $r['name'] ?? '',
        'series' => array_map('bracket_pre_series', $r['series'] ?? []),
      ];
    }
    return $out;
  };
  $ub = $rounds($st['upper'] ?? $st['ub_rounds'] ?? []);
  $lb = $rounds($st['lower'] ?? $st['lb_rounds'] ?? []);
  $gf = array_map('bracket_pre_series', $st['grand_final'] ?? []);
  return [
    'type' => 'playoff',
    'phase_type' => 'bracket',
    'name' => $st['name'] ?? 'bracket_main_event',
    'bracket' => [
      'type'        => $st['format'] ?? ($lb ? 'double_elimination' : 'single_elimination'),
      'ub_rounds'   => $ub,
      'lb_rounds'   => $lb,
      'grand_final' => $gf ? ['series' => $gf] : null,
      'ub_to_lb'    => $st['ub_to_lb'] ?? [],
      'unplaced'    => [],
    ]
  ];
}

function bracket_generate() {
  global $report, $meta;

  $pre = bracket_precomputed();
  if ($pre !== null) return $pre;

  $matches = bracket_report_matches();

  if (count($matches) < 2) return [
    'events' => [],
  ];

  tb_fix_split_rosters($matches);
  tb_merge_roster_aliases($matches);

  $teams = bracket_team_index();

  $desc = [
    'tag'      => $report['league_tag'] ?? '',
    'name'     => $report['league_name'] ?? $report['league_tag'] ?? 'event',
    'interest' => $report['teams_interest'] ?? null,
    'tickets'  => $report['tickets'] ?? [],
  ];

  $config = bracket_config();
  $series = tb_build_series($matches, [], $teams);
  $series = tb_apply_overrides($series, $config);
  $events = tb_split_events($series, $desc, $config);

  $result = [
    'events' => [],
  ];

  foreach ($events as $ev) {
    $analyzed = tb_analyze_event($ev, $teams, $config);
    // Season-aggregate sub-events are only kept when they reconstruct into a
    // coherent bracket; the rest stay covered by the performance grid.
    if (($ev['mode'] ?? '') === 'aggregate_sub' && !tb_event_is_coherent($ev, $analyzed)) {
      continue;
    }
    foreach (tb_split_division_events($ev, $analyzed, $teams) as $sub) {
      $result['events'][] = $sub;
    }
  }

  return $result;
}

function bracket_config() {
  global $report;

  $c = $report['settings']['bracket'] ?? null;
  if (!is_array($c)) {
    return [];
  }

  $config = [];
  if (!empty($c['overrides']) && is_array($c['overrides'])) {
    foreach ($c['overrides'] as $k => $v) {
      $config['overrides'][(string)$k] = (int)$v;
    }
  }
  if (!empty($c['stages']) && is_array($c['stages'])) {
    $config['stages'] = tb_normalize_stages($c['stages']);
  }

  if (!empty($c['divisions']) && is_array($c['divisions'])) {
    foreach ($c['divisions'] as $name => $spec) {
      $config['divisions'][(string)$name] = tb_normalize_division((array)$spec);
    }
  }
  if (array_key_exists('months', $c)) {
    $config['months'] = !in_array(strtolower((string)$c['months']), ['0', 'false', 'no', 'off', ''], true);
  }
  
  return $config;
}

function bracket_team_obj($id, $cards) {
  $id = (int)$id;
  return [
    'id'   => $id,
    'tag'  => $cards[$id]['tag']  ?? (string)$id,
    'name' => $cards[$id]['name'] ?? "Team $id",
  ];
}

function bracket_series_obj($s, $cards) {
  $teams  = array_values($s['teams']);
  $scores = array_map(fn($t) => (int)($s['score'][$t] ?? 0), $teams);
  return [
    'teams'   => array_map(fn($t) => bracket_team_obj($t, $cards), $teams),
    'scores'  => $scores,
    'winner'  => $s['winner'] ?? null,
    'bo'      => $s['bo'] ?? 0,
    'date'    => $s['start'] ?? 0,
    'matches' => array_values(array_map('intval', $s['mids'] ?? [])),
    'flags'   => array_values($s['flags'] ?? []),
  ];
}

function bracket_rounds_obj($rounds, $cards) {
  return array_map(fn($r) => [
    'name'   => bracket_name($r['name'] ?? ''),
    'series' => array_map(fn($s) => bracket_series_obj($s, $cards), $r['series'] ?? []),
  ], $rounds);
}

function bracket_json($result) {
  $events = [];
  foreach ($result['events'] as $ev) {
    $cards  = $ev['team_cards'] ?? [];
    $stages = [];

    foreach ($ev['stages'] as $st) {
      $stage = ['type' => $st['type'], 'name' => bracket_name($st['name'] ?? '')];

      if ($st['type'] === 'group_stage') {
        $stage['groups'] = [];
        foreach ($st['groups'] as $g) {
          $standings = [];
          foreach ($g['standings'] as $rank => $row) {
            $standings[] = [
              'rank'       => $rank + 1,
              'team'       => bracket_team_obj($row['team'], $cards),
              'wins'       => $row['w'], 'draws' => $row['d'], 'losses' => $row['l'],
              'map_wins'   => $row['mw'], 'map_losses' => $row['ml'],
            ];
          }
          $stage['groups'][] = ['name' => bracket_name($g['name']), 'format' => $g['format'], 'standings' => $standings];
        }
        if (!empty($st['tiebreakers']))
          $stage['tiebreakers'] = array_map(fn($s) => bracket_series_obj($s, $cards), $st['tiebreakers']);
        if (!empty($st['decider']['series']))
          $stage['decider'] = array_map(fn($s) => bracket_series_obj($s, $cards), $st['decider']['series']);

      } elseif ($st['type'] === 'playoff') {
        $b = $st['bracket'];
        $stage['phase_type'] = $st['phase_type'] ?? 'bracket';
        $stage['format']     = $b['type'];
        $stage['upper']      = bracket_rounds_obj($b['ub_rounds'] ?? [], $cards);
        $stage['lower']      = bracket_rounds_obj($b['lb_rounds'] ?? [], $cards);
        $stage['grand_final'] = !empty($b['grand_final'])
          ? bracket_rounds_obj([$b['grand_final']], $cards)[0] : null;
        if (!empty($b['unplaced']))
          $stage['unplaced'] = array_map(fn($s) => bracket_series_obj($s, $cards), $b['unplaced']);

      } else {
        $stage['series'] = array_map(fn($s) => bracket_series_obj($s, $cards), $st['series'] ?? []);
      }

      $stages[] = $stage;
    }

    $placements = [];
    $place = 1;
    foreach (tb_event_placements($ev['stages']) as $grp) {
      $placements[] = [
        'place_from' => $place,
        'place_to'   => $place + count($grp) - 1,
        'teams'      => array_map(fn($t) => bracket_team_obj($t, $cards), $grp),
      ];
      $place += count($grp);
    }

    $events[] = [
      'name' => $ev['name'],
      'stages' => $stages,
      'placements' => $placements,
    ];
  }

  return ['events' => $events];
}

const BRACKET_META_H = 18;
const BRACKET_ROW_H  = 22;
const BRACKET_CARD_H = 62;
const BRACKET_SLOT_H = 80;
const BRACKET_COL_W  = 200;
const BRACKET_CONN_W = 26;

function bracket_esc($v) {
  return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bracket_label($tid) {
  $tag = team_tag($tid);
  return $tag !== '' ? $tag : team_name($tid);
}

function bracket_vs_title($a, $b) {
  return team_name($a)." ".locale_string("bracket_vs")." ".team_name($b);
}

// Series popup title
function bracket_popup_title($a, $b, $ctx = '') {
  return bracket_vs_title($a, $b).($ctx !== '' ? ", $ctx" : "");
}

function bracket_flag($s) {
  $map = [
    'outcome-fixed' => 'bracket_flag_fixed',
    'overridden'    => 'bracket_flag_override',
    'tech-loss'     => 'bracket_flag_tech',
  ];

  foreach ($map as $f => $k) {
    if (in_array($f, $s['flags'] ?? [], true)) {
      return " <span class=\"bracket-flag\" title=\"".bracket_esc(locale_string($k))."\">⚠</span>";
    }
  }

  return '';
}

function bracket_name($name) {
  if (!preg_match('/^(bracket_[a-z_]+)(?: ([0-9A-Z]+))?$/', (string)$name, $m)) {
    return $name;
  }

  $s = locale_string($m[1]);
  if (!isset($m[2])) {
    return $s;
  }

  return strpos($s, '%n%') !== false ? str_replace('%n%', $m[2], $s) : trim($s.' '.$m[2]);
}

function bracket_render() {
  global $bracket_popups, $report;
  if (empty($report['bracket']) && !bracket_available()) {
    return "";
  }

  $result = bracket_generate();
  if (empty($result['events'])) {
    return "";
  }

  $bracket_popups = [];
  $multi = count($result['events']) > 1;

  $out = "<div class=\"content-text alert info bracket-notice\">".locale_string("bracket_notice")."</div>";
  foreach ($result['events'] as $ev) {
    $out .= bracket_render_event($ev, $multi);
  }

  if ($bracket_popups) {
    $out .= "<div class=\"bracket-popups\">";
    foreach ($bracket_popups as $id => $p) {
      $out .= "<div id=\"$id\" data-title=\"".bracket_esc($p['title'])."\">".$p['body']."</div>";
    }
    $out .= "</div>";
  }
  return "<div class=\"content-text wide left bracket\">$out</div>";
}

function bracket_render_event($ev, $multi) {
  global $report;
  $out = "";
  if ($multi) {
    $title = $ev['name'];
    $base  = $report['league_name'] ?? '';
    if ($base !== '' && str_starts_with($title, $base.', ')) {
      $title = substr($title, strlen($base.', '));
    }
    $out .= "<h1 class=\"content-header\">".bracket_esc($title)."</h1>";
  }
  $out .= bracket_render_placements($ev);

  $event = $ev['name'];
  foreach ($ev['stages'] as $st) {
    if ($st['type'] === 'group_stage') {
      $out .= bracket_render_group_stage($st, $event);
    } else if ($st['type'] === 'playoff') {
      $out .= bracket_render_playoff($st, $event);
    } else {
      $stage = bracket_name($st['name']);
      $out .= "<h2>".bracket_esc($stage)."</h2><div class=\"bracket-row\">";
      foreach ($st['series'] ?? [] as $s) {
        $out .= bracket_match_card($s, "$event, $stage");
      }
      $out .= "</div>";
    }
  }
  return $out;
}

function bracket_popup_id($mids, $title) {
  global $bracket_popups, $report, $leaguetag, $linkvars;
  $mids = array_values(array_map('intval', $mids ?? []));

  $rows = '';
  foreach ($mids as $mid) {
    $sides  = $report['match_participants_teams'][$mid] ?? [];
    $rad_win = !empty($report['matches_additional'][$mid]['radiant_win']);
    $winner = $rad_win ? ($sides['radiant'] ?? -1) : ($sides['dire'] ?? -2);
    $rows .= "<div class=\"match-link-modal\">".match_link($mid, $winner, true)."</div>";
  }

  $body = $rows;
  $gets = bracket_series_gets($mids[0] ?? null);
  if ($gets) {
    $body .= "<a class=\"bracket-pop-link\" href=\"?league=$leaguetag&mod=matches-cards&gets=$gets".(empty($linkvars) ? "" : "&$linkvars")."\">".
      locale_string("bracket_view_matches").
    "</a>";
  }

  $id = 'bracket-pop-'.count($bracket_popups);
  $bracket_popups[$id] = [
    'title' => $title,
    'body' => $body,
  ];

  return $id;
}

function bracket_series_gets($mid0) {
  global $report;
  if ($mid0 === null) return 0;

  if (isset($report['match_parts_series_tag'][$mid0])) {
    $tag = $report['match_parts_series_tag'][$mid0];
    return ($report['series'][$tag]['seriesid'] ?? 0) ?: $tag;
  }

  return (int)($report['matches_additional'][$mid0]['seriesid'] ?? 0);
}

function bracket_render_group_stage($st, $event = '') {
  $stage = bracket_name($st['name']);
  $base  = trim("$event :: $stage", ": ");
  $out = "<h2>".bracket_esc($stage)."</h2><div class=\"bracket-groups\">";
  foreach ($st['groups'] as $g) {
    $gname = count($st['groups']) > 1 ? bracket_name($g['name']) : '';
    $gctx  = $gname !== '' ? "$base, $gname" : $base;
    $out .= "<div class=\"bracket-group\">";
    if ($gname !== '') $out .= "<h2 class=\"bracket-group-name\">".bracket_esc($gname)."</h2>";
    $out .= "<div class=\"bracket-group-tables\">".bracket_render_standings($g['standings'], $st);

    if (in_array($g['format'], ['swiss', 'short_swiss']) && !empty($g['round_results'])) {
      $out .= bracket_render_swiss($g, $gctx);
    } else if (!empty($g['grid'])) {
      $out .= bracket_render_grid($g, $gctx);
    } else if (!empty($st['form_months']) && !empty($st['form'])) {
      $out .= bracket_render_form($st);
    }
    $out .= "</div></div>";
  }
  $out .= "</div>";

  if (!empty($st['tiebreakers'])) {
    $tb = locale_string("bracket_tiebreakers");
    $out .= "<h2 class=\"bracket-sub\">".$tb."</h2><div class=\"bracket-row\">";
    foreach ($st['tiebreakers'] as $s) $out .= bracket_match_card($s, "$base, $tb");
    $out .= "</div>";
  }
  if (!empty($st['decider']['series'])) {
    $dec = bracket_name($st["decider"]["title"] ?? "bracket_decider");
    $out .= "<h2 class=\"bracket-sub\">".bracket_esc($dec)."</h2><div class=\"bracket-row\">";
    foreach ($st['decider']['series'] as $s) $out .= bracket_match_card($s, "$base, $dec");
    $out .= "</div>";
  }
  return $out;
}

function bracket_render_standings($standings, $stage) {
  $ub = array_flip($stage['ub_teams'] ?? []);
  $lb = array_flip($stage['lb_teams'] ?? []);
  $er = array_flip($stage['er_teams'] ?? []);
  $dq = array_flip($stage['dq_teams'] ?? []);
  $total = count($standings);
  $use_er = $er && count($er) <= (int)round($total * 0.7);

  $row_class = function ($rank, $tid) use ($ub, $lb, $er, $dq, $total, $use_er) {
    if (isset($dq[$tid])) return 'dq-wr';
    if ($use_er && isset($er[$tid])) return 'medium-wr';
    if ($ub || $lb) {
      if (isset($ub[$tid])) return 'high-wr';
      if (isset($lb[$tid])) return 'medium-wr';
      return 'low-wr';
    }
    if ($total < 3) return '';
    if ($rank < max(1, (int)round($total * 0.35))) return 'high-wr';
    if ($rank >= $total - max(1, (int)round($total * 0.3))) return 'low-wr';
    return '';
  };

  $out  = "<table class=\"list list-small bracket-standings\"><thead><tr>";
  $out .= "<th>#</th><th colspan=\"2\">".locale_string("team")."</th>".
    "<th>".locale_string("bracket_wdl")."</th><th>".locale_string("matches")."</th>";
  $out .= "</tr></thead><tbody>";
  foreach ($standings as $rank => $row) {
    $tid = $row['team'];
    $out .= "<tr class=\"".$row_class($rank, $tid)."\" data-bracket-team=\"$tid\">";
    $out .= "<td>".($rank + 1)."</td>";
    $out .= "<td class=\"bracket-logo-cell\">".team_logo($tid)."</td>";
    $out .= "<td>".team_link($tid, false)."</td>";
    $out .= "<td>".$row['w']."-".$row['d']."-".$row['l']."</td>";
    $out .= "<td>".$row['mw']."-".$row['ml']."</td>";
    $out .= "</tr>";
  }
  return $out."</tbody></table>";
}

function bracket_render_grid($g, $ctx = '') {
  if (empty($g['grid']) || empty($g['standings'])) return '';
  $ids = array_column($g['standings'], 'team');

  $out  = "<table class=\"pvp bracket-grid\"><thead><tr><th></th>";
  foreach ($ids as $id) $out .= "<th>".bracket_label($id)."</th>";
  $out .= "</tr></thead><tbody>";
  foreach ($ids as $row_id) {
    $out .= "<tr data-bracket-team=\"$row_id\"><td class=\"bracket-grid-team\">".team_logo($row_id)." ".team_name($row_id)."</td>";
    foreach ($ids as $col_id) {
      if ($row_id === $col_id) {
        $out .= "<td class=\"transparent\"></td>";
        continue;
      }

      $cell = $g['grid'][$row_id][$col_id] ?? null;
      if (!$cell) {
        $out .= "<td>-</td>";
        continue;
      }

      $cls = $cell['win'] ? 'high-wr' : ($cell['draw'] ? 'medium-wr' : 'low-wr');
      $pop = !empty($cell['mids']) ? " data-pop=\"".bracket_popup_id($cell['mids'], bracket_popup_title($row_id, $col_id, $ctx))."\"" : '';
      $out .= "<td class=\"$cls\"$pop>".bracket_esc($cell['sc'])."</td>";
    }
    $out .= "</tr>";
  }
  return $out."</tbody></table>";
}

function bracket_render_swiss($g, $ctx = '') {
  $rr = $g['round_results'];
  $n  = count($rr);

  $out = "<table class=\"pvp bracket-grid bracket-swiss\"><thead><tr><th></th>";
  for ($r = 0; $r < $n; $r++) {
    $out .= "<th>R".($r + 1)."</th>";
  }

  $out .= "</tr></thead><tbody>";
  foreach ($g['standings'] as $row) {
    $tid = $row['team'];
    $out .= "<tr data-bracket-team=\"$tid\"><td class=\"bracket-grid-team\">".team_logo($tid)." ".team_name($tid)."</td>";
    for ($r = 0; $r < $n; $r++) {
      $res = $rr[$r][$tid] ?? null;
      if (!$res) { $out .= "<td>-</td>"; continue; }
      $cls = $res['win'] ? 'high-wr' : ($res['draw'] ? 'medium-wr' : 'low-wr');
      $pop = !empty($res['mids']) ? " data-pop=\"".bracket_popup_id($res['mids'], bracket_popup_title($tid, $res['opp'], $ctx))."\"" : '';
      $out .= "<td class=\"$cls\"$pop><span class=\"standings-score\">".bracket_esc($res['score'])."</span>".
        "<span class=\"standings-extra\">".bracket_esc(bracket_label($res['opp']))."</span></td>";
    }
    $out .= "</tr>";
  }

  return $out."</tbody></table>";
}

function bracket_render_form($st) {
  $months = $st['form_months'];
  $form   = $st['form'];
  $order  = array_column($st['groups'][0]['standings'] ?? [], 'team');

  $out = "<table class=\"pvp bracket-grid bracket-form\"><thead><tr><th></th><th>".locale_string("matches")."</th>";
  foreach ($months as $i => $mk) {
    $ts = strtotime($mk.'-01');
    $out .= "<th".($i === 0 ? " class=\"separator\"" : "").">".locale_month($ts)." '".date('y', $ts)."</th>";
  }
  $out .= "</tr></thead><tbody>";

  foreach ($order as $tid) {
    $out .= "<tr data-bracket-team=\"$tid\"><td class=\"bracket-grid-team\">".team_logo($tid)." ".team_name($tid)."</td>";
    $tot = ['w' => 0, 'l' => 0, 'd' => 0];
    foreach ($months as $mk) {
      $r = $form[$tid][$mk] ?? null;
      if ($r) { $tot['w'] += $r['w']; $tot['l'] += $r['l']; $tot['d'] += $r['d']; }
    }
    $out .= bracket_form_cell($tot);
    foreach ($months as $i => $mk) $out .= bracket_form_cell($form[$tid][$mk] ?? null, $i === 0);
    $out .= "</tr>";
  }
  return $out."</tbody></table>";
}

function bracket_form_cell($r, $sep = false) {
  $cls = $sep ? 'separator' : '';
  if (!$r || ($r['w'] + $r['l'] + $r['d']) === 0) {
    return "<td class=\"$cls\">-</td>";
  }
  $rec = $r['w']."-".$r['d']."-".$r['l'];
  $tot = $r['w'] + $r['l'] + $r['d'];
  $wr  = (int)round(100 * $r['w'] / $tot);
  $cls .= $wr >= 55 ? ' high-wr' : ($wr <= 45 ? ' low-wr' : ' medium-wr');
  return "<td class=\"$cls\"><span class=\"standings-score\">".bracket_esc($rec)."</span><span class=\"standings-extra\">".$wr."%</span></td>";
}

function bracket_render_playoff($st, $event = '') {
  $b = $st['bracket'];
  $stage = bracket_name($st['name']);
  $base  = trim("$event :: $stage", " :");
  $out = "<h2>".bracket_esc($stage)."</h2>";

  if (($st['phase_type'] ?? '') === 'elimination_round') {
    $out .= "<div class=\"bracket-row\">";
    foreach (array_merge($b['ub_rounds'] ?? [], $b['lb_rounds'] ?? []) as $r) {
      foreach ($r['series'] as $s) {
        $out .= bracket_match_card($s, "$base :: ".bracket_name($r['name'] ?? ''));
      }
    }
    $out .= "</div>";
  } elseif ($b['type'] === 'double_elimination') {
    $out .= bracket_render_de($b['ub_rounds'] ?? [], $b['lb_rounds'] ?? [], $b['ub_to_lb'] ?? [], $b['grand_final']['series'] ?? [], $base);
  } else {
    $out .= bracket_render_section($b['ub_rounds'] ?? [], $base);
  }

  if (!empty($b['unplaced'])) {
    $unp = locale_string("bracket_unplaced");
    $out .= "<h2 class=\"bracket-sub\">".$unp."</h2><div class=\"bracket-row\">";
    foreach ($b['unplaced'] as $s) {
      $out .= bracket_match_card($s, "$base, $unp");
    }
    $out .= "</div>";
  }
  return $out;
}

function bracket_team_row_y($pos, $team_id) {
  $i = array_search($team_id, $pos['s']['teams']);
  if ($i === false) $i = 0;

  return $pos['y'] - intdiv(BRACKET_CARD_H, 2) + BRACKET_META_H + intdiv(BRACKET_ROW_H, 2) + BRACKET_ROW_H * (int)$i;
}

function bracket_edge_svg($card_pos, $row_h, $drop_entry = []) {
  $items = array_values($card_pos);
  if (!$items) return '';

  $lines = '';
  $max_x = 0;
  foreach ($items as $it) {
    $w = $it['s']['winner'] ?? null;
    if (!$w) {
      continue;
    }
    
    $next = null;
    foreach ($items as $cand) {
      if ($cand['s']['start'] <= $it['s']['start'] || !in_array($w, $cand['s']['teams'])) {
        continue;
      }

      if ($next === null || $cand['s']['start'] < $next['s']['start']) {
        $next = $cand;
      }
    }
    if ($next === null || $next['x'] <= $it['x']) continue;
    $x1 = $it['x'] + BRACKET_COL_W - 4;
    $x2 = $next['x'] + 4;
    $mx = $x2 - intdiv(BRACKET_CONN_W, 2);
    $y1 = bracket_team_row_y($it, $w);
    $y2 = bracket_team_row_y($next, $w);
    $lines .= "<g data-bracket-team='$w'><line x1='$x1' y1='$y1' x2='$mx' y2='$y1'/><line x1='$mx' y1='$y1' x2='$mx' y2='$y2'/><line x1='$mx' y1='$y2' x2='$x2' y2='$y2'/></g>";
    $max_x = max($max_x, $x2);
  }
  foreach ($drop_entry as $tid => $key) {
    $pos = $card_pos[$key] ?? null;
    if (!$pos) continue;

    $meta_y = $pos['y'] - intdiv(BRACKET_CARD_H, 2) + intdiv(BRACKET_META_H, 2);
    $y_top = max(0, $meta_y - 12);
    $x2 = $pos['x'] + 4;
    $x1 = max(1, $x2 - 12);

    $lines .= "<g data-bracket-team='$tid'><line x1='$x1' y1='$y_top' x2='$x1' y2='$meta_y'/><line x1='$x1' y1='$meta_y' x2='$x2' y2='$meta_y'/></g>";
    $max_x = max($max_x, $x2);
  }

  if ($lines === '') return '';
  return "<svg class='bracket-conn' width='$max_x' height='$row_h'>$lines</svg>";
}

function bracket_render_section($rounds, $ctx = '') {
  if (!$rounds) {
    return '';
  }

  $max_n = max(array_map(fn($r) => count($r['series']), $rounds));
  $slots = tb_next_pow2($max_n);
  $total_h = $slots * BRACKET_SLOT_H;
  $half = intdiv(BRACKET_CARD_H, 2);

  $hdr = "<div class=\"bracket-hdr\">";
  foreach ($rounds as $ri => $round) {
    $hdr .= "<div class=\"bracket-colname\">".bracket_esc(bracket_name($round['name']))."</div>";
    if ($ri + 1 < count($rounds)) {
      $hdr .= "<div class=\"bracket-conn-sp\" style=\"width:".BRACKET_CONN_W."px\"></div>";
    }
  }
  $hdr .= "</div>";

  $card_pos = [];
  foreach ($rounds as $ri => $round) {
    $n = count($round['series']);
    $slot_size = $n > 0 ? $slots / $n : $slots;
    foreach ($round['series'] as $si => $s) {
      $card_pos[$s['key']] = [
        'x' => $ri * (BRACKET_COL_W + BRACKET_CONN_W),
        'y' => (int)round(($si + 0.5) * $slot_size * BRACKET_SLOT_H),
        's' => $s,
      ];
    }
  }

  $body = "<div class=\"bracket-outer\">".bracket_edge_svg($card_pos, $total_h);
  foreach ($rounds as $ri => $round) {
    $n = count($round['series']);
    $slot_size = $n > 0 ? $slots / $n : $slots;
    $body .= "<div class=\"bracket-col\" style=\"height:".$total_h."px\">";
    $rname = bracket_name($round['name'] ?? '');
    foreach ($round['series'] as $si => $s) {
      $top = (int)round(($si + 0.5) * $slot_size * BRACKET_SLOT_H) - $half;
      $body .= "<div class=\"bracket-cwrap\" style=\"top:".$top."px\">".bracket_match_card($s, trim("$ctx, $rname", " ,"))."</div>";
    }
    $body .= "</div>";
    if ($ri + 1 < count($rounds)) $body .= "<div class=\"bracket-conn-sp\" style=\"width:".BRACKET_CONN_W."px;height:".$total_h."px\"></div>";
  }
  $body .= "</div>";

  return "<div class=\"bracket-section\">$hdr$body</div>";
}

function bracket_de_columns($ub_rounds, $lb_rounds, $ub_to_lb) {
  $n_ub = count($ub_rounds); $n_lb = count($lb_rounds);
  $cols = [];
  $lb_cursor = 0;
  for ($ui = 0; $ui < $n_ub; $ui++) {
    $align_lb = $ub_to_lb[$ui] ?? $ub_to_lb[(string)$ui] ?? null;
    $gap_until = ($align_lb !== null) ? (int)$align_lb : $n_lb;
    while ($lb_cursor < $gap_until) {
      $cols[] = [
        'ub' => null,
        'lb' => $lb_rounds[$lb_cursor],
      ];
      $lb_cursor++;
    }

    $lb_slot = null;
    if ($align_lb !== null && $lb_cursor === (int)$align_lb && $lb_cursor < $n_lb) {
      $is_last_feeder = true;

      for ($uj = $ui + 1; $uj < $n_ub; $uj++) {
        $aj = $ub_to_lb[$uj] ?? $ub_to_lb[(string)$uj] ?? null;
        if ($aj !== null && (int)$aj === $lb_cursor) {
          $is_last_feeder = false;
          break;
        }
      }

      if ($is_last_feeder) {
        $lb_slot = $lb_rounds[$lb_cursor];
        $lb_cursor++;
      }
    }
    $cols[] = [
      'ub' => $ub_rounds[$ui],
      'lb' => $lb_slot,
    ];
  }

  while ($lb_cursor < $n_lb) { 
    $cols[] = [
      'ub' => null,
      'lb' => $lb_rounds[$lb_cursor],
    ];
    $lb_cursor++;
  }
  
  return $cols;
}

function bracket_render_de($ub_rounds, $lb_rounds, $ub_to_lb, $gf_series, $ctx = '') {
  if (!$ub_rounds && !$lb_rounds) return '';

  $gf_html = '';
  if ($gf_series) {
    $gf_html = "<div class=\"bracket-gf\"><div class=\"bracket-colname bracket-gf-label\">".locale_string("bracket_gf")."</div>";
    foreach ($gf_series as $s) {
      $gf_html .= bracket_match_card($s, trim("$ctx, ".locale_string("bracket_gf"), " ,"));
    }
    $gf_html .= "</div>";
  }

  $n_ub = count($ub_rounds); $n_lb = count($lb_rounds);
  if ($n_ub > 0 && $n_lb > 0 && !isset($ub_to_lb[$n_ub - 1]) && !isset($ub_to_lb[(string)($n_ub - 1)])) {
    $ub_to_lb[$n_ub - 1] = $n_lb - 1;
  }

  $coverage = $n_ub > 0 ? count($ub_to_lb) / $n_ub : 0;
  $is_monotone = true; $prev_lb_idx = -1;
  for ($i = 0; $i < $n_ub; $i++) {
    $v = $ub_to_lb[$i] ?? $ub_to_lb[(string)$i] ?? null;
    if ($v !== null) {
      if ((int)$v < $prev_lb_idx) {
        $is_monotone = false;
        break;
      } $prev_lb_idx = (int)$v;
    }
  }
  if (!isset($ub_to_lb[0]) && !isset($ub_to_lb['0']) && $n_ub > 0 && $n_lb > 0) {
    $is_monotone = false;
  }

  if ($coverage < 0.6 || !$is_monotone) {
    $out = "<div class=\"bracket-tree\"><div class=\"bracket-aligned\">";
    if ($ub_rounds) {
      $out .= "<div class=\"bracket-label\">".locale_string("bracket_ub")."</div>".
        bracket_render_section($ub_rounds, trim("$ctx, ".locale_string("bracket_ub"), " ,"));
    }
    if ($lb_rounds) {
      $out .= "<div class=\"bracket-label bracket-lb-label\">".locale_string("bracket_lb")."</div>".
        bracket_render_section($lb_rounds, trim("$ctx, ".locale_string("bracket_lb"), " ,"));
    }
    return $out."</div>$gf_html</div>";
  }

  $columns = bracket_de_columns($ub_rounds, $lb_rounds, $ub_to_lb);
  $n_cols = count($columns);
  $max_u_bn = $ub_rounds ? max(array_map(fn($r) => count($r['series']), $ub_rounds)) : 1;
  $max_l_bn = $lb_rounds ? max(array_map(fn($r) => count($r['series']), $lb_rounds)) : 1;
  $ub_slots = tb_next_pow2($max_u_bn);
  $lb_slots = tb_next_pow2($max_l_bn);
  $ub_total_h = $ub_slots * BRACKET_SLOT_H;
  $lb_total_h = $lb_slots * BRACKET_SLOT_H;
  $half = intdiv(BRACKET_CARD_H, 2);

  $ub_positions = [];
  foreach ($columns as $ci => $col) if ($col['ub'] !== null) $ub_positions[] = $ci;

  $card_pos = ['ub' => [], 'lb' => []];
  foreach ($columns as $ci => $col) {
    $col_x = $ci * (BRACKET_COL_W + BRACKET_CONN_W);
    foreach (['ub' => $ub_slots, 'lb' => $lb_slots] as $side => $slots) {
      if (!$col[$side]) continue;
      $n = count($col[$side]['series']);
      $slot_size = $n > 0 ? $slots / $n : $slots;
      foreach ($col[$side]['series'] as $si => $s) {
        $card_pos[$side][$s['key']] = ['x' => $col_x, 'y' => (int)round(($si + 0.5) * $slot_size * BRACKET_SLOT_H), 's' => $s];
      }
    }
  }

  $ub_loser_set = [];
  foreach ($ub_rounds as $r) foreach ($r['series'] as $s) { $l = tb_loser($s); if ($l !== null) $ub_loser_set[$l] = true; }
  $drop_entry = [];
  foreach ($card_pos['lb'] as $key => $pos) {
    foreach ($pos['s']['teams'] as $t) {
      if (!isset($ub_loser_set[$t])) continue;
      if (!isset($drop_entry[$t]) || $pos['s']['start'] < $card_pos['lb'][$drop_entry[$t]]['s']['start']) $drop_entry[$t] = $key;
    }
  }

  $out = "<div class=\"bracket-tree\"><div class=\"bracket-aligned\">";

  if ($ub_rounds) {
    $out .= "<div class=\"bracket-label\">".locale_string("bracket_ub")."</div>";
    $out .= "<div class=\"bracket-hdr-row\">";
    $first_ub_pos = $ub_positions[0] ?? 0;
    for ($p = 0; $p < $first_ub_pos; $p++) $out .= "<div class=\"bracket-hdr-cell\"></div><div class=\"bracket-hdr-conn\"></div>";
    foreach ($ub_positions as $k => $pos) {
      $out .= "<div class=\"bracket-hdr-cell\">".bracket_esc(bracket_name($columns[$pos]['ub']['name'] ?? ''))."</div>";
      if ($k + 1 < count($ub_positions)) {
        $next_pos = $ub_positions[$k + 1];
        $span_w = BRACKET_CONN_W + ($next_pos - $pos - 1) * (BRACKET_COL_W + BRACKET_CONN_W);
        $out .= "<div style=\"flex-shrink:0;width:".$span_w."px\"></div>";
      }
    }
    $out .= "</div>";

    $out .= "<div class=\"bracket-body-row\">".bracket_edge_svg($card_pos['ub'], $ub_total_h);
    for ($p = 0; $p < $first_ub_pos; $p++) {
      $out .= "<div class=\"bracket-col bracket-spacer\" style=\"height:".$ub_total_h."px\"></div>".
        "<div class=\"bracket-conn-sp\" style=\"width:".BRACKET_CONN_W."px;height:".$ub_total_h."px\"></div>";
    }
    foreach ($ub_positions as $k => $pos) {
      $col = $columns[$pos]; $n = count($col['ub']['series']);
      $slot_size = $n > 0 ? $ub_slots / $n : $ub_slots;
      $rname = bracket_name($col['ub']['name'] ?? '');
      $out .= "<div class=\"bracket-col\" style=\"height:".$ub_total_h."px\">";
      foreach ($col['ub']['series'] as $si => $s) {
        $top = (int)round(($si + 0.5) * $slot_size * BRACKET_SLOT_H) - $half;
        $out .= "<div class=\"bracket-cwrap\" style=\"top:".$top."px\">".bracket_match_card($s, trim("$ctx, $rname", " ,"))."</div>";
      }
      $out .= "</div>";
      if ($k + 1 < count($ub_positions)) {
        $next_pos = $ub_positions[$k + 1];
        $span_w = BRACKET_CONN_W + ($next_pos - $pos - 1) * (BRACKET_COL_W + BRACKET_CONN_W);
        $out .= "<div style=\"width:".$span_w."px;height:".$ub_total_h."px;flex-shrink:0\"></div>";
      }
    }
    $out .= "</div>";
  }

  if ($lb_rounds) {
    $out .= "<div class=\"bracket-label bracket-lb-label\">".locale_string("bracket_lb")."</div>";
    $out .= "<div class=\"bracket-hdr-row\">";
    foreach ($columns as $ci => $col) {
      $out .= "<div class=\"bracket-hdr-cell bracket-hdr-lb\">".bracket_esc(bracket_name($col['lb']['name'] ?? ''))."</div>";
      if ($ci + 1 < $n_cols) {
        $out .= "<div class=\"bracket-hdr-conn\"></div>";
      }
    }
    $out .= "</div>";

    $out .= "<div class=\"bracket-body-row\">".bracket_edge_svg($card_pos['lb'], $lb_total_h, $drop_entry);
    foreach ($columns as $ci => $col) {
      if ($col['lb']) {
        $n = count($col['lb']['series']);
        $slot_size = $n > 0 ? $lb_slots / $n : $lb_slots;
        $rname = bracket_name($col['lb']['name'] ?? '');
        $out .= "<div class=\"bracket-col\" style=\"height:".$lb_total_h."px\">";
        foreach ($col['lb']['series'] as $si => $s) {
          $top = (int)round(($si + 0.5) * $slot_size * BRACKET_SLOT_H) - $half;
          $out .= "<div class=\"bracket-cwrap\" style=\"top:".$top."px\">".bracket_match_card($s, trim("$ctx, $rname", " ,"))."</div>";
        }
        $out .= "</div>";
      } else {
        $out .= "<div class=\"bracket-col bracket-spacer\" style=\"height:".$lb_total_h."px\"></div>";
      }
      if ($ci + 1 < $n_cols) {
        $out .= "<div class=\"bracket-conn-sp\" style=\"width:".BRACKET_CONN_W."px;height:".$lb_total_h."px\"></div>";
      }
    }
    $out .= "</div>";
  }

  $out .= "</div>";

  if ($gf_html) {
    if ($ub_rounds && $lb_rounds) {
      $label_h = 20; $hdr_h = 18; $lb_label_h = 20;
      $ub_block = $label_h + $hdr_h + $ub_total_h;
      $total_h = $ub_block + $lb_label_h + $hdr_h + $lb_total_h;
      $ubf_y = $label_h + $hdr_h + intdiv($ub_total_h, 2);
      $lbf_y = $ub_block + $lb_label_h + $hdr_h + intdiv($lb_total_h, 2);
      $gf_y = intdiv($total_h, 2);
      $w = 34; $mx = 17;
      $gf0 = $gf_series[0] ?? [];
      $t0  = $gf0['teams'][0] ?? 0;
      $t1  = $gf0['teams'][1] ?? 0;
      
      $lines = "<g data-bracket-team='$t0'>"
             . "<line x1='0' y1='$ubf_y' x2='$mx' y2='$ubf_y'/><line x1='$mx' y1='$ubf_y' x2='$mx' y2='$gf_y'/></g>"
             . "<g data-bracket-team='$t1'>"
             . "<line x1='0' y1='$lbf_y' x2='$mx' y2='$lbf_y'/><line x1='$mx' y1='$lbf_y' x2='$mx' y2='$gf_y'/></g>"
             . "<g class='bracket-gf-shared'><line x1='$mx' y1='$gf_y' x2='$w' y2='$gf_y'/></g>";
      $out .= "<svg class='bracket-conn bracket-gf-conn' width='$w' height='$total_h'>$lines</svg>";
    }
    $out .= $gf_html;
  }
  return $out."</div>";
}

function bracket_match_card($s, $ctx = '') {
  [$ta, $tb] = $s['teams'];
  $sa = (int)($s['score'][$ta] ?? 0);
  $sb = (int)($s['score'][$tb] ?? 0);
  $wa = $s['winner'] === $ta;
  $wb = $s['winner'] === $tb;

  $meta = locale_day($s['start']).' · BO'.($s['bo'] ?? 0);

  $row = function ($tid, $sc, $win, $lose) {
    $cls = 'bracket-team'.($win ? ' win' : ($lose ? ' lose' : ''));
    return "<div class=\"$cls\" data-bracket-team=\"$tid\">".
      "<span class=\"bracket-tname\">".team_logo($tid)." ".bracket_label($tid)."</span>".
      "<span class=\"bracket-tsc\">$sc</span></div>";
  };

  $id = bracket_popup_id($s['mids'] ?? [], bracket_popup_title($ta, $tb, $ctx));
  return "<div class=\"bracket-card\" data-pop=\"$id\">".
    "<div class=\"bracket-cmeta\">".bracket_esc($meta).bracket_flag($s)."</div>".
    $row($ta, $sa, $wa, $wb).$row($tb, $sb, $wb, $wa)."</div>";
}

function bracket_render_placements($ev) {
  $groups = tb_event_placements($ev['stages']);
  if (count($groups) < 2) return '';

  $out = "<h2>".locale_string("bracket_placements")."</h2>";
  $out .= "<table class=\"bracket-places\"><tbody>";
  $place = 1;
  foreach ($groups as $grp) {
    $n = count($grp);
    $label = $n === 1 ? bracket_place_label($place) : 'T'.$place.'–'.($place + $n - 1);
    $cls = $place <= 2 ? ' high-wr' : ($place <= 4 ? ' medium-wr' : '');
    foreach ($grp as $t) {
      $out .= "<tr class=\"bracket-place$cls\" data-bracket-team=\"$t\">".
        "<td class=\"bracket-place-label\">$label</td>".
        "<td class=\"bracket-place-team\">".team_logo($t).
        " <span class=\"bracket-place-team-name\">".bracket_label($t)."</span></td></tr>";
    }
    $place += $n;
  }
  return $out."</tbody></table>";
}

function bracket_place_label($place) {
  return ['🥇', '🥈', '🥉'][$place - 1] ?? (string)$place;
}
