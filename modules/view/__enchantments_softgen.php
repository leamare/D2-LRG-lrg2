<?php

// Pre-enchantments migration: estimate real enchantments stats based on old data
if (isset($report['items']['stats']) && !isset($report['items']['enchantments'])) {
  $enchantment_items = [];
  $enchantment_categories = [];
  
  foreach (array_keys($meta['item_categories']) as $i => $category_name) {
    if (strpos($category_name, 'enhancement_tier_') === 0) {
      $enchantment_categories[$i] = $category_name;
      foreach ($meta['item_categories'][$category_name] as $item_id) {
        $enchantment_items[$item_id] = $i;
      }
    }
  }
  
  if (!empty($enchantment_items)) {
    if (is_wrapped($report['items']['stats'])) {
      $report['items']['stats'] = unwrap_data($report['items']['stats']);
    }
    
    $has_enchantments = false;
    foreach (array_keys($enchantment_items) as $item_id) {
      if (isset($report['items']['stats']['total'][$item_id])) {
        $has_enchantments = true;
        break;
      }
    }
    
    if ($has_enchantments) {
      $enchantments = [];
      
      $tier_timings = [
        1 => [-90, 900],    // 0-15 minutes
        2 => [900, 1500],   // 15-25 minutes
        3 => [1500, 2100],  // 25-35 minutes
        4 => [2100, 3600],  // 35-60 minutes
        5 => [3600, PHP_INT_MAX], // 60+ minutes
      ];
      
      $cat_to_tier = [];
      $tier_num = 1;
      foreach ($enchantment_categories as $cat_id => $cat_name) {
        $cat_to_tier[$cat_id] = $tier_num++;
      }
      
      foreach ($enchantment_items as $item_id => $first_cat_id) {
        $item_tiers = [];
        foreach ($enchantment_categories as $cat_id => $cat_name) {
          if (in_array($item_id, $meta['item_categories'][$cat_name])) {
            $item_tiers[] = $cat_id;
          }
        }
        sort($item_tiers);
        
        if (empty($item_tiers)) continue;
        
        $first_tier_id = $item_tiers[0];
        $num_tiers = count($item_tiers);
        
        foreach ($report['items']['stats'] as $hero_id => $items) {
          if (!isset($items[$item_id])) continue;
          
          $item_data = $items[$item_id];
          
          $total_matches = (int)$item_data['matchcount'];
          
          $median_timing = (int)$item_data['median'];
          $q1_timing = (int)$item_data['q1'];
          $q3_timing = (int)$item_data['q3'];
          $early_wr = (float)$item_data['early_wr'];
          $late_wr = (float)$item_data['late_wr'];
          $avg_wr = (float)$item_data['winrate'];
          $grad = (float)$item_data['grad'];
          
          // Estimate distribution across tiers based on timing data
          // Old data only recorded the first pickup, so heavily weight the first tier
          $tier_distribution = [];
          
          // Always use the first tier the item exists in as the primary tier
          // (items are picked up as soon as they become available)
          $first_tier_idx = 0;
          
          // Check if median timing suggests most pickups happen in a later tier
          // If median is significantly later than the first tier, reduce first tier weight slightly
          $first_cat_id = $item_tiers[0];
          $first_game_tier = $cat_to_tier[$first_cat_id];
          list($first_min_time, $first_max_time) = $tier_timings[$first_game_tier];
          
          // Adjust base weight based on how far median is from first tier
          $base_weight = 0.60; // Start with 60% for first tier
          
          if ($median_timing > $first_max_time) {
            // Median is beyond first tier window
            // Reduce first tier weight based on how much later the median is
            $time_beyond = $median_timing - $first_max_time;
            $minutes_beyond = $time_beyond / 60;
            
            // Reduce by 2% per minute beyond (capped at reducing to 30%)
            $reduction = min(0.20, $minutes_beyond * 0.02);
            $base_weight = 0.50 - $reduction;
          }
          
          $tiers_after = 0;
          $tiers_before = 0;
          foreach ($item_tiers as $idx => $tier_id) {
            if ($idx > $first_tier_idx) $tiers_after++;
            if ($idx < $first_tier_idx) $tiers_before++;
          }
          
          $remaining_weight = 1.0 - $base_weight;
          $weight_per_later_tier = $tiers_after > 0 ? $remaining_weight / $tiers_after : 0;
          
          foreach ($item_tiers as $idx => $tier_id) {
            if ($idx == $first_tier_idx) {
              // This is the first tier - gets the bulk of matches
              $tier_distribution[$tier_id] = $base_weight;
            } else if ($idx < $first_tier_idx) {
              // Earlier tier than first - should be rare, give minimal weight
              $tier_distribution[$tier_id] = 0.001;
            } else {
              // Later tier - split remaining weight evenly
              $tier_distribution[$tier_id] = $weight_per_later_tier;
            }
          }
          
          // Normalize to ensure sum is 1.0
          $total_weight = array_sum($tier_distribution);
          if ($total_weight > 0) {
            foreach ($tier_distribution as $tier_id => $weight) {
              $tier_distribution[$tier_id] = $weight / $total_weight;
            }
          }
          
          // Estimate matches per tier
          $tier_matches = [];
          $remaining_matches = $total_matches;
          
          foreach ($item_tiers as $idx => $tier_id) {
            if ($idx == count($item_tiers) - 1) {
              // Last tier gets remaining
              $tier_matches[$tier_id] = $remaining_matches;
            } else {
              $estimated = (int)round($total_matches * $tier_distribution[$tier_id]);
              $tier_matches[$tier_id] = min($estimated, $remaining_matches);
              $remaining_matches -= $tier_matches[$tier_id];
            }
          }
          
          // Generate stats for each tier
          foreach ($item_tiers as $cat_id) {
            $matches = $tier_matches[$cat_id];
            if ($matches <= 0) continue;
            
            // Estimate winrate - use average winrate with small gradient-based adjustment
            $tier_idx = array_search($cat_id, $item_tiers);
            $game_tier = $cat_to_tier[$cat_id]; // Actual tier number (1-5)
            list($min_time, $max_time) = $tier_timings[$game_tier];
            $tier_mid_time = ($min_time + min($max_time, 3600)) / 2;
            
            // For tiers after the first, slightly adjust WR based on gradient and timing
            $wr = $avg_wr;
            if ($tier_idx > $first_tier_idx && abs($grad) > 0.0001) {
              // Later tiers: item is picked up later, adjust slightly based on gradient
              $time_diff = $tier_mid_time - $median_timing;
              $time_diff_minutes = $time_diff / 60;
              // Small adjustment: -0.01 per 10 minutes for negative gradient
              $wr_adjustment = $grad * $time_diff_minutes * 0.1;
              $wr = $avg_wr + $wr_adjustment;
            }
            
            // Keep winrate reasonable (between 20% and 80%)
            $wr = max(0.20, min(0.80, $wr));
            
            $wins = (int)round($matches * $wr);
            
            if (!isset($enchantments[$hero_id])) {
              $enchantments[$hero_id] = [];
            }
            if (!isset($enchantments[$hero_id][$cat_id])) {
              $enchantments[$hero_id][$cat_id] = [];
            }
            
            // Store basic data first, matches_wo and wr_wo will be calculated later
            $enchantments[$hero_id][$cat_id][$item_id] = [
              'matches' => $matches,
              'wins' => $wins,
              'wr' => round($wr, 4),
              'matches_wo' => 0, // Will be calculated in second pass
              'wr_wo' => 0, // Will be calculated in second pass
            ];
          }
          
          // Remove from stats
          unset($report['items']['stats'][$hero_id][$item_id]);
        }
      }
      
      // Second pass: calculate matches_wo and wr_wo for each tier
      // For individual tiers, prate is relative to that tier's total pickups
      foreach ($enchantments as $hero_id => $categories) {
        foreach ($categories as $cat_id => $items) {
          // Calculate total pickups (matches) for this category/hero
          $cat_total_pickups = 0;
          $cat_total_wins = 0;
          
          foreach ($items as $item_id => $data) {
            $cat_total_pickups += $data['matches'];
            $cat_total_wins += $data['wins'];
          }
          
          // Now calculate matches_wo and wr_wo for each item
          foreach ($items as $item_id => $data) {
            $matches_wo = $cat_total_pickups - $data['matches'];
            $wr_wo = 0;
            
            if ($matches_wo > 0) {
              $wr_wo = ($cat_total_wins - $data['wins']) / $matches_wo;
            }
            
            $enchantments[$hero_id][$cat_id][$item_id]['matches_wo'] = $matches_wo;
            $enchantments[$hero_id][$cat_id][$item_id]['wr_wo'] = round($wr_wo, 4);
          }
        }
      }
      
      // Generate "0" category (total across all tiers) for each hero
      // For category 0, use hero's total pickups (sum of all tier pickups)
      foreach ($enchantments as $hero_id => $categories) {
        $enchantments[$hero_id][0] = [];
        
        // Aggregate across all tiers for each item
        $item_totals = [];
        foreach ($categories as $cat_id => $items) {
          foreach ($items as $item_id => $data) {
            if (!isset($item_totals[$item_id])) {
              $item_totals[$item_id] = [
                'matches' => 0,
                'wins' => 0,
              ];
            }
            $item_totals[$item_id]['matches'] += $data['matches'];
            $item_totals[$item_id]['wins'] += $data['wins'];
          }
        }
        
        // Calculate total pickups across all enchantment items for this hero
        $hero_total_pickups = 0;
        $hero_total_wins = 0;
        foreach ($item_totals as $iid => $t) {
          $hero_total_pickups += $t['matches'];
          $hero_total_wins += $t['wins'];
        }
        
        // Calculate stats for category 0
        foreach ($item_totals as $item_id => $totals) {
          $matches = $totals['matches'];
          $wins = $totals['wins'];
          $wr = $matches > 0 ? $wins / $matches : 0;
          
          // For category 0, matches_wo is relative to hero's total enchantment pickups
          $matches_wo = $hero_total_pickups - $matches;
          $wr_wo = 0;
          
          if ($matches_wo > 0) {
            $wr_wo = ($hero_total_wins - $wins) / $matches_wo;
          }
          
          $enchantments[$hero_id][0][$item_id] = [
            'matches' => $matches,
            'wins' => $wins,
            'wr' => round($wr, 4),
            'matches_wo' => $matches_wo,
            'wr_wo' => round($wr_wo, 4),
          ];
        }
        
        $zero_cat = $enchantments[$hero_id][0];
        unset($enchantments[$hero_id][0]);
        $enchantments[$hero_id] = [0 => $zero_cat] + $enchantments[$hero_id];
      }
      
      $report['items']['enchantments'] = wrap_data($enchantments, true, true, true);
      
      if (!isset($report['settings'])) {
        $report['settings'] = [];
      }
      $report['settings']['enchantments_recalc'] = true;
    }
  }
}