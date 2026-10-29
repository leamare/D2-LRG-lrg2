<?php

include __DIR__ . "/../libs/keyvalues-php/src/kv_decode.php";
include __DIR__ . "/../modules/commons/metadata.php";
include __DIR__ . "/../modules/commons/special_bonus.php";

use function leamare\SimpleKV\kv_decode;

$meta = new lrg_metadata;

$localesMap = include __DIR__ . "/../locales/map.php";
$vpkRoot = $argv[1] ?? "https://raw.githubusercontent.com/dotabuff/d2vpkr/master/dota/resource/localization/";

// const vpkdata_npc_abilities_root = "https://raw.githubusercontent.com/dotabuff/d2vpkr/master/dota/scripts/npc/";
const vpkdata_npc_abilities_root = "/home/lea/code/dota-npc/";
const vpkdata_npc_abilities_main = "npc_abilities.txt";
const vpkdata_npc_abilities_heroes = "heroes/npc_dota_hero_%TAG%.txt";

$addlocales = [];

$files = scandir("locales_additional");
foreach ($files as $locf) {
  if ($locf[0] == '.') continue;
  $tag = str_replace('.json', '', $locf);
  $addlocales[$tag] = json_decode(file_get_contents("locales_additional/$locf"), true);
}

$talentValues = [];
$abilityScripts = [];

$spellsources = [
  vpkdata_npc_abilities_main,
];
foreach ($meta['heroes'] as $hid => $hero) {
  $spellsources[] = str_replace("%TAG%", $hero['tag'], vpkdata_npc_abilities_heroes);
}


$spellsList = [];
$spellTalentsList = [];
$facetsList = [];

foreach ($meta['heroes_spells'] as $hid => $hero) {
  foreach ($hero['main'] as $stag) {
    if (is_array($stag)) {
      foreach ($stag as $st) {
        $spellsList[$st] = false;
      }
    } else {
      $spellsList[$stag] = false;
    }
  }

  if (is_array($hero['ultimate'])) {
    foreach ($hero['ultimate'] as $st) {
      $spellsList[$st] = false;
    }
  } else {
    $spellsList[$hero['ultimate']] = false;
  }

  if (is_array($hero['innate'])) {
    foreach ($hero['innate'] as $st) {
      $spellsList[$st] = false;
    }
  } else {
    $spellsList[$hero['innate']] = false;
  }

  foreach ($hero['talents'] as $tlvl) {
    foreach ($tlvl as $stag) {
      $spellTalentsList[$stag] = false;
      $spellsList[$stag] = false;
    }
  }
}
foreach ($meta['facets']['heroes'] as $hid => $facets) {
  foreach($facets as $facet) {
    $facetsList[$facet['name']] = $hid;
    if (!empty($facet['abilities'])) {
      foreach ($facet['abilities'] as $spell) {
        $spellTalentsList[ $spell['name'] ] = false;
      }
    }
  }
}


foreach ($spellsources as $src) {
  echo "vpkdata $src ";
  // sleep(2);
  
  $NPCAbilities = kv_decode(file_get_contents(vpkdata_npc_abilities_root . $src));

  echo "OK\n";

  foreach (extract_ability_scripts_from_vpk($NPCAbilities ?? []) as $stag => $spell) {
    if (!isset($talentValues[$stag])) $talentValues[$stag] = [];

    if (\is_array($spell)) {
      $abilityScripts[$stag] = $spell;

      if (isset($spellTalentsList[$stag])) {
        if (isset($spell['AbilitySpecial'])) {
          foreach ($spell['AbilitySpecial'] as $i => $vals) {
            foreach ($vals as $k => $v) {
              if ($k == "var_type") continue;
              if (empty($talentValues[$stag]) || $k == "value") {

                if (is_array($v)) {
                  $v = $v['value'] ?? reset($v);
                }
                $talentValues[$stag]['value'] = $v;
              }
              $talentValues[$stag][$k] = $v;
              if ($k == "value") {
                // break 2;
              }
            }
          }
        }

        if (isset($spell['AbilityValues'])) {
          foreach ($spell['AbilityValues'] as $k => $v) {
            // if (empty($talentValues[$stag]) || $k == "value") {
              if (is_array($v)) {
                foreach($v as $kk => $vv) {
                  if (strpos($kk, "special_bonus_facet_") !== false) {
                    $kk = str_replace("special_bonus_facet_", "", $kk);
                    if (!isset($talentValues[$kk])) $talentValues[$kk] = [];
                    $talentValues[$kk][$k] = $vv;
                    continue;
                  }
                  if (isset($spellTalentsList[$kk])) {
                    if (empty($talentValues[$kk])) $talentValues[$kk] = [];
                    $talentValues[$kk][$stag] = $vv;
                    $talentValues[$kk]['value'] = $talentValues[$kk]['value'] ?? $vv;
                  }
                }

                $v = $v['value'] ?? reset($v);
              }
              $talentValues[$stag]['value'] = $v;
            // }
            $talentValues[$stag][$k] = $v;

            if ($k == "value") {
              break;
            }
          }
        }

        if (isset($talentValues[$stag])) {
          continue;
        }
      }

      $spells = \array_merge(
        (array)($spell['AbilityValues'] ?? []),
        (array)($spell['AbilitySpecial'] ?? []),
      );

      foreach($spells as $k => $v) {
        if (is_array($v)) {
          foreach ($v as $kk => $vv) {
            if (is_string($vv) && strpos($vv, '=') !== false) {
              $vv = str_replace('=', '', $vv);
            }
            if (strpos($kk, "special_bonus_facet_") !== false) {
              $kk = str_replace("special_bonus_facet_", "", $kk);
              
              if (!isset($talentValues[$kk])) $talentValues[$kk] = [];
              $talentValues[$kk][$k] = $vv;
              continue;
            }
            if (isset($spellsList[$kk]) || isset($spellTalentsList[$kk])) {
              if (!isset($talentValues[$kk])) $talentValues[$kk] = [];
              $talentValues[$kk][$k] = $vv;
              $talentValues[$kk]['value'] = $vv;
            } else {
              $talentValues[$k][$kk] = $vv;
            }
          }
        } else {
          if (isset($spellsList[$k])) {
            if (!isset($talentValues[$k])) $talentValues[$k] = [];
            $talentValues[$k]['value'] = $v;
          }
        }
      }
    }
  }
}

foreach ($meta['facets']['heroes'] as $hid => $facets) {
  foreach($facets as $facet) {
    if (!empty($facet['abilities'])) {
      foreach ($facet['abilities'] as $spell) {
        foreach ($talentValues[$spell['name']] ?? [] as $k => $v) {
          if (isset($talentValues[$facet['name']][$k])) continue;

          $talentValues[$facet['name']][$k] = $v;
        }
      }
    }
  }
}

$currentTalents = [];
$preferredAbilities = [];
foreach ($meta['heroes_spells'] as $hero) {
  foreach (['main', 'ultimate', 'innate'] as $slot) {
    $entries = $hero[$slot] ?? [];
    if (!is_array($entries)) $entries = [$entries];
    foreach ($entries as $stag) {
      if (is_array($stag)) {
        foreach ($stag as $st) {
          if (is_string($st) && strpos($st, 'special_bonus') !== 0) {
            $preferredAbilities[$st] = true;
          }
        }
      } elseif (is_string($stag) && strpos($stag, 'special_bonus') !== 0) {
        $preferredAbilities[$stag] = true;
      }
    }
  }
  foreach ($hero['talents'] ?? [] as $tlvl) {
    foreach ((array)$tlvl as $stag) {
      if (is_string($stag) && strpos($stag, 'special_bonus') === 0) {
        $currentTalents[$stag] = true;
      }
    }
  }
}
foreach ($spellTalentsList as $talent => $_) {
  if (strpos((string)$talent, 'special_bonus') === 0) {
    $currentTalents[$talent] = true;
  }
}

$talentLookup = build_special_bonus_lookup($abilityScripts, [
  'currentTalents' => $currentTalents,
  'preferredAbilities' => $preferredAbilities,
]);

foreach ($localesMap as $lt => $loc) {
  if (!isset($loc['vpk'])) continue;

  echo " - ".$loc['vpk'].": ";

  $strings = kv_decode(file_get_contents($vpkRoot."abilities_".$loc['vpk'].".txt"));
  $nameStrings = ability_name_strings($strings['lang']['Tokens'] ?? []);

  echo "spells ";
  if (!isset($addlocales['spells_'.$lt])) {
    $addlocales['spells_'.$lt] = [];
  }

  $abilityNames = [];
  foreach ($spellsList as $spell => $t) {
    $tag = $nameStrings['dota_tooltip_ability_' . strtolower($spell)] ?? null;

    if (empty($tag)) {
      $candidates = array_filter(array_keys($strings['lang']['Tokens'] ?? []), function($el) use ($spell) {
        $elLower = strtolower($el);
        $needle = 'dota_tooltip_ability_' . strtolower($spell);
        return strpos($elLower, $needle) !== false
          && substr($elLower, -strlen('_description')) !== '_description';
      });

      if (!empty($candidates)) {
        $tag = implode(" / ", array_map(function($k) use ($strings) {
          return $strings['lang']['Tokens'][$k];
        }, $candidates));
      }
    }

    if (!empty($tag)) {
      $abilityNames[$spell] = ['dname' => $tag];
    }
  }

  resolve_special_bonus_placeholders($abilityNames, $talentLookup);

  foreach ($spellsList as $spell => $t) {
    $tag = $abilityNames[$spell]['dname'] ?? null;

    // Back-compat: if odota-style lookup left placeholders, use the legacy filler.
    if (!empty($tag) && isset($talentValues[$spell]) && preg_match('/\{s:/i', $tag)) {
      $tag = resolve_special_bonus_placeholders_legacy($tag, $talentValues[$spell]);
    }

    if (empty($tag) && $lt == 'en') {
      $tag = \str_replace('special_bonus_', '', $spell);
      foreach ($meta['heroes'] as $hero) {
        if (strpos($tag, $hero['tag']) !== false) {
          $tag = \str_replace([ $hero['tag'], 'unique' ], '', $tag);
          break;
        }
      }
      $tag = \str_replace([ '___', '__', '_' ], ' ', $tag);
      $tag = trim(ucwords($tag));
    }

    if (!empty($tag) && $t !== $tag && !empty($spell)) {
      if ($lt == 'en') {
        $spellsList[ $spell ] = $tag;
      }
      $addlocales['spells_'.$lt]['#spell::'.$spell] = $tag;
    }
  }


  $addlocales['spells_'.$lt]['#spell::special_bonus_attributes'] = $strings['lang']['Tokens']['DOTA_Tooltip_ability_attribute_bonus'] ?? "Attribute Bonus";

  echo "facets ";
  if (!isset($addlocales['facets_'.$lt])) {
    $addlocales['facets_'.$lt] = [];
  }
  foreach ($facetsList as $facet => $hero) {
    $tag = $strings['lang']['Tokens']['DOTA_Tooltip_Facet_'.$facet] ?? 
      $strings['lang']['Tokens']['DOTA_Tooltip_facet_'.$facet] ?? 
      $strings['lang']['Tokens']['DOTA_Tooltip_ability_'.$facet] ?? 
      $strings['lang']['Tokens']['DOTA_Tooltip_Ability_'.$facet] ?? 
      null;

    $desc = $strings['lang']['Tokens']['DOTA_Tooltip_Facet_'.$facet.'_Description'] ?? 
      $strings['lang']['Tokens']['DOTA_Tooltip_facet_'.$facet.'_Description'] ?? 
      $strings['lang']['Tokens']['DOTA_Tooltip_Facet_'.$facet.'_description'] ?? 
      $strings['lang']['Tokens']['DOTA_Tooltip_facet_'.$facet.'_description'] ?? 
      $strings['lang']['Tokens']['DOTA_Tooltip_ability_'.$facet.'_Description'] ?? 
      $strings['lang']['Tokens']['DOTA_Tooltip_Ability_'.$facet.'_Description'] ?? 
      $strings['lang']['Tokens']['DOTA_Tooltip_ability_'.$facet.'_description'] ?? 
      $strings['lang']['Tokens']['DOTA_Tooltip_Ability_'.$facet.'_description'] ?? 
    null;

    if (!$tag) {
      $spelltag = null;
      foreach ($meta['facets']['heroes'][$hero] as $ft => $data) {
        if ($data['name'] == $facet) {
          if (!empty($data['abilities'])) {
            foreach ($data['abilities'] as $spell) {
              $spelltag = $spell['name'];
              $tag = $strings['lang']['Tokens']['DOTA_Tooltip_ability_'.$spelltag] ?? 
                $strings['lang']['Tokens']['DOTA_Tooltip_Ability_'.$spelltag] ?? 
              null;
              $desc = $strings['lang']['Tokens']['DOTA_Tooltip_ability_'.$spelltag.'_Description'] ?? 
                $strings['lang']['Tokens']['DOTA_Tooltip_Ability_'.$spelltag.'_Description'] ?? 
              null;
              if ($tag) break 2;
            }
          }
        }
      }
    }

    if ($facet == "drow_ranger_high_ground") {
      $tv = $talentValues[$facet];
    }

    if (!empty($desc) && isset($talentValues[$facet])) {
      $desc = resolve_special_bonus_placeholders_legacy($desc, $talentValues[$facet]);
    }
    // if (strpos($desc, "<font") !== false) {
    //   $desc = \preg_replace("/<font(.*)>(.*)<\/font>/", "\\2", $desc);  
    // }

    if (!$tag) {
      echo "\n\t WARNING: $facet no translation";
      continue;
    }
    $addlocales['facets_'.$lt]['#facet::'.$facet] = $tag;
    $addlocales['facets_'.$lt]['#facet-desc::'.$facet] = $desc;
  }
  
  echo "\n";
}

foreach ($addlocales as $ftag => $data) {
  if (strpos($ftag, "_en") !== false) {
    continue;
  }
  $gtag = substr($ftag, 0, strrpos($ftag, '_'));
  foreach ($data as $tag => $string) {
    if (!isset($addlocales[$gtag.'_'.$localesMap['def']['alias']])) {
      continue;
    }
    if ($string == $addlocales[$gtag.'_'.$localesMap['def']['alias']][$tag]) {
      unset($addlocales[$ftag][$tag]);
    }
  }
}

foreach ($addlocales as $ftag => $data) {
  if (empty($data)) continue;

  file_put_contents("locales_additional/$ftag.json", json_encode($data, JSON_UNESCAPED_UNICODE));
}