<?php

// Neutral item tier resolver.
//
// One trinket/enhancement may belong to several tiers, so the tier it
// counts for is deduced from the time it was crafted (neutral_item_history
// event time), not from the item itself.
//
// Version 6100+ reduced the number of neutral item tiers from 5 to 4:
//   Tier 1 - 0:00, Tier 2 - 15:00, Tier 3 - 30:00, Tier 4 - 50:00
// Earlier patches (5 tiers):
//   Tier 1 - 0:00, Tier 2 - 15:00, Tier 3 - 25:00, Tier 4 - 35:00, Tier 5 - 60:00

const LRG_NEUTRAL_4_TIERS_VERSION = 6100;

function neutral_item_tier($time, $version) {
  $boundaries = $version >= LRG_NEUTRAL_4_TIERS_VERSION
    ? [ 0, 900, 1800, 3000 ]        // 4 tiers: 0:00 / 15:00 / 30:00 / 50:00
    : [ 0, 900, 1500, 2100, 3600 ]; // 5 tiers: 0:00 / 15:00 / 25:00 / 35:00 / 60:00

  $tier = 1;
  foreach ($boundaries as $i => $start) {
    if ($time >= $start) $tier = $i + 1;
    else break;
  }

  return $tier;
}
