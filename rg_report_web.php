<?php
/* SETTINGS */

$lrg_use_get = true;
$lrg_get_depth = 3;
$locale = "en";

$mod = "";

/* FUNCTIONS */  {
  function check_module($module) {
    global $lrg_get_depth;
    global $lrg_use_get;
    global $mod;

    return ($lrg_use_get && stripos($mod, $module) === 0) || !$lrg_use_get || !$lrg_get_depth || unset_module();
  }

  function unset_module() {
    global $unset_module;

    if($unset_module) {
      $unset_module = false;
      return true;
    }
    return false;
  }

  function hero_portrait($hid, &$meta) {
    return "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][ $hid ]['tag'].
      ".png\" alt=\"".$meta['heroes'][ $hid ]['tag']."\" />";
  }

  function hero_full($hid, &$meta) {
    return hero_portrait($hid, $meta)." ".$meta['heroes'][ $hid ]['name'];
  }

  function player_name() {

  }

  function player_card_link() {

  }

  function player_card($player_id, &$report, &$meta, &$strings) {
    $pname = $report['players'][$player_id];
    $pinfo = $report['players_additional'][$player_id];

    $output = "<div class=\"player-card\"><div class=\"player-name\"><a href=\"http://opendota.com/players/$player_id\" target=\"_blank\">".$pname." (".$player_id.")</a></div>";
    if(isset($report['teams']))
      $output .= "<div class=\"player-team\">".$pinfo['team']."</div>";
    $output .= "<div class=\"player-add-info\">".
                  "<div class=\"player-info-line\"><span class=\"caption\">".$strings['matches'].":</span> ".$pinfo['matches']." (".
                    $pinfo['won']." - ".($pinfo['matches'] - $pinfo['won']).")</div>".
                  "<div class=\"player-info-line\"><span class=\"caption\">".$strings['winrate'].":</span> ".number_format($pinfo['won']*100/$pinfo['matches'], 2)."%</div>".
                  "<div class=\"player-info-line\"><span class=\"caption\">".$strings['gpm'].":</span> ".number_format($pinfo['gpm'],1)."</div>".
                  "<div class=\"player-info-line\"><span class=\"caption\">".$strings['xpm'].":</span> ".number_format($pinfo['xpm'],1)."</div>".
                  "<div class=\"player-info-line\"><span class=\"caption\">".$strings['hero_pool'].":</span> ".$pinfo['hero_pool_size']."</div></div>";

    # heroes
    $output .= "<div class=\"player-heroes\"><div class=\"section-caption\">".$strings['heroes']."</div><div class=\"section-lines\">";
    foreach($pinfo['heroes'] as $hero) {
      $output .= "<div class=\"player-info-line\"><span class=\"caption\">".hero_full($hero['heroid'], $meta).":</span> ";
      $output .= $hero['matches']." - ".number_format($hero['wins']*100/$hero['matches'], 2)."%</div>";
    }
    $output .= "</div></div>";

    # positions
    $output .= "<div class=\"player-positions\"><div class=\"section-caption\">".$strings['player_positions']."</div><div class=\"section-lines\">";
    foreach($pinfo['positions'] as $position) {
      $output .= "<div class=\"player-info-line\"><span class=\"caption\">".($position['core'] ? $strings['core']." " : $strings['support']).
                    $meta['lanes'][ $position['lane'] ].":</span> ";
      $output .= $position['matches']." - ".number_format($position['wins']*100/$position['matches'], 2)."%</div>";
    }
    $output .= "</div></div>";


    return $output."</div>";
  }

  function team_card() {

  }

  function match_card($mid, &$report, &$meta, &$strings) {
    $output = "<div class=\"match-card\"><div class=\"match-id\">".match_link($mid)."</div>";
    $radiant = "<div class=\"match-team radiant\">";
    $dire = "<div class=\"match-team dire\">";

    $players_radi = ""; $players_dire = "";
    $heroes_radi = "";  $heroes_dire = "";

    for($i=0; $i<10; $i++) {
      if($report['matches'][$mid][$i]['radiant']) {
        $players_radi .= "<div class=\"match-player\">".$report['players'][ $report['matches'][$mid][$i]['player'] ]."</div>";
        $heroes_radi .= "<div class=\"match-hero\">".hero_portrait($report['matches'][$mid][$i]['hero'], $meta)."</div>";
      } else {
        $players_dire .= "<div class=\"match-player\">".$report['players'][ $report['matches'][$mid][$i]['player'] ]."</div>";
        $heroes_dire .= "<div class=\"match-hero\">".hero_portrait($report['matches'][$mid][$i]['hero'], $meta)."</div>";
      }

    }
    if(isset($report['teams'])) {
      $team_radiant = $report['teams'][ $report['match_participants_teams']['radiant'] ]['name']." (".$report['teams'][ $report['match_participants_teams']['radiant'] ]['tag'].")";
      $team_dire = $report['teams'][ $report['match_participants_teams']['dire'] ]['name']." (".$report['teams'][ $report['match_participants_teams']['dire'] ]['tag'].")";
    } else {
      $team_radiant = "Radiant";
      $team_dire = "Dire";
    }
    $radiant .= "<div class=\"match-team-name\">".$team_radiant."</div>";
    $dire .= "<div class=\"match-team-name\">".$team_dire."</div>";

    $radiant .= "<div class=\"match-players\">".$players_radi."</div><div class=\"match-heroes\">".$heroes_radi."</div></div>";
    $dire .= "<div class=\"match-players\">".$players_dire."</div><div class=\"match-heroes\">".$heroes_dire."</div></div>";

    $output .= $radiant.$dire;

    $duration = (int)($report['matches_additional'][$mid]['duration']/3600);

    $duration = $duration ? $duration.":".((int)($report['matches_additional'][$mid]['duration']%3600/60)) : ((int)($report['matches_additional'][$mid]['duration']%3600/60));

    $duration = $duration.":".((int)($report['matches_additional'][$mid]['duration']%60));

    $output .= "<div class=\"match-add-info\">
                  <div class=\"match-info-line\"><span class=\"caption\">".$strings['duration']." :</span> ".
                    $duration."</div>
                  <div class=\"match-info-line\"><span class=\"caption\">".$strings['region']." :</span> ".
                    $meta['regions'][
                      $meta['clusters'][ $report['matches_additional'][$mid]['cluster'] ]
                    ]."</div>
                  <div class=\"match-info-line\"><span class=\"caption\">".$strings['game_mode']." :</span> ".
                    $meta['modes'][$report['matches_additional'][$mid]['game_mode']]."</div>
                    <div class=\"match-info-line\"><span class=\"caption\">".$strings['winner']." :</span> ".
                      ($report['matches_additional'][$mid]['radiant_win'] ? $team_radiant : $team_dire)."</div>
                    <div class=\"match-info-line\"><span class=\"caption\">".$strings['date']." :</span> ".
                      date("h:i:s j M Y", $report['matches_additional'][$mid]['date'])."</div>
                </div>";

    return $output."</div>";
  }

  function join_matches($matches) {
    $output = array();
    foreach($matches as $match) {
      $output[] = match_link($match);
    }
    return implode($output, ", ");

  }

  function match_link($mid) {
    return "<a href=\"https://opendota.com/matches/$mid\" target=\"_blank\">$mid</a>";
  }

  function join_selectors($modules, $level, $parent="") {
    global $lrg_use_get;
    global $lrg_get_depth;
    global $level_codes;
    global $mod;
    global $strings;
    global $leaguetag;

    $out = "";
    $first = true;
    $unset_selector = false;

    if(empty($parent)) {
      $selectors = explode("-", $mod);
      if(!isset($modules[$selectors[0]])) $unset_selector = true;
    } else {
      $selectors = explode("-", str_replace($parent."-", "", $mod));
      if(!isset($modules[$selectors[0]])) $unset_selector = true;
    }
    # reusing assets Kappa
    $selectors = array();

    foreach($modules as $modname => $module) {
      if($lrg_use_get && $lrg_get_depth > $level) {
        if (stripos($mod, (empty($parent) ? "" : $parent."-" ).$modname) === 0)
          $selectors[] = "<span class=\"selector active\">".$strings[$modname]."</span>";
        else
          $selectors[] = "<span class=\"selector".($unset_selector ? " active" : "").
                            "\"><a href=\"?league=".$leaguetag."&mod=".(empty($parent) ? "" : $parent."-" ).$modname."\">".$strings[$modname]."</a></span>";
      } else {
        $selectors[] = "<span class=\"mod-".$level_codes[$level][1]."-selector selector".
                            ($first ? " active" : "")."\" onclick=\"switchTab(event, 'module-".(empty($parent) ? "" : $parent."-" ).$modname."', 'mod-".$level_codes[$level][1]."');\">".$strings[$modname]."</span>";
      }
      if(($lrg_use_get && stripos($mod, (empty($parent) ? "" : $parent."-" ).$modname) === 0) || !$lrg_use_get || $lrg_get_depth < $level+1 || $unset_selector) {
        if(is_array($module)) {
          $module = join_selectors($module, $level+1, (empty($parent) ? "" : $parent."-" ).$modname);
        }
        $out .= "<div id=\"module-".(empty($parent) ? "" : $parent."-" ).$modname."\" class=\"selector-module mod-".$level_codes[$level][1].($first ? " active" : "")."\">".$module."</div>";
        $first = false;
        $unset_selector = false;
      }
    }
    return "<div class=\"selector-modules".(empty($level_codes[$level][0]) ? "" : "-".$level_codes[$level][0])."\">".implode($selectors, " | ")."</div>".$out;
  }
}
$level_codes = array(
  # level => array( class-postfix, class-level )
  0 => array ( "", "higher-level" ),
  1 => array ( "sublevel", "lower-level" ),
  2 => array ( "level-3", "level-3" ),
  4 => array ( "level-4", "level-4" )
);

/* INITIALISATION */

    if(isset($argv)) {

    $options = getopt("l:m:d:f");

    if(isset($options['l'])) {
      $leaguetag = $options['l'];
    }
    if(isset($options['m'])) {
      $lrg_use_get = true;
      $mod = $options['m'];
    }
    if(isset($options['f'])) {
      $lrg_get_depth = 0;
    }
    if(isset($options['d'])) {
      $lrg_get_depth = (int)$options['d'];
    }
  } else if ($lrg_use_get) {
    if(isset($_GET['league']) && !empty($_GET['league'])) {
      if(file_exists("reports/report_".$_GET['league'].".json")) {
        $leaguetag = $_GET['league'];
        if($lrg_get_depth > 0) {
          if(isset($_GET['mod'])) $mod = $_GET['mod'];
          else $mod = "";
        }
      } else $leaguetag = "";
    } else $leaguetag = "";

    if(isset($_GET['loc']) && !empty($_GET['loc'])) {
      $locale = $_GET['loc'];
    }
  }

  if(file_exists('locales/'.$locale.'.php'))
    require_once('locales/'.$locale.'.php');
  else
    require_once('locales/en.php');

  if (!empty($leaguetag)) {
    $output = "";

    $report = file_get_contents("reports/report_".$leaguetag.".json") or die("[F] Can't open $teaguetag, probably no such report\n");
    $report = json_decode($report, true);

    $meta = file_get_contents("res/metadata.json") or die("[F] Can't open metadata\n");
    $meta = json_decode($meta, true);

    $modules = array();
    # module => array or ""
    $modules['overview'] = "";
    if (isset($report['records'])) $modules['records'] = "";
    if (isset($report['averages_heroes']) || isset($report['pickban']) || isset($report['draft']) || isset($report['hero_positions']) ||
        isset($report['hero_sides']) || isset($report['hero_pairs']) || isset($report['hero_triplets']))
          $modules['heroes'] = array();

    if (isset($report['averages_players']) || isset($report['pvp']) || isset($report['player_positions']) || isset($report['player_pairs']))
      $modules['players'] = array();

    if (isset($report['teams'])) $modules['teams'] = array();

    if (isset($report['matches'])) $modules['matches'] = "";

    if (isset($report['players'])) $modules['participants'] = array();

    if(empty($mod)) $unset_module = true;
    else $unset_module = false;

    $use_graph = false;

    $h3 = array_rand($report['random']);

    $random_caption = "placeholder";
    $random_text = "Some random text...";

  # overview
  if ( check_module("overview") ) {
    $modules['overview'] .= "<div class=\"content-text\"><h1>".$random_caption."</h1>".$random_text."</div>";

    $modules['overview'] .= "<table class=\"list\" id=\"overview-table\">";
    foreach($report['random'] as $key => $value) {
      $modules['overview'] .= "<tr><td>".$strings[$key]."</td><td>".$value."</td></tr>";
    }
    $modules['overview'] .= "</table>";
  }
  # records

  if (isset($modules['records']) && check_module("records")) {
    $modules['records'] .= "<table id=\"records-module-table\" class=\"list\">
                              <tr class=\"thead\">
                                <th onclick=\"sortTable(0,'records-module-table');\">".$strings['record']."</th>".
                               "<th onclick=\"sortTable(1,'records-module-table');\">".$strings['match']."</th>
                                <th onclick=\"sortTableNum(2,'records-module-table');\">".$strings['value']."</th>
                                <th onclick=\"sortTable(3,'records-module-table');\">".$strings['player']."</th>
                                <th onclick=\"sortTable(4,'records-module-table');\">".$strings['hero']."</th>
                              </tr>";
    foreach($report['records'] as $key => $record) {
      $modules['records'] .= "<tr>
                                <td>".$strings[$key]."</td>
                                <td>". ($record['matchid'] ?
                                          "<a href=\"https://opendota.com/matches/".$record['matchid']."\" alt=\"Match ".$record['matchid']." on OpenDota\" target=\"_blank\">".$record['matchid']."</a>" :
                                          //"<a onclick=\"showModal('".htmlspecialchars(match_card($record['matchid'], $report['matches'][$record['matchid']], $report, $meta))."','');\" alt=\"Match ".$record['matchid']." on OpenDota\" target=\"_blank\">".$record['matchid']."</a>" :
                                     "")."</td>
                                <td>".number_format($record['value'],2)."</td>
                                <td>". ($record['playerid'] ?
                                          (strstr($key, "_team") != FALSE ?
                                            $report['teams'][ $record['playerid'] ]['name']." ( ".$report['teams'][ $record['playerid'] ]['tag']." )" :
                                            $report['players'][$record['playerid']]
                                          ) :
                                     "")."</td>
                                <td>".($record['heroid'] ?
                                  "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][$record['heroid']]['tag'].
                                  ".png\" alt=\"".$meta['heroes'][$record['heroid']]['tag']."\" /> ".
                                  $meta['heroes'][$record['heroid']]['name'] : "").
                               "</td>
                            </tr>";
    }

    $modules['records'] .= "</table>";
  }

  # heroes
  if (isset($modules['heroes']) && check_module("heroes")) {
    if($mod == "heroes") $unset_module = true;
    $parent = "heroes-";

    if (isset($report['averages_heroes']) ) {
      $modules['heroes']['averages_heroes'] = "";

      if (check_module($parent."averages_heroes")) {
        foreach($report['averages_heroes'] as $key => $avg) {
          $modules['heroes']['averages_heroes'] .= "<table id=\"avgs-heroes-".$key."\" class=\"list list-fixed list-small\">
                                                      <caption>".$strings[$key]."</caption>
                                                      <tr class=\"thead\">
                                                        <th>".$strings['hero']."</th>
                                                        <th>".$strings['value']."</th>
                                                      </tr>";
          foreach($avg as $hero) {
            $modules['heroes']['averages_heroes'] .= "<tr>
                                                        <td>".($hero['heroid'] ?
                                                          "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][$hero['heroid']]['tag'].
                                                          ".png\" alt=\"".$meta['heroes'][$hero['heroid']]['tag']."\" /> ".
                                                          $meta['heroes'][$hero['heroid']]['name'] : "").
                                                       "</td><td>".number_format($hero['value'],2)."</td></tr>";
          }
          $modules['heroes']['averages_heroes'] .= "</table>";
        }
      }
    }
    if (isset($report['pickban'])) {
      $modules['heroes']['pickban'] = "";

      if (check_module($parent."pickban")) {
        $heroes = $meta['heroes'];

          $modules['heroes']['pickban'] .=  "<table id=\"heroes-pickban\" class=\"list\">
                                                <tr class=\"thead\">
                                                  <th onclick=\"sortTable(0,'heroes-pickban');\">".$strings['hero']."</th>
                                                  <th onclick=\"sortTableNum(1,'heroes-pickban');\">".$strings['matches_total']."</th>
                                                  <th onclick=\"sortTableNum(2,'heroes-pickban');\">".$strings['matches_picked']."</th>
                                                  <th onclick=\"sortTableNum(3,'heroes-pickban');\">".$strings['winrate_picked']."</th>
                                                  <th onclick=\"sortTableNum(4,'heroes-pickban');\">".$strings['matches_banned']."</th>
                                                  <th onclick=\"sortTableNum(5,'heroes-pickban');\">".$strings['winrate_banned']."</th>
                                                </tr>";
          foreach($report['pickban'] as $hid => $hero) {
            unset($heroes[$hid]);
            $modules['heroes']['pickban'] .=  "<tr>
                                                  <td>".($hid ?
                                                    "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][$hid]['tag'].
                                                    ".png\" alt=\"".$meta['heroes'][$hid]['tag']."\" /> ".
                                                    $meta['heroes'][$hid]['name'] : "").
                                                 "</td>
                                                  <td>".$hero['matches_total']."</td>
                                                  <td>".$hero['matches_picked']."</td>
                                                  <td>".number_format($hero['winrate_picked']*100,2)."%</td>
                                                  <td>".$hero['matches_banned']."</td>
                                                  <td>".number_format($hero['winrate_banned']*100,2)."%</td>
                                                </tr>";
          }
          $modules['heroes']['pickban'] .= "</table>";

          $modules['heroes']['pickban'] .= "<div class=\"content-text\"><h1>".$strings['heroes_uncontested'].": ".sizeof($heroes)."</h1>";

          foreach($heroes as $hero) {
            $modules['heroes']['pickban'] .= "<div class=\"hero\"><img src=\"res/heroes/".$hero['tag'].
                ".png\" alt=\"".$hero['tag']."\" /><span class=\"hero_name\">".
                $hero['name']."</span></div>";
          }
          $modules['heroes']['pickban'] .= "</div>";
        }
    }
    if (isset($report['draft'])) {
      $modules['heroes']['draft'] = array();

      if (check_module($parent."draft")) {
        for ($i=0; $i<2; $i++) {
          $modules['heroes']['draft'][($i ? "pick" : "ban")."_stages"] = "";
          for ($j=1; $j<4; $j++, isset($report['draft'][$i][$j])) {
            uasort($report['draft'][$i][$j], function($a, $b) {
              if($a['matches'] == $b['matches']) return 0;
              else return ($a['matches'] < $b['matches']) ? 1 : -1;
            });

            $modules['heroes']['draft'][($i ? "pick" : "ban")."_stages"] .= "<table id=\"heroes-draft-$i-$j\" class=\"list list-small\">
                                              <caption> Stage $j of ".($i ? $strings['picks'] : $strings['bans'])."</caption>
                                              <tr class=\"thead\">
                                                <th onclick=\"sortTable(0,'heroes-draft-$i-$j');\">".$strings['hero']."</th>
                                                <th onclick=\"sortTableNum(1,'heroes-draft-$i-$j');\">".$strings['matches']."</th>
                                                <th onclick=\"sortTableNum(2,'heroes-draft-$i-$j');\">".$strings['winrate']."</th>
                                              </tr>";

            foreach($report['draft'][$i][$j] as $hero) {
              $modules['heroes']['draft'][($i ? "pick" : "ban")."_stages"] .= "<tr>
                                                  <td>".($hero['heroid'] ?
                                                    "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][$hero['heroid']]['tag'].
                                                    ".png\" alt=\"".$meta['heroes'][$hero['heroid']]['tag']."\" /> ".
                                                    $meta['heroes'][$hero['heroid']]['name'] : "").
                                                 "</td>
                                                  <td>".$hero['matches']."</td>
                                                  <td>".number_format($hero['winrate']*100,2)."%</td>
                                                </tr>";
            }
            $modules['heroes']['draft'][($i ? "pick" : "ban")."_stages"] .= "</table>";

          }
        }
      }
    }
    if (isset($report['hero_positions'])) {
      $modules['heroes']['hero_positions'] = array();

      if(check_module($parent."hero_positions")) {
        for ($i=0; $i<2 && !isset($keys); $i++) {
          for ($j=1; $j<6 && $j>0; $j++) {
            if (!$i) { $j = 0; }
            if(isset($report['hero_positions'][$i][$j][0])) {
              $keys = array_keys($report['hero_positions'][$i][$j][0]);
              break;
            }
            if (!$i) { break; }
          }
        }

        for ($i=0; $i<2; $i++) {
          for ($j=1; $j<6 && $j>0; $j++) {
            if (!$i) { $j = 0; }

            if(!isset($strings["positions_$i"."_$j"]))
              $strings["positions_$i"."_$j"] = ($i ? $strings['core'] : $strings['support'])." ".$meta['lanes'][$j];

            if(sizeof($report['hero_positions'][$i][$j])) {
              $modules['heroes']['hero_positions']["positions_$i"."_$j"]  = "";
              $modules['heroes']['hero_positions']["positions_$i"."_$j"] .= "<table id=\"heroes-positions-$i-$j\" class=\"list wide\">
                                                <tr class=\"thead\">
                                                  <th onclick=\"sortTable(0,'heroes-positions-$i-$j');\">".$strings['hero']."</th>";
              for($k=1, $end=sizeof($keys); $k < $end; $k++) {
                $modules['heroes']['hero_positions']["positions_$i"."_$j"] .= "<th onclick=\"sortTableNum($k,'heroes-positions-$i-$j');\">".$strings[$keys[$k]]."</th>";
              }
              $modules['heroes']['hero_positions']["positions_$i"."_$j"] .= "</tr>";

              uasort($report['hero_positions'][$i][$j], function($a, $b) {
                if($a['matches_s'] == $b['matches_s']) return 0;
                else return ($a['matches_s'] < $b['matches_s']) ? 1 : -1;
              });

              foreach($report['hero_positions'][$i][$j] as $hero) {

                $modules['heroes']['hero_positions']["positions_$i"."_$j"] .= "<tr".(isset($report['hero_positions_matches']) ?
                                                                  " onclick=\"showModal('".htmlspecialchars(join_matches($report['hero_positions_matches'][$i][$j][$hero['heroid']])).
                                                                          "', '".$meta['heroes'][ $hero['heroid'] ]['name']." - ".
                                                                          $strings["positions_$i"."_$j"]." - ".$strings['matches']."');\"" : "").">
                                                    <td>".($hero['heroid'] ?
                                                      "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][ $hero['heroid'] ]['tag'].
                                                      ".png\" alt=\"".$meta['heroes'][ $hero['heroid'] ]['tag']."\" /> ".
                                                      $meta['heroes'][ $hero['heroid'] ]['name'] : "").
                                                   "</td>
                                                    <td>".$hero['matches_s']."</td>
                                                    <td>".number_format($hero['winrate_s']*100,1)."%</td>";
                for($k=3, $end=sizeof($keys); $k < $end; $k++) {
                  $modules['heroes']['hero_positions']["positions_$i"."_$j"] .= "<td>".number_format($hero[$keys[$k]],1)."</td>";
                }
                $modules['heroes']['hero_positions']["positions_$i"."_$j"] .= "</tr>";
              }
              $modules['heroes']['hero_positions']["positions_$i"."_$j"] .= "</table>";
            }
            if (!$i) { break; }
          }
        }
        unset($keys);
      }
    }
    if (isset($report['hero_sides'])) {
      $modules['heroes']['hero_sides'] = array();

      if(check_module($parent."hero_sides")) {
        for ($i=0; $i<2 && !isset($keys); $i++) {
            if(isset($report['hero_sides'][$i][0])) {
              $keys = array_keys($report['hero_sides'][$i][0]);
              break;
            }
        }

        for ($side = 0; $side < 2; $side++) {
          $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] = "";
          $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] .= "<table id=\"hero-sides-".$side."\" class=\"list\">
                                        <tr class=\"thead\">
                                          <th onclick=\"sortTable(0,'hero-sides-$side');\">".$strings['hero']."</th>";
          for($k=1, $end=sizeof($keys); $k < $end; $k++) {
            $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] .= "<th onclick=\"sortTableNum($k,'hero-sides-$side');\">".$strings[$keys[$k]]."</th>";
          }
          $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] .= "</tr>";

          foreach($report['hero_sides'][$side] as $hero) {
            $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] .= "<tr>
                                                <td>".($hero['heroid'] ?
                                                  "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][ $hero['heroid'] ]['tag'].
                                                  ".png\" alt=\"".$meta['heroes'][ $hero['heroid'] ]['tag']."\" /> ".
                                                  $meta['heroes'][ $hero['heroid'] ]['name'] : "").
                                               "</td>".
                                               "<td>".$hero['matches']."</td>".
                                               "<td>".number_format($hero['winrate']*100,2)."%</td>";
            for($k=3, $end=sizeof($keys); $k < $end; $k++) {
              $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] .= "<td>".number_format($hero[$keys[$k]],2)."</td>";
            }
            $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] .= "</tr>";
          }
          $modules['heroes']['hero_sides'][$side ? 'dire' : 'radiant'] .= "</table>";
        }
        unset($keys);
      }
    }
    if (isset($report['hero_combos_graph']) && $report['settings']['heroes_combo_graph']) {
      $modules['heroes']['hero_combo_graph'] = "";

      if (check_module($parent."hero_combo_graph") && isset($report['pickban'])) {
        if(isset($report['hero_combos_graph'])) {
          $use_graph = true;

          $modules['heroes']['hero_combo_graph'] .= "<div id=\"hero-combos-graph\"></div><script type=\"text/javascript\">";

          $nodes = "";
          foreach($meta['heroes'] as $hid => $hero) {
            $nodes .= "{id: $hid, value: ".
              (isset($report['pickban'][$hid]['matches_picked']) ? $report['pickban'][$hid]['matches_picked'] : 0).
              ", label: '".addslashes($hero['name'])."'},";
          }
          $modules['heroes']['hero_combo_graph'] .= "var nodes = [".$nodes."];";

          $nodes = "";
          foreach($report['hero_combos_graph'] as $combo) {
            $nodes .= "{from: ".$combo['heroid1'].", to: ".$combo['heroid2'].", value:".$combo['matches']."},";
          }

          $modules['heroes']['hero_combo_graph'] .= "var edges = [".$nodes."];";

          $modules['heroes']['hero_combo_graph'] .= "var container = document.getElementById('hero-combos-graph');\n".
                                                      "var data = { nodes: nodes, edges: edges};\n".
                                                      "var options={nodes: { shape: 'dot', scaling:{ label: { min:8, max:20 } } }};\n".
                                                      "var network = new vis.Network(container, data, options);\n".
                                                      "</script>";
        }
      }
    }
    if (isset($report['hero_pairs']) || isset($report['hero_pairs'])) {
      $modules['heroes']['hero_combos'] = "";

        if (isset($report['hero_pairs'])) {{
          $modules['heroes']['hero_combos'] .= "<table id=\"hero-pairs\" class=\"list\">
                                                <caption>".$strings['hero_pairs']."</caption>
                                                <tr class=\"thead\">
                                                  <th onclick=\"sortTable(0,'hero-pairs');\">".$strings['hero']." 1</th>
                                                  <th onclick=\"sortTable(1,'hero-pairs');\">".$strings['hero']." 2</th>
                                                  <th onclick=\"sortTableNum(2,'hero-pairs');\">".$strings['matches']."</th>
                                                  <th onclick=\"sortTableNum(3,'hero-pairs');\">".$strings['winrate']."</th>
                                                </tr>";
          foreach($report['hero_pairs'] as $pair) {
            $modules['heroes']['hero_combos'] .= "<tr".(isset($report['hero_pairs_matches']) ?
                                                " onclick=\"showModal('".htmlspecialchars(join_matches($report['hero_pairs_matches'][$pair['heroid1'].'-'.$pair['heroid2']])).
                                                                      "', '".$strings['matches']."');\"" : "").">
                                                  <td>".($pair['heroid1'] ?
                                                    "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][ $pair['heroid1'] ]['tag'].
                                                    ".png\" alt=\"".$meta['heroes'][ $pair['heroid1'] ]['tag']."\" /> ".
                                                    $meta['heroes'][ $pair['heroid1'] ]['name'] : "").
                                                 "</td><td>".($pair['heroid2'] ?
                                                   "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][ $pair['heroid2'] ]['tag'].
                                                   ".png\" alt=\"".$meta['heroes'][ $pair['heroid2'] ]['tag']."\" /> ".
                                                   $meta['heroes'][ $pair['heroid2'] ]['name'] : "").
                                                 "</td>
                                                 <td>".$pair['matches']."</td>
                                                 <td>".number_format($pair['winrate']*100,2)."</td>
                                                </tr>";
          }
          $modules['heroes']['hero_combos'] .= "</table>";
        }

        if (!empty($report['hero_triplets'])) {

          $modules['heroes']['hero_combos'] .= "<table id=\"hero-triplets\" class=\"list\">
                                                <caption>".$strings['hero_triplets']."</caption>
                                                <tr class=\"thead\">
                                                  <th onclick=\"sortTable(0,'hero-triplets');\">".$strings['hero']." 1</th>
                                                  <th onclick=\"sortTable(1,'hero-triplets');\">".$strings['hero']." 2</th>
                                                  <th onclick=\"sortTable(2,'hero-triplets');\">".$strings['hero']." 3</th>
                                                  <th onclick=\"sortTableNum(3,'hero-triplets');\">".$strings['matches']."</th>
                                                  <th onclick=\"sortTableNum(4,'hero-triplets');\">".$strings['winrate']."</th>
                                                </tr>";
          foreach($report['hero_triplets'] as $pair) {
            $modules['heroes']['hero_combos'] .= "<tr".(isset($report['hero_pairs_matches']) ?
                                                " onclick=\"showModal('".
                                                implode($report['hero_pairs_matches'][$pair['heroid1'].'-'.$pair['heroid2'].'-'.$pair['heroid3']], ", ").
                                                                      "', '".$strings['matches']."');\"" : "").">
                                                  <td>".($pair['heroid1'] ?
                                                    "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][ $pair['heroid1'] ]['tag'].
                                                    ".png\" alt=\"".$meta['heroes'][ $pair['heroid1'] ]['tag']."\" /> ".
                                                    $meta['heroes'][ $pair['heroid1'] ]['name'] : "").
                                                 "</td><td>".($pair['heroid2'] ?
                                                   "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][ $pair['heroid2'] ]['tag'].
                                                   ".png\" alt=\"".$meta['heroes'][ $pair['heroid2'] ]['tag']."\" /> ".
                                                   $meta['heroes'][ $pair['heroid2'] ]['name'] : "").
                                                 "</td><td>".($pair['heroid3'] ?
                                                   "<img class=\"hero_portrait\" src=\"res/heroes/".$meta['heroes'][ $pair['heroid3'] ]['tag'].
                                                   ".png\" alt=\"".$meta['heroes'][ $pair['heroid3'] ]['tag']."\" /> ".
                                                   $meta['heroes'][ $pair['heroid3'] ]['name'] : "").
                                                 "</td>
                                                 <td>".$pair['matches']."</td>
                                                 <td>".number_format($pair['winrate']*100,2)."</td>
                                                </tr>";
          }
          $modules['heroes']['hero_combos'] .= "</table>";
        }
      }
    }
  }

  # players
  if (isset($modules['players']) && check_module("players")) {
    if($mod == "players") $unset_module = true;
    $parent = "players-";

    if (isset($report['averages_players'])) {
      $modules['players']['averages_players'] = "";

      if(check_module($parent."averages_players")) {
        foreach($report['averages_players'] as $key => $avg) {
          $modules['players']['averages_players'] .= "<table id=\"avgs-players-".$key."\" class=\"list list-fixed list-small\">
                                                      <caption>".$strings[$key]."</caption>
                                                      <tr class=\"thead\">
                                                        <th>".$strings['player']."</th>
                                                        <th>".$strings['value']."</th>
                                                      </tr>";
          foreach($avg as $player) {
            $modules['players']['averages_players'] .= "<tr>
                                                        <td>".$report['players'][$player['playerid']].
                                                       "</td><td>".number_format($player['value'],2)."</td></tr>";
          }
          $modules['players']['averages_players'] .= "</table>";
        }
      }
    }
    if (isset($report['pvp'])) {
      $pvp = array();
      $modules['players']['pvp'] = array();

      if (check_module($parent."pvp")) {
        foreach($report['players'] as $pid => $pname) {
          $pvp[$pid] = array();
        }
        $player_ids = array_keys($report['players']);

        if($report['settings']['pvp_grid']) {
          $modules['players']['pvp']['grid'] = "";

          $modules['players']['pvp']['grid'] .= "<table  class=\"pvp wide\">";

          $modules['players']['pvp']['grid'] .= "<tr class=\"thead\"><th></th>";
          foreach($report['players'] as $pid => $pname) {
            $modules['players']['pvp']['grid'] .= "<th><span>".$pname."</span></th>";
          }
          $modules['players']['pvp']['grid'] .= "</tr>";
        }

        foreach($pvp as $player_id => $playerline) {
          foreach($player_ids as $pid) {
            $pvp[$player_id][$pid] = array(
              "winrate" => 0,
              "matches" => 0,
              "won" => 0,
              "lost" => 0
            );
          }
        }

        foreach($player_ids as $pid) {
          for($i=0, $end = sizeof($report['pvp']); $i<$end; $i++) {
            if($report['pvp'][$i]['playerid1'] == $pid) {
              $pvp[$pid][$report['pvp'][$i]['playerid2']] = array(
                "winrate" => $report['pvp'][$i]['p1winrate'],
                "matches" => $report['pvp'][$i]['matches'],
                "won" => $report['pvp'][$i]['p1won'],
                "lost" => $report['pvp'][$i]['matches'] - $report['pvp'][$i]['p1won'],
                "matchids" => $report['pvp'][$i]['matchids']
              );
            }
            if($report['pvp'][$i]['playerid2'] == $pid) {
              $pvp[$pid][$report['pvp'][$i]['playerid1']] = array(
                "winrate" => 1-$report['pvp'][$i]['p1winrate'],
                "matches" => $report['pvp'][$i]['matches'],
                "won" => $report['pvp'][$i]['matches'] - $report['pvp'][$i]['p1won'],
                "lost" => $report['pvp'][$i]['p1won']
              );
            }
            if(isset($report['pvp'][$i]['matchids'])) {
              if($report['pvp'][$i]['playerid1'] == $pid)
                $pvp[$pid][$report['pvp'][$i]['playerid2']]['matchids'] = $report['pvp'][$i]['matchids'];
              if($report['pvp'][$i]['playerid2'] == $pid)
                $pvp[$pid][$report['pvp'][$i]['playerid1']]['matchids'] = $report['pvp'][$i]['matchids'];
            }
          }
        }

          if($report['settings']['pvp_grid']) {
          foreach($pvp as $pid => $playerline) {
            $modules['players']['pvp']['grid'] .= "<tr><td>".$report['players'][$pid]."</td>";
            for($i=0, $end = sizeof($player_ids); $i<$end; $i++) {
              if($pid == $player_ids[$i]) {
                $modules['players']['pvp']['grid'] .= "<td class=\"transparent\"></td>";
              } else if($playerline[$player_ids[$i]]['matches'] == 0) {
                $modules['players']['pvp']['grid'] .= "<td>-</td>";
              } else {
                $modules['players']['pvp']['grid'] .= "<td".
                        ($playerline[$player_ids[$i]]['winrate'] > 0.55 ? " class=\"high-wr\"" : (
                              $playerline[$player_ids[$i]]['winrate'] < 0.45 ? " class=\"low-wr\"" : ""
                            )
                          )." onclick=\"showModal('".$strings['matches'].": ".$pvp[$pid][$player_ids[$i]]['matches']
                                ."<br />".$strings['winrate'].": ".number_format($pvp[$pid][$player_ids[$i]]['winrate']*100,2)
                                ."%<br />".$strings['won']." ".$pvp[$pid][$player_ids[$i]]['won']." - "
                                         .$strings['lost']." ".$pvp[$pid][$player_ids[$i]]['lost'].(
                                           isset($pvp[$pid][$player_ids[$i]]['matchids']) ?
                                            "<br />MatchIDs: ".implode($pvp[$pid][$player_ids[$i]]['matchids'], ", ")
                                            : "").
                                "','".$report['players'][$pid]." vs ".$report['players'][$player_ids[$i]]."')\">".
                            number_format($playerline[$player_ids[$i]]['winrate']*100,0)."</td>";
              }
            }
            $modules['players']['pvp']['grid'] .= "</tr>";
          }

          $modules['players']['pvp']['grid'] .= "</table>";
        }

        foreach($pvp as $pid => $playerline) {
          $strings['pid'.$pid] = $report['players'][$pid];



          $modules['players']['pvp']['pid'.$pid] = "<table id=\"player-pvp-$pid\" class=\"list\">";

          $modules['players']['pvp']['pid'.$pid] .= "<tr class=\"thead\">
                                                        <th onclick=\"sortTable(0,'player-pvp-$pid');\">".$strings['opponent']."</th>
                                                        <th onclick=\"sortTableNum(1,'player-pvp-$pid');\">".$strings['winrate']."</th>
                                                        <th onclick=\"sortTableNum(2,'player-pvp-$pid');\">".$strings['matches']."</th>
                                                        <th onclick=\"sortTableNum(3,'player-pvp-$pid');\">".$strings['won']."</th>
                                                        <th onclick=\"sortTableNum(4,'player-pvp-$pid');\">".$strings['lost']."</th>
                                                     </tr>";
          for($i=0, $end = sizeof($player_ids); $i<$end; $i++) {
            if($player_ids[$i] == $pid || $pvp[$pid][$player_ids[$i]]['matches'] == 0) {
              continue;
            } else {
              $modules['players']['pvp']['pid'.$pid] .= "<tr ".(isset($pvp[$pid][$player_ids[$i]]['matchids']) ?
                                                                "onclick=\"showModal('".implode($pvp[$pid][$player_ids[$i]]['matchids'], ", ")."','".$strings['matches']."')\"" :
                                                                "").">
                                                            <td>".$report['players'][$player_ids[$i]]."</th>
                                                            <td>".number_format($pvp[$pid][$player_ids[$i]]['winrate']*100,2)."</th>
                                                            <td>".$pvp[$pid][$player_ids[$i]]['matches']."</th>
                                                            <td>".$pvp[$pid][$player_ids[$i]]['won']."</th>
                                                            <td>".$pvp[$pid][$player_ids[$i]]['lost']."</th>
                                                         </tr>";
            }
          }
          $modules['players']['pvp']['pid'.$pid] .= "</table>";
        }
        unset($pvp);
      }
    }
    if (isset($report['players_combo_graph']) && $report['settings']['players_combo_graph']) {
      $modules['players']['players_combo_graph'] = "";

      if (check_module($parent."players_combo_graph")) {
        if(isset($report['players_combo_graph'])) {
          $use_graph = true;

          $modules['players']['players_combo_graph'] .= "<div id=\"players-combos-graph\"></div><script type=\"text/javascript\">";

          $nodes = "";
          foreach($report['players'] as $pid => $player) {
            $nodes .= "{id: $pid, value: ".$report['players_additional'][$pid]['matches'].", label: '".addslashes($player)."'},";
          }
          $modules['players']['players_combo_graph'] .= "var nodes = [".$nodes."];";

          $nodes = "";
          foreach($report['players_combo_graph'] as $combo) {
            $nodes .= "{from: ".$combo['playerid1'].", to: ".$combo['playerid2'].", value:".$combo['wins']."},";
          }

          $modules['players']['players_combo_graph'] .= "var edges = [".$nodes."];";

          $modules['players']['players_combo_graph'] .= "var container = document.getElementById('players-combos-graph');\n".
                                                      "var data = { nodes: nodes, edges: edges};\n".
                                                      "var options={nodes: { shape: 'dot', scaling:{ label: { min:8, max:20 } } }};\n".
                                                      "var network = new vis.Network(container, data, options);\n".
                                                      "</script>";
        }
      }
    }
    if (isset($report['player_pairs']) || isset($report['player_triplets'])) {
      $modules['players']['player_combos'] = "";

      if(check_module($parent."player_combos")) {
        if(isset($report['player_pairs'])) {
          $modules['players']['player_combos'] .= "<table id=\"player-pairs\" class=\"list\">
                                                <caption>".$strings['player_pairs']."</caption>
                                                <tr class=\"thead\">
                                                  <th onclick=\"sortTable(0,'player-pairs');\">".$strings['player']." 1</th>
                                                  <th onclick=\"sortTable(1,'player-pairs');\">".$strings['player']." 2</th>
                                                  <th onclick=\"sortTableNum(2,'player-pairs');\">".$strings['matches']."</th>
                                                  <th onclick=\"sortTableNum(3,'player-pairs');\">".$strings['winrate']."</th>
                                                </tr>";
          foreach($report['player_pairs'] as $pair) {
            $modules['players']['player_combos'] .= "<tr".(isset($report['player_pairs_matches']) ?
                            " onclick=\"showModal('".implode($report['player_pairs_matches'][$pair['playerid1'].'-'.$pair['playerid2']], ", ").
                                  "', '".$strings['matches']."');\"" : "").">
                                                  <td>".$report['players'][ $pair['playerid1'] ]."</td>
                                                  <td>".$report['players'][ $pair['playerid2'] ]."</td>
                                                 <td>".$pair['matches']."</td>
                                                 <td>".number_format($pair['winrate']*100,2)."</td>
                                                </tr>";
          }
          $modules['players']['player_combos'] .= "</table>";
        }

        if (isset($report['player_triplets'])) {
          $modules['players']['player_combos'] .= "<table id=\"player-triplets\" class=\"list\">
                                                <caption>".$strings['player_triplets']."</caption>
                                                <tr class=\"thead\">
                                                  <th onclick=\"sortTable(0,'player-triplets');\">".$strings['player']." 1</th>
                                                  <th onclick=\"sortTable(1,'player-triplets');\">".$strings['player']." 2</th>
                                                  <th onclick=\"sortTable(2,'player-triplets');\">".$strings['player']." 3</th>
                                                  <th onclick=\"sortTableNum(3,'player-triplets');\">".$strings['matches']."</th>
                                                  <th onclick=\"sortTableNum(4,'player-triplets');\">".$strings['winrate']."</th>
                                                </tr>";
          foreach($report['player_triplets'] as $pair) {
            $modules['players']['player_combos'] .= "<tr".(isset($report['player_triplets_matches']) ?
                            " onclick=\"showModal('".implode($report['player_triplets_matches'][$pair['playerid1'].'-'.$pair['playerid2'].'-'.$pair['playerid3']], ", ").
                                  "', '".$strings['matches']."');\"" : "").">
                                                  <td>".$report['players'][ $pair['playerid1'] ]."</td>
                                                  <td>".$report['players'][ $pair['playerid2'] ]."</td>
                                                  <td>".$report['players'][ $pair['playerid3'] ]."</td>
                                                 <td>".$pair['matches']."</td>
                                                 <td>".number_format($pair['winrate']*100,2)."</td>
                                                </tr>";
          }
          $modules['players']['player_combos'] .= "</table>";
        }
      }
    }
    if (isset($report['player_positions'])) {
      $modules['players']['player_positions'] = array();

      if(check_module($parent."player_positions")) {
        for ($i=0; $i<2 && !isset($keys); $i++) {
          for ($j=1; $j<6 && $j>0; $j++) {
            if (!$i) { $j = 0; }
            if(isset($report['player_positions'][$i][$j][0])) {
              $keys = array_keys($report['player_positions'][$i][$j][0]);
              break;
            }
            if (!$i) { break; }
          }
        }

        for ($i=0; $i<2; $i++) {
          for ($j=1; $j<6 && $j>0; $j++) {
            if (!$i) { $j = 0; }

            if(!isset($strings["positions_$i"."_$j"]))
              $strings["positions_$i"."_$j"] = ($i ? $strings['core'] : $strings['support'])." ".$meta['lanes'][$j];

            if(sizeof($report['player_positions'][$i][$j])) {
              $modules['players']['player_positions']["positions_$i"."_$j"]  = "";
              $modules['players']['player_positions']["positions_$i"."_$j"] .= "<table id=\"players-positions-$i-$j\" class=\"list wide\">
                                                <tr class=\"thead\">
                                                  <th onclick=\"sortTable(0,'players-positions-$i-$j');\">".$strings['player']."</th>";
              for($k=1, $end=sizeof($keys); $k < $end; $k++) {
                $modules['players']['player_positions']["positions_$i"."_$j"] .= "<th onclick=\"sortTableNum($k,'players-positions-$i-$j');\">".$strings[$keys[$k]]."</th>";
              }
              $modules['players']['player_positions']["positions_$i"."_$j"] .= "</tr>";


              foreach($report['player_positions'][$i][$j] as $player) {

                $modules['players']['player_positions']["positions_$i"."_$j"] .= "<tr".(isset($report['player_positions_matches']) ?
                                                                          " onclick=\"showModal('".implode($report['player_positions_matches'][$i][$j][$player['playerid']], ", ").
                                                                          "', '".$report['players'][$player['playerid']]." - ".
                                                                          $strings["positions_$i"."_$j"]." - ".$strings['matches']."');\"" : "").">
                                                    <td>".$report['players'][$player['playerid']]."</td>
                                                    <td>".$player['matches_s']."</td>
                                                    <td>".number_format($player['winrate_s']*100,1)."%</td>";
                for($k=3, $end=sizeof($keys); $k < $end; $k++) {
                  $modules['players']['player_positions']["positions_$i"."_$j"] .= "<td>".number_format($player[$keys[$k]],1)."</td>";
                }
                $modules['players']['player_positions']["positions_$i"."_$j"] .= "</tr>";
              }
              $modules['players']['player_positions']["positions_$i"."_$j"] .= "</table>";
            }
            if (!$i) { break; }
          }
        }
        unset($keys);
      }
    }
  }
  # teams

  # matches
  if (isset($modules['matches']) && check_module("matches")) {
    $modules['matches'] = "<div class=\"content-cards\">";
    foreach($report['matches'] as $matchid => $match) {
      $modules['matches'] .= match_card($matchid, $report, $meta, $strings);
    }
    $modules['matches'] .= "</div>";
  }

  # participants
  if(isset($modules['participants']) && check_module("participants")) {


    $modules['participants']['players'] = "<div class=\"content-cards\">";
    foreach($report['players'] as $player_id => $player) {
      $modules['participants']['players'] .= player_card($player_id, $report, $meta, $strings);
    }
    $modules['participants']['players'] .= "</div>";
  }
}
  ?>
  <!DOCTYPE html>
  <html>
    <head>
      <link rel="shortcut icon" href="/favicon.ico" />
      <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
      <title>League Report</title>
      <link href="res/valve_mimic.css" rel="stylesheet" type="text/css" />
      <link href="res/reports.css" rel="stylesheet" type="text/css" />
      <?php if(isset($report['settings']['custom_style']) && file_exists("res/custom_colors_".$report['settings']['custom_style'].".css"))
                echo "<link href=\"res/custom_colors_".$report['settings']['custom_style'].".css\" rel=\"stylesheet\" type=\"text/css\" />";
            if($use_graph) {
              echo "<script type=\"text/javascript\" src=\"http://visjs.org/dist/vis.js\"></script>";
              echo "<link href=\"http://visjs.org/dist/vis-network.min.css\" rel=\"stylesheet\" type=\"text/css\" />";
            }
       ?>
    </head>
    <body onload="draw()">
      <header class="navBar">
        <span class="navItem dotalogo"><a href="http://spectralalliance.ru/dota"></a></span>
        <span class="navItem"><a href="http://spectralalliance.ru/dota-reports" target="_blank" alt="Dota 2 League Reports">League Reports</a></span>
        <span class="navItem"><a href="https://vk.com/spectraldota" target="_blank" alt="SpectrAl /Dota VK">VK</a></span>
        <span class="navItem"><a href="https://vk.com/thecybersport" target="_blank" alt="TheCyberSport">TheCyberSport</a></span>
        <div class="share-links">
          <?php
            echo '<div class="share-link reddit"><a href="http://www.reddit.com/submit?url='.'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'?'.$_SERVER['QUERY_STRING'].'" target="_blank">Share on Reddit</a></div>';
            echo '<div class="share-link twitter"><a href="http://twitter.com/share?text=League Report: '.$leaguetag.' - '.'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'?'.$_SERVER['QUERY_STRING'].'" target="_blank">Share on Twitter</a></div>';
            echo '<div class="share-link vk"><a href="https://vk.com/share.php?url='.'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'?'.$_SERVER['QUERY_STRING'].'" target="_blank">Share on VK</a></div>';
            echo '<div class="share-link fb"><a href="https://www.facebook.com/sharer/sharer.php?u='.'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'?'.$_SERVER['QUERY_STRING'].'" target="_blank">Share on Facebook</a></div>';
          ?>
        </div>
      </header>
      <div id="content-wrapper">
      <?php if (!empty($leaguetag)) { ?>
        <div id="header-image" class="section-header">
          <h1><?php echo $report['leaguetag']; ?></h1>
          <h2><?php echo $report['leaguedesc']; ?></h2>
          <h3><?php echo $strings[$h3].": ".$report['random'][$h3]; ?></h3>
        </div>
        <div id="main-section" class="content-section">
          <div id="content-top">
            <div class="content-header"><?php echo $random_caption; ?></div>
            <div class="content-text"><?php echo $random_text; ?></div>
          </div>
<?php

$output = join_selectors($modules, 0);

echo $output;

?>
          </div>
        </div>
      <?php } else { ?>
        <div id="header-image" class="section-header">
          <h1>League Report Generator</h1>
        </div>
        <div id="main-section" class="content-section">
          <div id="content-top">
            <div class="content-header"><?php echo $strings['noleague_cap']; ?></div>
            <div class="content-text"><?php echo $strings['noleague_desc']; ?></div>
          </div>
        </div>
      <?php } ?>
        <footer>
          Dota 2 is a registered trademark of Valve Corporation.<br />
          Match replay data analyzed by OpenDota.<br />
          Made by Spectral Alliance with support of TheCyberSport.<br />
          Klozi is a registered trademark of Grafensky.<br />
          All changes can be discussed on Spectral Alliance discord channel and on github.
        </footer>
        <div class="modal" id="modal-box">
          <div class="modal-content">
            <div class="modal-header"></div>
            <div id="modal-text" class="modal-text"></div>
            <div id="modal-sublevel" class="modal-sublevel"></div>
          </div>
        </div>
        <script type="text/javascript" src="res/reports.js"></script>
      </body>
    </html>
