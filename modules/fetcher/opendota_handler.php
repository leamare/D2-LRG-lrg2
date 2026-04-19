<?php

const LRG_OPENDOTA_TIMEOUT = 30;

function lrg_opendota_http_request(string $method, string $full_url, array $data = []) {
  $method = strtoupper($method);

  // NOTE: PHP's HTTP stream wrapper requires the 'http' key for context options,
  // even when the URL scheme is https. Using 'https' here makes PHP silently
  // ignore everything (method, headers, content, timeout) and fall back to GET.
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

  $http_response_header = null;
  $resp = @file_get_contents($full_url, false, stream_context_create($opts));

  if ($method === 'POST') {
    $status = 0;
    if (!empty($http_response_header) && is_array($http_response_header)) {
      if (preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) $status = (int)$m[1];
    }
    $path = parse_url($full_url, PHP_URL_PATH) ?: $full_url;
    $tail = '';
    if ($resp !== false && $resp !== '') {
      $j = json_decode($resp, true);
      if (is_array($j)) {
        if (isset($j['job']['jobId']))   $tail = ' jobId=' . $j['job']['jobId'];
        elseif (isset($j['error']))      $tail = ' error=' . substr((string)$j['error'], 0, 120);
        else                             $tail = ' resp=' . substr($resp, 0, 120);
      } else {
        $tail = ' resp=' . substr($resp, 0, 120);
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
