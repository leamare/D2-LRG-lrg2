<?php 

$endpoints['fantasy'] = function($mods, $vars, &$report) {
  $res = [];

  if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ];
  } else {
    $context =& $report;
  }

  if (in_array("players", $mods)) {
    if (is_wrapped($context['fantasy']['players_mvp'])) $context['fantasy']['players_mvp'] = unwrap_data($context['fantasy']['players_mvp']);
    $res = $context['fantasy']['players_mvp'];
    $res['__endp'] = "players-fantasy";
  } else if (in_array("heroes", $mods)) {
    if (is_wrapped($context['fantasy']['heroes_mvp'])) $context['fantasy']['heroes_mvp'] = unwrap_data($context['fantasy']['heroes_mvp']);
    $res = $context['fantasy']['heroes_mvp'];
    $res['__endp'] = "heroes-fantasy";
  } else {
    throw new \Exception("What kind of fantasy data do you need?");
  }

  return $res;
};
