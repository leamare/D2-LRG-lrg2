<?php

function lrg_opendota_http_request(string $method, string $full_url, array $data = []) {
  $ch = curl_init();

  if (strtoupper($method) === 'GET') {
    if (!empty($data)) {
      $full_url .= (strpos($full_url, '?') === false ? '?' : '&') . http_build_query($data);
    }
    curl_setopt($ch, CURLOPT_URL, $full_url);
  } else {
    curl_setopt($ch, CURLOPT_URL, $full_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
  }

  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  curl_setopt($ch, CURLOPT_USERAGENT, 'lrg-fetcher/1.0');

  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($resp === false) {
    if ($err !== '') {
      echo "[E] OpenDota HTTP: $err\n";
    }
    return false;
  }
  if (strpos($resp, '<!DOCTYPE HTML>') !== false) {
    return ['error' => 'Node disabled'];
  }

  $decoded = json_decode($resp, true);
  return $decoded;
}

function lrg_install_opendota_handler(
  \SimpleOpenDotaPHP\odota_api $od,
  string $hostname = 'https://api.opendota.com/api/'
): void {
  $od->set_get_callback(function ($url, $data) use ($hostname) {
    return lrg_opendota_http_request('GET', $hostname . $url, (array)($data ?? []));
  });
  $od->set_post_callback(function ($url, $data) use ($hostname) {
    return lrg_opendota_http_request('POST', $hostname . $url, (array)($data ?? []));
  });
}
