<?php 

function get_facet_name($hid, $variant) {
  global $report, $meta;

  if (!$variant) return "_no_facet_";

  if (!empty($report['meta']['variants'])) {
    if (empty($report['meta']['variants'][$hid]) || !is_numeric($variant)) {
      return "undefined";
    }
    $facet = array_keys($report['meta']['variants'][$hid])[ $variant-1 ] ?? "undefined";
  } else {
    $facet = $meta['facets']['heroes'][$hid][$variant-1]['name'] ?? "undefined";
  }

  return $facet;
}

function get_facet_icon($hid, $variant) {
  global $report, $meta;

  if (!empty($report['meta']['variants'])) {
    if (empty($report['meta']['variants'][$hid]) || !is_numeric($variant)) {
      return "unique";
    }
    $facet = array_keys($report['meta']['variants'][$hid])[ $variant-1 ] ?? "undefined";
    $icon = ($report['meta']['variants'][$hid][$facet] ?? [])[0] ?? "unique";
  } else {
    $icon = $meta['facets']['heroes'][$hid][$variant-1]['icon'] ?? "unique";
  }

  return $icon;
}

function get_hero_variants_list($hid) {
  global $report, $meta;

  if (!empty($report['meta']['variants'])) {
    if (empty($report['meta']['variants'][$hid])) {
      return [];
    }
    return array_keys($report['meta']['variants'][$hid]);
  } else {
    return array_map(function($el) {
      return $el['name'];
    }, $meta['facets']['heroes'][$hid]);
  }
}

function get_facet_color($hid, $variant) {
  global $report, $meta;

  if (!$variant) return "Black";

  if (!empty($report['meta']['variants'])) {
    if (empty($report['meta']['variants'][$hid]) || !is_numeric($variant)) {
      return "Black";
    }
    $facet = array_keys($report['meta']['variants'][$hid])[ $variant-1 ] ?? "undefined";
    $color_id = ($report['meta']['variants'][$hid][$facet] ?? [])[1] ?? 0;
    $color = $report['meta']['variants_colors'][ $color_id ] ?? "Black";
  } else {
    $color = $meta['facets']['heroes'][$hid][$variant-1]['color'] ?? "Black";
  }

  return $color;
}

function facet_icon_link($hid, $variant) {
  global $varianticon_logo_provider;

  $icon = get_facet_icon($hid, $variant);
  // $name = get_facet_name($hid, $variant);

  return str_replace("%FACET%", $icon, $varianticon_logo_provider);
}

function facet_full_element($hid, $variant, $nopopup = false) {
  global $locale;
  include_locale($locale, "facets");

  $icon_link = facet_icon_link($hid, $variant);
  $color = get_facet_color($hid, $variant);
  $name = get_facet_name($hid, $variant);

  return "<div class=\"hero-facet-element facet-$color\" data-tag=\"$name\"".
      ($nopopup || !$variant ? "" : " onclick=\"showModal('".
        htmlspecialchars(addcslashes(locale_string("#facet-desc::$name"), "'"))."', '".
        htmlspecialchars(addcslashes(facet_full_element($hid, $variant, true), "'"))."');\"").
    ">".
    "<span class=\"facet-icon\"><img src=\"$icon_link\" alt=\"$name\" /></span>".
    "<span class=\"facet-name\">".locale_string("#facet::".$name)."</span>".
  "</div>";
}

function facet_micro_element($hid, $variant, $modal = true) {
  global $locale;
  include_locale($locale, "facets");

  $icon_link = facet_icon_link($hid, $variant);
  $color = get_facet_color($hid, $variant);
  $name = get_facet_name($hid, $variant);

  return "<div class=\"hero-facet-element facet-micro facet-$color\" data-tag=\"$name\"".
      ($modal && $variant ?
      " onclick=\"showModal('".
        htmlspecialchars(addcslashes(locale_string("#facet-desc::$name"), "'"))."', '".
        htmlspecialchars(addcslashes(facet_full_element($hid, $variant, true), "'"))."');\"" :
      "").
    ">".
    "<span class=\"facet-icon\"><img src=\"$icon_link\" alt=\"$name\" /></span>".
    "<span class=\"facet-name\">".locale_string("#facet::".$name)."</span>".
  "</div>";
}