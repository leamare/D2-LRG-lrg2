#!/bin/php
<?php
require_once("libs/simple-opendota-php/simple_opendota.php");

// Backported from Simple OpenDota for PHP
// TODO: replace with Simple STRATZ for PHP
function post($url, $data = []) {
  $curl = curl_init($url);

  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($curl);

  if ( curl_errno($curl) )
      $response = false;

  if ( !curl_errno($curl) )
      echo("OK\n");
  else
      echo("\ncURL error: ".curl_error($curl)."\n");

  curl_close($curl);

  return $response;
}

$options = getopt("f:");
if(isset($options['f'])) {
  $filename = $options['f'];
} else die();

$settings = json_decode(file_get_contents("rg_settings.json"), true);

$input_cont = file_get_contents($filename) or die("[F] Error while opening file.\n");
$input_cont = str_replace("\r\n", "\n", $input_cont);

if(!empty($settings['odapikey']))
  $opendota = new \SimpleOpenDotaPHP\odota_api(true, "", 1500, $settings['odapikey']);
else
  $opendota = new \SimpleOpenDotaPHP\odota_api(true, "", 1500);

unset($settings);

$matches    = explode("\n", trim($input_cont));

$matches = array_unique($matches);

foreach ($matches as $match) {
    if ($match[0] == "#") continue;

    $opendota->request_match($match);
    // post("https://api.stratz.com/api/v1/match/$match/retry");
}

echo "[S] All matches were requested.\n";

?>
