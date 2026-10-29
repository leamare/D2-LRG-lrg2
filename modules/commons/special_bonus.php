<?php

/**
 * Lowercase token map for case-insensitive DOTA_Tooltip lookups.
 *
 * @param array<string, string> $tokens
 * @return array<string, string>
 */
function ability_name_strings(array $tokens): array {
  $out = [];
  foreach ($tokens as $key => $value) {
    $out[strtolower((string)$key)] = $value;
  }
  return $out;
}

/**
 * Collect ability scripts from a parsed npc VPK file.
 * Supports legacy DOTAAbilities roots and modern d2vpkr hero files
 * (DOTAHeroes.*.AbilityDefinitions).
 *
 * @param array<string, mixed> $vpk
 * @return array<string, array>
 */
function extract_ability_scripts_from_vpk(array $vpk): array {
  $out = [];
  foreach (($vpk['DOTAAbilities'] ?? []) as $stag => $spell) {
    if (is_string($stag) && is_array($spell)) {
      $out[$stag] = $spell;
    }
  }
  foreach (($vpk['DOTAHeroes'] ?? []) as $hero) {
    if (!is_array($hero)) continue;
    foreach (($hero['AbilityDefinitions'] ?? []) as $stag => $spell) {
      if (is_string($stag) && is_array($spell)) {
        $out[$stag] = $spell;
      }
    }
  }
  return $out;
}

/**
 * AbilitySpecial / AbilityValues attribute entries for one ability script.
 *
 * @return list<array{0: string, 1: mixed}>
 */
function special_bonus_script_entries(array $script): array {
  if (!empty($script['AbilitySpecial']) && is_array($script['AbilitySpecial'])) {
    $out = [];
    foreach ($script['AbilitySpecial'] as $entry) {
      if (!is_array($entry)) continue;
      foreach ($entry as $key => $value) {
        if ($key === 'var_type') continue;
        $out[] = [(string)$key, $value];
      }
    }
    return $out;
  }
  $out = [];
  $values = $script['AbilityValues'] ?? null;
  if (!is_array($values)) return $out;
  foreach ($values as $key => $value) {
    $out[] = [(string)$key, $value];
  }
  return $out;
}

/**
 * Collect talent modifier values before formatting names.
 *
 * @param array<string, mixed> $scripts ability tag => KV script
 * @param array{currentTalents?: array<string,bool>, preferredAbilities?: array<string,bool>} $opts
 * @return array<string, array<string, string>> talent => (value|bonus_<attr>) => display
 */
function build_special_bonus_lookup(array $scripts, array $opts = []): array {
  $currentTalents = $opts['currentTalents'] ?? [];
  $preferredAbilities = $opts['preferredAbilities'] ?? [];
  $lookup = [];
  $priorities = [];

  foreach ($scripts as $ability => $script) {
    if (!is_array($script)) continue;
    foreach (special_bonus_script_entries($script) as [$attribute, $value]) {
      if (!is_array($value)) continue;
      foreach ($value as $talent => $modifier) {
        $talent = (string)$talent;
        if (strpos($talent, 'special_bonus_') !== 0) continue;
        if (preg_match('/^special_bonus_(facet|scepter|shard)(?:_|$)/', $talent)) continue;

        $raw = is_string($modifier)
          ? $modifier
          : (is_array($modifier) ? ($modifier['special_bonus_scepter'] ?? null) : null);
        if (!is_string($raw)) continue;

        // Strip one leading sign/op char only (odota: /^[+=x-]/), so "+-10" → "-10".
        $display = implode(' ', array_map(
          static function (string $part): string {
            $part = preg_replace('/^[+=x-]/', '', $part) ?? $part;
            return preg_replace('/%$/', '', $part) ?? $part;
          },
          preg_split('/\s+/', trim($raw)) ?: []
        ));

        $priority = (!empty($currentTalents[$talent]) && !empty($preferredAbilities[$ability])) ? 1 : 0;
        if (!isset($lookup[$talent])) $lookup[$talent] = [];

        foreach (['value', 'bonus_' . strtolower($attribute)] as $key) {
          $group = $talent . '/' . $key;
          $previous = $priorities[$group] ?? -1;
          // value alias keeps first equal-priority candidate; named bonuses keep last.
          if ($priority > $previous || ($priority === $previous && $key !== 'value')) {
            $lookup[$talent][$key] = $display;
            $priorities[$group] = $priority;
          }
        }
      }
    }
  }

  foreach ($scripts as $name => $script) {
    if (!is_string($name) || strpos($name, 'special_bonus') !== 0 || !is_array($script)) continue;
    if (!isset($lookup[$name])) $lookup[$name] = [];
    foreach (special_bonus_script_entries($script) as [$attribute, $value]) {
      $raw = is_array($value) ? ($value['value'] ?? null) : $value;
      if (is_string($raw)) {
        $lookup[$name][strtolower($attribute)] = $raw;
      }
    }
  }

  return $lookup;
}

/**
 * Replace `{s:…}` / `{S:…}` placeholders in a talent display name.
 *
 * @param array<string, array{dname?: ?string}> $abilities
 * @param array<string, array<string, string>> $lookup
 */
function resolve_special_bonus_placeholders(array &$abilities, array $lookup, bool $formatModifiers = true): void {
  foreach ($abilities as $name => &$ability) {
    $values = $lookup[$name] ?? null;
    if (empty($ability['dname']) || empty($values)) continue;

    $template = (string)$ability['dname'];
    if (!preg_match_all('/\{s:([^}]+)\}/i', $template, $matches, PREG_OFFSET_CAPTURE)) {
      continue;
    }

    for ($i = count($matches[0]) - 1; $i >= 0; $i--) {
      [$placeholder, $offset] = $matches[0][$i];
      $key = strtolower($matches[1][$i][0]);
      $value = $values[$key] ?? $values[$matches[1][$i][0]] ?? null;
      if (!is_string($value)) continue;

      if ($formatModifiers) {
        $signedByTemplate = (bool)preg_match('/[+-]\s*$/', substr($template, 0, $offset));
        $value = implode(' / ', array_map(
          static function (string $part) use ($signedByTemplate): string {
            return $signedByTemplate ? (preg_replace('/^[+-]/', '', $part) ?? $part) : $part;
          },
          preg_split('/\s+/', $value) ?: []
        ));
      }

      $template = substr($template, 0, $offset) . $value . substr($template, $offset + strlen($placeholder));
    }

    $ability['dname'] = $template;
  }
  unset($ability);
}

/**
 * Legacy placeholder fill used before odota-style lookup (facets / fallback).
 * Kept so facet descriptions and any unresolved talent templates still resolve.
 *
 * @param array<string, mixed> $values
 */
function resolve_special_bonus_placeholders_legacy(string $template, array $values): string {
  if (!preg_match('/\{s:(.*?)\}/', $template) && !preg_match('/%(.*?)%/', $template)) {
    return $template;
  }

  $multi = preg_match_all('/\{s:(.*?)\}/', $template) > 1
    || preg_match_all('/%(.*?)%/', $template) > 1
    || count($values) > 1;

  if ($multi) {
    foreach ($values as $k => $v) {
      if (is_array($v)) {
        $v = $v['value'] ?? reset($v);
      }
      if (!is_string($v) && !is_numeric($v)) continue;
      $v = (string)$v;
      if (strpos($v, ' ') !== false) {
        $v = implode('/', array_unique(explode(' ', $v)));
      }
      $template = preg_replace('/\{s:(bonus_)?' . preg_quote((string)$k, '/') . '(.*?)\}/', $v, $template) ?? $template;
      $template = preg_replace('/%(bonus_)?' . preg_quote((string)$k, '/') . '%/', $v, $template) ?? $template;
    }
  }

  if (preg_match('/\{s:(.*?)\}/', $template) || preg_match('/%(.*?)%/', $template)) {
    $fallback = $values['value'] ?? reset($values);
    if (is_array($fallback)) {
      $fallback = implode(',', $fallback);
    }
    if (is_string($fallback) || is_numeric($fallback)) {
      $fallback = (string)$fallback;
      $template = preg_replace('/\{s:(.*?)\}/', $fallback, $template) ?? $template;
      $template = preg_replace('/%(.*?)%/', $fallback, $template) ?? $template;
    }
  }

  $template = str_replace(
    ['++', '--', '–-', '%%', '-+', '+-'],
    ['+', '-', '-', '%', '-', '-'],
    $template
  );
  return preg_replace('/x(\d)x/', '$1x', $template) ?? $template;
}
