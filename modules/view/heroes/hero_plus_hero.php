<?php

$modules['heroes']['hph'] = [];
unset($modules['heroes']['combos']);

function rg_view_generate_heroes_hph() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings;
  if($mod == $parent."hph") $unset_module = true;
  $parent_module = $parent."hph-";
  $res = [];
  include_once($root."/modules/view/generators/hph.php");

  if (is_wrapped($report['hph'])) {
    $report['hph'] = unwrap_data($report['hph']);
  }

  $hnames = $meta["heroes"];

  uasort($hnames, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  $res['combos'] = "";
  if (check_module($parent_module."combos")) {
    $parent = $parent_module;
    $res['combos'] = rg_view_generate_heroes_combos();
  }

  foreach($hnames as $hid => $name) {
    $strings['en']["heroid".$hid] = hero_name($hid);
    $res["heroid".$hid] = "";

    if(check_module($parent_module."heroid".$hid)) {
      if($mod == $parent_module."heroid".$hid) $unset_module = true;
      $parent_module = $parent_module."heroid".$hid."-";

      $res["heroid".$hid] = [
        'total' => ""
      ];

      if(check_module($parent_module."total")) {
        if (!empty($report['hph'][$hid])) {
          foreach ($report['hph'][$hid] as $id => $line) {
            if ($id == '_h') {
              unset($report['hph'][$hid][$id]);
              continue;
            }
            if ($line === null) unset($report['hph'][$hid][$id]);
            if (is_array($line) && $line['matches'] === -1) $report['hph'][$hid][$id] = $report['hph'][$id][$hid];
          }

          // $res["heroid".$hid] = "<div class=\"content-text\">".locale_string("desc_heroes_hph")."</div>";
          
          $res["heroid".$hid]["total"] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
            "<div class=\"explain-content\">".
              "<div class=\"line\">".locale_string("pairs_desc")."</div>".
              "<div class=\"line\">".locale_string("pairs_desc_2")."</div>".
            "</div>".
          "</details>";
          $res["heroid".$hid]["total"] .= rg_generator_hph_profile("$parent_module-$hid", $report['hph'][$hid], $report['pickban'], $hid);
        } else {
          $res["heroid".$hid]["total"] .= "<div class=\"content-text\">".locale_string("stats_empty")."</div>";
        }
      }

      if (isset($report['hph_v'])) {
        $res["heroid".$hid]['variants'] = "";

        $_meta_facets = $meta['facets']['heroes'][$hid];

        if(check_module($parent_module."variants")) {
          $res["heroid".$hid]['variants'] = [];

          if (is_wrapped($report['hph_v'])) {
            $report['hph_v'] = unwrap_data($report['hph_v']);
          }

          if($mod == $parent_module."variants") $unset_module = true;
          $parent_module = $parent_module."variants-";

          $variants = get_hero_variants_list($hid);
          global $locale;
          include_locale($locale, "facets");

          $res["heroid".$hid]["variants"]["total"] = "";
          if(check_module($parent_module."total")) {
            $report['hero_variants'][$hid."-x"] = [
              'm' => 0,
              'w' => 0,
              'f' => 1,
            ];
            $report['hph_v'][$hid."-x"] = [];
            foreach ($variants as $i => $tag) {
              $i++;
              if (empty($report['hero_variants'][$hid."-".$i])) continue;
              $report['hero_variants'][$hid."-x"]['m'] += $report['hero_variants'][$hid."-".$i]['m'];
              $report['hero_variants'][$hid."-x"]['w'] += $report['hero_variants'][$hid."-".$i]['w'];

              foreach ($report['hph_v'][$hid."-".$i] as $opid => $data) {
                if (empty($data) || $data['matches'] == -1) continue;
                
                if (!isset($report['hph_v'][$hid."-x"][$opid])) {
                  $report['hph_v'][$hid."-x"][$opid] = [
                    "matches" => 0,
                    "exp" => 0,
                    "won" => 0,
                    "lost" => 0,
                    "winrate" => null,
                    "diff" => null,
                    "lane_rate" => 0,
                    "lane_wr" => 0,
                  ];
                }

                $report['hph_v'][$hid."-x"][$opid]['matches'] += $data['matches'];
                $report['hph_v'][$hid."-x"][$opid]['exp'] += $data['exp'];
                $report['hph_v'][$hid."-x"][$opid]['won'] += $data['won'];
                $report['hph_v'][$hid."-x"][$opid]['lane_rate'] += round($data['lane_rate'] * $data['matches']);
                $report['hph_v'][$hid."-x"][$opid]['lane_wr'] += $data['lane_rate'] * $data['matches'] * $data['lane_wr'];
              }
            }
            if (empty($report['hero_variants'][$hid."-x"]['m'])) continue;
            $wr = $report['hero_variants'][$hid."-x"]['w']/$report['hero_variants'][$hid."-x"]['m'];
            foreach ($report['hph_v'][$hid."-x"] as $opid => $data) {
              if (!$data['matches']) continue;
              $report['hph_v'][$hid."-x"][$opid]['winrate'] = $data['won']/$data['matches'];
              $report['hph_v'][$hid."-x"][$opid]['diff'] = $report['hph_v'][$hid."-x"][$opid]['winrate'] - $wr;
              $report['hph_v'][$hid."-x"][$opid]['lane_wr'] = $data['lane_wr']/($data['lane_rate'] ?: 1);
              $report['hph_v'][$hid."-x"][$opid]['lane_rate'] = $data['lane_rate']/$data['matches'];
              $report['hph_v'][$hid."-x"][$opid]['lost'] = $data['matches'] - $data['won'];
            }

            $res["heroid".$hid]["variants"]["total"] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
                "<div class=\"explain-content\">".
                  "<div class=\"line\">".locale_string("desc_heroes_hvh_2")."</div>".
                "</div>".
              "</details>";
            $res["heroid".$hid]["variants"]["total"] .= rg_generator_hph_profile("$parent_module-$hid-total", $report['hph_v'][$hid."-x"], $report['hero_variants'], $hid."-x", true, true);
          }

          foreach ($variants as $i => $tag) {
            $i++;

            $is_deprecated = $_meta_facets[$i-1]['deprecated'] ?? false;
            
            if ($is_deprecated && empty($report['hero_variants'][$hid."-".$i])) continue;

            register_locale_string(locale_string("#facet::".$tag), "variant$i");

            $res["heroid".$hid]["variants"]["variant$i"] = "";
            if(check_module($parent_module."variant$i")) {
              $hvid = $hid.'-'.$i;
              if (!isset($report['hph_v'][$hvid])) continue;
              foreach ($report['hph_v'][$hvid] as $id => $line) {
                if ($id == '_h') {
                  unset($report['hph_v'][$hvid][$id]);
                  continue;
                }
                if ($line === null) unset($report['hph_v'][$hvid][$id]);
                if (is_array($line) && $line['matches'] === -1) $report['hph_v'][$hvid][$id] = $report['hph_v'][$id][$hvid];
              }

              if (empty($report['hero_variants'][$hid."-".$i])) {
                $res["heroid".$hid]["variants"]["variant$i"] .= "<div class=\"content-text\">".locale_string("stats_empty")."</div>";
              } else {
                $res["heroid".$hid]["variants"]["variant$i"] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
                  "<div class=\"explain-content\">".
                    "<div class=\"line\">".locale_string("pairs_desc")."</div>".
                    "<div class=\"line\">".locale_string("pairs_desc_2")."</div>".
                  "</div>".
                  "</details>";
                $res["heroid".$hid]["variants"]["variant$i"] .= rg_generator_hph_profile("$parent_module-$hvid", $report['hph_v'][$hvid], $report['hero_variants'], $hvid, true, true);
              }
            }
          }
        }
      }
    }
  }

  return $res;
}
