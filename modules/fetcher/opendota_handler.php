<?php

const LRG_OPENDOTA_TIMEOUT = 30;

function lrg_opendota_http_request(string $method, string $full_url, array $data = []) {
  $method = strtoupper($method);

  $opts = [
    'http' => [
      'timeout'       => LRG_OPENDOTA_TIMEOUT,
      'ignore_errors' => true,
    ],
  ];

  if ($method === 'POST') {
    $body = http_build_query($data);
    $opts['http']['method']  = 'POST';
    $opts['http']['content'] = $body;
    $opts['http']['header']  = "Content-Type: application/x-www-form-urlencoded\r\n"
                             . "Content-Length: " . strlen($body);
  } elseif (!empty($data)) {
    $full_url .= (strpos($full_url, '?') === false ? '?' : '&') . http_build_query($data);
  }

  $resp = @file_get_contents($full_url, false, stream_context_create($opts));

  if ($resp === false) return false;
  if (strpos($resp, '<!DOCTYPE HTML>') !== false) {
    return ['error' => 'Node disabled'];
  }
  return json_decode($resp, true);
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
