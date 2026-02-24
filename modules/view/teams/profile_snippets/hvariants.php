<?php 


// $res["team".$tid]['heroes']['hvariants'] = "";

$table_id = "team-$tid-heroes-hvariants";

$context_total_matches = $context[$tid]['matches_total'];

$context_copy = array_values($context[$tid]['hvariants']);
$ccount = count($context_copy);

uasort($context_copy, function($a, $b) {
  return $a['m'] <=> $b['m'];
});
$mp = isset($context_copy[ round($ccount*0.5) ]) ? $context_copy[ round($ccount*0.5) ]['m'] : 1;

uasort($context[$tid]['hvariants'], function($a, $b) {
  if($a['m'] == $b['m']) return 0;
  else return ($a['m'] < $b['m']) ? 1 : -1;
});

$res_local = "";

$res_local = "<div class=\"content-text\">".locale_string("heroes_median_picks").
  " (mp): $mp - ".locale_string("matches_total").": $context_total_matches</div>";

$res_local .= filter_toggles_component($table_id, [
  'mp' => [
    'value' => '0.9',
    'label' => 'data_filter_low_values_mp'
  ],
], $table_id);

$res_local .= search_filter_component($table_id);

$res_local .=  "<table id=\"$table_id\" class=\"list sortable\"><thead><tr>".
  "<th class=\"sorter-no-parser\" colspan=\"2\" width=\"1%\"></th>".
  "<th data-sortInitialOrder=\"asc\">".locale_string("hero")."</th>".
  "<th class=\"separator\">".locale_string("ratio")."</th>".
  "<th>".locale_string("rank")."</th>".
  "<th>".locale_string("antirank")."</th>".
  "<th class=\"separator\">".locale_string("matches_picked")."</th>".
  "<th>".locale_string("winrate")."</th>".
  "<th>".locale_string("mp")."</th>".
"</tr></thead>";

$ranks = [];
$antiranks = [];

$context_copy = array_map(function($el) {
  return [
    'matches_picked' => $el['m'],
    'winrate_picked' => $el['w']/$el['m'],
    'ratio' => $el['f'],
    'matches_banned' => 0,
    'winrate_banned' => 0,
  ];
}, $context[$tid]['hvariants']);

$compound_ranking_sort = function($a, $b) use ($context_total_matches) {
  return compound_ranking_sort($a, $b, $context_total_matches);
};

compound_ranking($context_copy, $context_total_matches);

uasort($context_copy, function($a, $b) {
  return $b['wrank'] <=> $a['wrank'];
});

$min = end($context_copy)['wrank'];
$max = reset($context_copy)['wrank'];

foreach ($context_copy as $id => $el) {
  $ranks[$id] = ($max != $min) ? 100 * ($el['wrank']-$min) / ($max-$min) : 0;
}

foreach($context_copy as &$el)  {
  $el['winrate_picked'] = 1-$el['winrate_picked'];
  $el['winrate_banned'] = 1-$el['winrate_banned'];
}

uasort($context_copy, $compound_ranking_sort);

compound_ranking($context_copy, $context_total_matches);

uasort($context_copy, function($a, $b) {
  return $b['wrank'] <=> $a['wrank'];
});

$min = end($context_copy)['wrank'];
$max = reset($context_copy)['wrank'];

foreach ($context_copy as $id => $el) {
  $antiranks[$id] = ($max != $min) ? 100 * ($el['wrank']-$min) / ($max-$min) : 0;
}

unset($context_copy);

foreach($context[$tid]['hvariants'] as $id => $el) {
  $_id = $id;
  [ $id, $v ] = explode('-', $id);
  $el_mp = number_format($el['m']/$mp, 1);
  $res_local .=  "<tr data-value-mp=\"$el_mp\">".
    "<td>".hero_portrait($id)."</td>".
    "<td>".facet_micro_element($id, $v)."</td>".
    "<td>".hero_link($id)." ".locale_string('facet_short').$v."</td>".
    "<td class=\"separator\">".number_format($el['f']*100,2)."%</td>".
    "<td>".number_format($ranks[$_id],2)."</td>".
    "<td>".number_format($antiranks[$_id],2)."</td>".
    "<td class=\"separator\">".$el['m']."</td>".
    "<td>".number_format(($el['w']/$el['m'])*100,2)."%</td>".
    "<td>".$el_mp."</td>".
  "</tr>";
}

$res_local .= "</table>";

$res["team".$tid]['heroes']['hvariants'] = $res_local;