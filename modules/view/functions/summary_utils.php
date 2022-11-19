<?php 

function get_summary_key_primary_group($key) {
  return explode(',', SUMMARY_GROUPS[$key] ?? 'other')[0];
}

function summary_prepare_value($key, $value) {
  if (strpos($key, "duration") !== FALSE || strpos($key, "_len") !== FALSE)
    return convert_time($value);

  if (strpos($key, "winrate") !== FALSE || strpos($key, "_wr") !== FALSE || strpos($key, "ratio") !== FALSE || strpos($key, "diversity") !== FALSE)
    return number_format($value*100, 2)."%";

  if (strpos($key, "matches") !== FALSE) return number_format($value, 0);
  
  if(!is_numeric($value)) return $value;

  if ($value > 10)
    return number_format($value,1);
  if ($value > 1)
    return number_format($value,2);
  
  return number_format($value,3);
}