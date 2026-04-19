<?php

const LRG_OPENDOTA_TIMEOUT = 30;

function lrg_opendota_http_request(string $method, string $full_url, array $data = []) {
  $method = strtoupper($method);
  $scheme = (stripos($full_url, 'https') === 0) ? 'https' : 'http';

  $opts = [
    $scheme => [
      'timeout'       => LRG_OPENDOTA_TIMEOUT,
      'ignore_errors' => true,
    ],
  ];

  if ($method === 'POST') {
    $body = http_build_query($data);
    $opts[$scheme]['method'] = 'POST';
    if ($body !== '') {
      $opts[$scheme]['header']  = "Content-Type: application/x-www-form-urlencoded\r\n"
                                . "Content-Length: " . strlen($body);
      $opts[$scheme]['content'] = $body;
    }
  } elseif (!empty($data)) {
    $full_url .= (strpos($full_url, '?') === false ? '?' : '&') . http_build_query($data);
  }

  $http_response_header = null;
  $resp = @file_get_contents($full_url, false, stream_context_create($opts));

  if ($method === 'POST') {
    $status = 0;
    if (!empty($http_response_header) && is_array($http_response_header)) {
      if (preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) $status = (int)$m[1];
    }
    $path = parse_url($full_url, PHP_URL_PATH) ?: $full_url;
    $tail = '';
    if ($resp !== false) {
      $j = json_decode($resp, true);
      if (is_array($j)) {
        if (isset($j['job']['jobId']))   $tail = ' jobId=' . $j['job']['jobId'];
        elseif (isset($j['error']))      $tail = ' error=' . substr((string)$j['error'], 0, 80);
      }
    }
    echo "[OD-POST] {$path} -> " . ($status ?: 'no-response') . $tail . "\n";
  }

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
