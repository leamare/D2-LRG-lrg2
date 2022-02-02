<?php

include_once($root."/modules/view/generators/meta_graph.php");

function rg_view_generate_regions_heroes_meta_graph($region, $reg_report) {

  $locale_settings = ["lim" => $reg_report['settings']['limiter_graph']+1,
      "per" => "35%"
  ];


  $res = "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line\">".locale_string("desc_meta_graph", $locale_settings)."</div>".
      "<div class=\"line\">".locale_string("desc_meta_graph_add", $locale_settings)."</div>".
    "</div>".
  "</details>";
  $res .= rg_generator_meta_graph("region$region-heroes-meta-graph", $reg_report['heroes_meta_graph'], $reg_report['pickban']);

  return $res;
}

?>
