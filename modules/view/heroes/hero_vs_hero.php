<?php

include_once($root."/modules/view/generators/pvp_unwrap_data.php");
include_once($root."/modules/view/generators/pvp_profile.php");

$modules['heroes']['hvh'] = [];

function rg_view_generate_heroes_hvh() {
  global $report, $mod, $parent, $strings, $meta, $unset_module;
  if($mod == $parent."hvh") $unset_module = true;
  $parent_module = $parent."hvh-";

  $hnames = $meta["heroes"];

  uasort($hnames, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  $res['counters'] = "";
  if (check_module($parent_module."counters")) {
    $parent = $parent_module;
    $res['counters'] = rg_view_generate_heroes_counters();
  }

  foreach($hnames as $hid => $name) {
    $strings['en']["heroid".$hid] = hero_name($hid);
    $res["heroid".$hid] = "";

    if(check_module($parent_module."heroid".$hid)) {
      if($mod == $parent_module."heroid".$hid) $unset_module = true;
      $parent_module = $parent_module."heroid".$hid."-";

      $res["heroid".$hid] = [];

      $res["heroid".$hid]['total'] = "";
      if(check_module($parent_module."total")) {
        $hvh = rg_generator_pvp_unwrap_data($report['hvh'], $report['pickban']);

        if (isset($report['hero_laning'])) {
          if (is_wrapped($report['hero_laning'])) {
            $report['hero_laning'] = unwrap_data($report['hero_laning']);
          }

          foreach(($report['hero_laning'][$hid] ?? []) as $opid => $hero) {
            if (empty($hvh[$hid][$opid])) continue;
            $hvh[$hid][$opid]['lane_rate'] = ($hero['matches'] ?? 0)/$hvh[$hid][$opid]['matches'];
            $hvh[$hid][$opid]['lane_wr'] = $hero['lane_wr'] ?? 0;
          }
        }
        
        $res["heroid".$hid]['total'] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
            "<div class=\"explain-content\">".
              "<div class=\"line\">".locale_string("desc_heroes_hvh_2")."</div>".
            "</div>".
          "</details>";
        $res["heroid".$hid]['total'] .= rg_generator_pvp_profile("hero-hvh-$hid-total", $hvh[$hid], $report['pickban'], $hid);
      }

      if (isset($report['hvh_v'])) {
        $res["heroid".$hid]['variants'] = "";

        $_meta_facets = $meta['facets']['heroes'][$hid];

        if(check_module($parent_module."variants")) {
          $res["heroid".$hid]['variants'] = [];

          $hvh = rg_generator_pvp_unwrap_data($report['hvh_v'], $report['hero_variants'], true, true);

          if($mod == $parent_module."variants") $unset_module = true;
          $parent_module = $parent_module."variants-";

          $variants = get_hero_variants_list($hid);
          if (isset($hvh[$hid.'-0'])) {
            array_unshift($variants, "_no_variant_");
          }
          global $locale;
          include_locale($locale, "facets");

          $res["heroid".$hid]["variants"]["total"] = "";
          if(check_module($parent_module."total")) {
            $report['hero_variants'][$hid."-x"] = [
              'm' => 0,
              'w' => 0,
              'f' => 1,
            ];
            $hvh[$hid."-x"] = [];
            foreach ($variants as $i => $tag) {
              $i++;
              if (empty($report['hero_variants'][$hid."-".$i])) continue;
              $report['hero_variants'][$hid."-x"]['m'] += $report['hero_variants'][$hid."-".$i]['m'];
              $report['hero_variants'][$hid."-x"]['w'] += $report['hero_variants'][$hid."-".$i]['w'];

              foreach ($hvh[$hid."-".$i] as $opid => $data) {
                if (!isset($hvh[$hid."-x"][$opid])) {
                  $hvh[$hid."-x"][$opid] = [
                    "matches" => 0,
                    "expectation" => 0,
                    "won" => 0,
                    "lost" => 0,
                    "winrate" => null,
                    "diff" => null,
                    "lane_rate" => 0,
                    "lane_wr" => 0,
                  ];
                }

                $hvh[$hid."-x"][$opid]['matches'] += $data['matches'];
                $hvh[$hid."-x"][$opid]['expectation'] += $data['expectation'];
                $hvh[$hid."-x"][$opid]['won'] += $data['won'];
                $hvh[$hid."-x"][$opid]['lane_rate'] += round($data['lane_rate'] * $data['matches']);
                $hvh[$hid."-x"][$opid]['lane_wr'] += $data['lane_rate'] * $data['matches'] * $data['lane_wr'];
              }
            }
            $wr = $report['hero_variants'][$hid."-x"]['w']/$report['hero_variants'][$hid."-x"]['m'];
            foreach ($hvh[$hid."-x"] as $opid => $data) {
              $hvh[$hid."-x"][$opid]['winrate'] = $data['won']/$data['matches'];
              $hvh[$hid."-x"][$opid]['diff'] = $hvh[$hid."-x"][$opid]['winrate'] - $wr;
              $hvh[$hid."-x"][$opid]['lane_wr'] = $data['lane_wr']/($data['lane_rate'] ?: 1);
              $hvh[$hid."-x"][$opid]['lane_rate'] = $data['lane_rate']/$data['matches'];
              $hvh[$hid."-x"][$opid]['lost'] = $data['matches'] - $data['won'];
            }

            $res["heroid".$hid]["variants"]["total"] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
                "<div class=\"explain-content\">".
                  "<div class=\"line\">".locale_string("desc_heroes_hvh_2")."</div>".
                "</div>".
              "</details>";
            $res["heroid".$hid]["variants"]["total"] .= rg_generator_pvp_profile("hero-hvh-$hid-total", $hvh[$hid."-x"], $report['hero_variants'], $hid."-x", true, true);
          }

          foreach ($variants as $i => $tag) {
            $i++;

            $is_deprecated = $_meta_facets[$i-1]['deprecated'] ?? false;

            if ($is_deprecated && empty($report['hero_variants'][$hid."-".$i])) continue;

            register_locale_string(locale_string("#facet::".$tag), "variant$i");

            $res["heroid".$hid]["variants"]["variant$i"] = "";
            if(check_module($parent_module."variant$i")) {
              if (empty($report['hero_variants'][$hid."-".$i])) {
                $res["heroid".$hid]["variants"]["variant$i"] .= "<div class=\"content-text\">".locale_string("stats_empty")."</div>";
              } else {
                $res["heroid".$hid]["variants"]["variant$i"] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
                    "<div class=\"explain-content\">".
                      "<div class=\"line\">".locale_string("desc_heroes_hvh_2")."</div>".
                    "</div>".
                  "</details>";
                $res["heroid".$hid]["variants"]["variant$i"] .= rg_generator_pvp_profile("hero-hvh-$hid-variant$i", $hvh[$hid."-".$i], $report['hero_variants'], $hid."-".$i, true, true);
              }
            }
          }
        }
      }
    }
  }

  return $res;
}

?>
