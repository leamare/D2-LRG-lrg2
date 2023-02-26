<?php 

if (isset($__lrg_onrequest)) {
  if (function_exists("geoip_country_name_by_name")) {
    $country = geoip_country_name_by_name(
      $_SERVER['HTTP_CLIENT_IP'] ?? 
      $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
      $_SERVER['REMOTE_ADDR']
    );
  } else {
    $country = null;
  }

  if (!empty($leaguetag)) {
    $path = "league=".$leaguetag."::".($mod ?? "");
  } else {
    $path = http_build_query($_GET);
  }

  if (empty($path)) $path = "index";

  if (is_array($linkvars)) $linkvars = http_build_query($linkvars);

  $__lrg_onrequest([
    'type' => 'request',
    'project' => $projectName ?? "LRG2",
    'path' => $path,
    'params' => $linkvars ?? "",
    'title' => $uni_title ?? $rep_sm_title ?? $mod ?? null,
    'lang' => $origLocale ?? null,
    'langused' => $locale ?? null,
    'calltype' => $isApi ? 0 : 1,
    'agent' => $_SERVER['HTTP_USER_AGENT'] ?? "Unknown Agent",
    'ip' => $_SERVER['HTTP_CLIENT_IP'] ?? 
      $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
      $_SERVER['REMOTE_ADDR'],
    'country' => $country,  
  ]);
}