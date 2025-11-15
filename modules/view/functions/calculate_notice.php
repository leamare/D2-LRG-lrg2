<?php

const PATCH_PRE_ALPHA = 100;
const PATCH_MAIN_S1 = 800;
const PATCH_MAIN_S2 = 1500;
const PATCH_700 = 1900;

function calculate_notice() {
  global $report;

  if (empty($report) || empty($report['versions'])) return "";
 
  $patches = array_keys($report['versions']);
  $patches = array_map('intval', $patches);

  $minpatch = min($patches);
  $maxpatch = max($patches);

  $notice = "";

  if ($report['settings']['incomplete_source'] ?? false) {
    $notice = "notice_missing_data";
  } else {
    if ($minpatch < PATCH_PRE_ALPHA) {
      $notice = "notice_wc3";
    } else if ($minpatch < PATCH_MAIN_S1) {
      if ($maxpatch > PATCH_MAIN_S2) {
        $notice = "notice_mixed_to_source2";
      } else if ($maxpatch >= PATCH_MAIN_S1) {
        $notice = "notice_mixed_old_source1";
      } else {
        $notice = "notice_old_source1";
      }
    } else if ($minpatch < PATCH_MAIN_S2) {
      if ($maxpatch >= PATCH_MAIN_S2) {
        $notice = "notice_mixed_source1";
      } else {
        $notice = "notice_source1";
      }
    } else {
      return "";
    }
  }

  return "<div class=\"content-text alert info\">".locale_string($notice)."</div>";
}

function sources_notice() {
  global $report;

  if (empty($report) || empty($report['settings'])) return "";

  $sources = array_map(function($link, $title) {
    return "<a href=\"$link\" target=\"_blank\" rel=\"noopener\">$title</a>";
  }, $report['settings']['sources'], array_keys($report['settings']['sources']));

  return "<div class=\"content-text\">".locale_string("sources_used").": ".implode(", ", $sources)."</div>";
}