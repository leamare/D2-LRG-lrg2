<?php

include __DIR__ . "/../libs/keyvalues-php/src/kv_decode.php";
include __DIR__ . "/../modules/commons/metadata.php";

use function leamare\SimpleKV\kv_decode;

$meta = new lrg_metadata;

$localesMap = include __DIR__ . "/../locales/map.php";
$vpkRoot = $argv[1] ?? "https://raw.githubusercontent.com/dotabuff/d2vpkr/master/dota/resource/localization/";

// const vpkdata_npc_abilities_root = "https://raw.githubusercontent.com/dotabuff/d2vpkr/master/dota/scripts/npc/";
const vpkdata_npc_abilities_root = "/mnt/c/code/dota-npc/";
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

  foreach ($NPCAbilities['DOTAAbilities'] as $stag => $spell) {
    if (!isset($talentValues[$stag])) $talentValues[$stag] = [];

    if (\is_array($spell)) {
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
        $spell['AbilityValues'] ?? [],
        $spell['AbilitySpecial'] ?? [],
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

foreach ($localesMap as $lt => $loc) {
  if (!isset($loc['vpk'])) continue;

  echo " - ".$loc['vpk'].": ";

  $strings = kv_decode(file_get_contents($vpkRoot."abilities_".$loc['vpk'].".txt"));

  echo "spells ";
  if (!isset($addlocales['spells_'.$lt])) {
    $addlocales['spells_'.$lt] = [];
  }
  foreach ($spellsList as $spell => $t) {
    $tag = 
      $strings['lang']['Tokens']['DOTA_Tooltip_ability_'.$spell] ?? 
      $strings['lang']['Tokens']['DOTA_Tooltip_Ability_'.$spell] ?? null;

    if (empty($tag)) {
      $candidates = array_filter(array_keys($strings['lang']['Tokens']), function($el) use (&$spell) {
        return strpos($el, 'DOTA_Tooltip_Ability_'.$spell) !== false || strpos($el, 'DOTA_Tooltip_ability_'.$spell) !== false;
      });

      if (!empty($candidates)) {
        $tag = implode(" / ", array_map(function($k) use (&$strings) {
          return $strings['lang']['Tokens'][$k];
        }, $candidates));
      }
    }

    if (isset($talentValues[$spell]) && \preg_match("/\{s\:(.*?)\}/", $tag)) {
      $tv = $talentValues[$spell];
      if (preg_match_all("/\{s\:(.*?)\}/", $tag) > 1 || count($talentValues[$spell]) > 1) {
        foreach ($talentValues[$spell] as $k => $v) {
          if (is_array($v)) {
            $v = $v['value'] ?? reset($v);
          }
          $tag = \preg_replace("/\{s\:(bonus_)?".$k."(.*?)\}/", $v, $tag);  
        }
      }
      if (preg_match_all("/\{s\:(.*?)\}/", $tag) > 0) {
        if (isset($talentValues[$spell]['value']) && is_array($talentValues[$spell]['value'])) {
          $talentValues[$spell]['value'] = implode(",", $talentValues[$spell]['value']);
        }

        $tag = \preg_replace("/\{s\:(.*?)\}/", $talentValues[$spell]['value'] ?? reset($talentValues[$spell]), $tag);
      }

      $tag = \str_replace(
        ['++', '--', '–-', '%%', '-+', '+-'],
        ['+', '-', '-', '%', '-', '-'],
        $tag
      );
      $tag = \preg_replace("/x(\d)x/", "$1x", $tag);  
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

    if (isset($talentValues[$facet]) && (\preg_match("/\{s\:(.*?)\}/", $desc) || \preg_match("/%(.*?)%/", $desc))) {
      if ((preg_match_all("/\{s\:(.*?)\}/", $desc) > 1 || preg_match_all("/%(.*?)%/", $desc) > 1) || count($talentValues[$facet]) > 1) {
        foreach ($talentValues[$facet] as $k => $v) {
          if (is_array($v)) {
            $v = $v['value'] ?? reset($v);
          }
          if (strpos($v, ' ') !== false) {
            $v = implode('/', array_unique(explode(' ', $v)));
          }
          $desc = \preg_replace("/\{s\:(bonus_)?".$k."(.*?)\}/", $v, $desc);
          $desc = \preg_replace("/%(bonus_)?".$k."%/", $v, $desc);
        }
      } else {
        if (isset($talentValues[$facet]['value']) && is_array($talentValues[$facet]['value'])) {
          $talentValues[$facet]['value'] = implode(",", $talentValues[$facet]['value']);
        }

        $desc = \preg_replace("/\{s\:(.*?)\}/", $talentValues[$facet]['value'] ?? reset($talentValues[$facet]), $desc);
        $desc = \preg_replace("/%(.*?)%/", ($talentValues[$facet]['value'] ?? reset($talentValues[$facet])), $desc);
      }

      $desc = \str_replace(
        ['++', '--', '–-', '%%', '-+', '+-'],
        ['+', '-', '-', '%', '-', '-'],
        $desc
      );
      $desc = \preg_replace("/x(\d)x/", "$1x", $desc);  
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