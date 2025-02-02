<?php

if ($lg_settings['ana']['avg_heroes']) {
  # average for heroes
  require_once("modules/analyzer/heroes/averages.php");
}

if ($lg_settings['ana']['draft_stages']) {
  # pick/ban draft stages stats
  require_once("modules/analyzer/heroes/draft.php");
}

if ($lg_settings['ana']['hero_positions']) {
  # heroes on positions
  require_once("modules/analyzer/heroes/positions.php");
}

if ($lg_settings['ana']['hero_sides']) {
  # heroes factions
  require_once("modules/analyzer/heroes/sides.php");
}

if ($lg_settings['ana']['hero_pairs'] && !$lg_settings['ana']['full_hero_pairs']) {
  # heroes pairs
  require_once("modules/analyzer/heroes/pairs.php");
}

if ($lg_settings['ana']['hero_combos_graph'] && !$lg_settings['ana']['full_hero_pairs']) {
  # heroes combo graph
  require_once("modules/analyzer/heroes/combo_graph.php");
}

if ($lg_settings['ana']['full_hero_pairs']) {
  require_once("modules/analyzer/heroes/full_combos.php");
}

if ($lg_settings['ana']['hero_triplets']) {
  # heroes trios
  require_once("modules/analyzer/heroes/trios.php");
}

if ($lg_settings['ana']['hero_lane_combos']) {
  # heroes lane combos
  require_once("modules/analyzer/heroes/lane_combos.php");
}

if ($lg_settings['ana']['hero_vs_hero']) {
  # hero vs hero
  require_once("modules/analyzer/heroes/versus_hero.php");
}

if ($lg_settings['ana']['hero_summary']) {
  # heroes summary
  require_once("modules/analyzer/heroes/summary.php");
}

if ($lg_settings['ana']['hero_laning']) {
  # heroes laning
  require_once("modules/analyzer/heroes/laning.php");
}

if ($lg_settings['ana']['heroes_daily_winrate'] && !empty($result["first_match"])) {
  # heroes laning
  require_once("modules/analyzer/heroes/daily_wr.php");
}

if ($lg_settings['ana']['heroes_winrate_boxplots']) {
  # heroes laning
  require_once("modules/analyzer/heroes/winrate_timings.php");
}

if ($lg_settings['ana']['heroes_winrate_spammers']) {
  # heroes laning
  require_once("modules/analyzer/heroes/winrate_spammers.php");
}

if ($schema['variant']) {
  if ($lg_settings['ana']['hero_vs_hero']) {
    require_once("modules/analyzer/heroes/versus_hero_variants.php");
    if ($lg_settings['ana']['hero_laning']) {
      require_once("modules/analyzer/heroes/laning_variants.php");
    }

    $result["hvh_v"] = wrap_data($result["hvh_v"], true, true, true);
  }

  if ($lg_settings['ana']['full_hero_pairs']) {
    require_once("modules/analyzer/heroes/full_combos_variants.php");

    // if ($lg_settings['ana']['hero_lane_combos']) {
    //   require_once("modules/analyzer/heroes/laning_combos_variants.php");
    // }
    
    $result["hph_v"] = wrap_data($result["hph_v"], true, true, true);
  }
  
  if ($lg_settings['ana']['heroes_daily_winrate'] && !empty($result["first_match"])) {
    # heroes laning
    require_once("modules/analyzer/heroes/daily_wr_variants.php");
  }

  if ($lg_settings['ana']['hero_summary']) {
    # heroes summary
    require_once("modules/analyzer/heroes/summary_variants.php");
  }
}