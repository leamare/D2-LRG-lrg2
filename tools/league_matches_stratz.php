<?php
require_once('head.php');

$_file = !empty($options['o']) ? $options['o'] : "matchlists/$lrg_league_tag.list";

$_ticket = !empty($options['T']) ? $options['T'] : die("# Specify ticket ID\n");

$matches = [];
$i = 0;

while (true) {
  $data = [
    'query' => "query TicketMatches {
      league(id: {$_ticket}) {
        matches(request: {take: 100, skip: ".($i * 100)."}) {
          id
        }
      }
    }"
  ];

  $data['query'] = str_replace("  ", "", $data['query']);
  $data['query'] = str_replace("\n", " ", $data['query']);

  if (!empty($stratztoken)) $data['key'] = $stratztoken;
    
  $stratz_request = "https://api.stratz.com/graphql";

  $json = file_get_contents($stratz_request, false, stream_context_create([
    'ssl' => [
      'verify_peer' => false,
      'verify_peer_name' => false,
    ],
    'http' => [
      'method' => 'POST',
      'header'  => "Content-Type: application/json\r\nKey: $stratztoken\r\nUser-Agent: STRATZ_API\r\n",
      'content' => json_encode($data),
      'timeout' => 60,
    ]
  ]));

  sleep(1);

  $stratz = json_decode($json, true);

  echo ".";

  if (!empty($stratz['errors'])) {
    throw new Exception(json_encode($stratz['errors'], JSON_PRETTY_PRINT));
  }

  if (empty($stratz['data']) || !empty($stratz['errors'])) {
    return null;
  }

  if (empty($stratz['data']['league']['matches'])) break;

  foreach ($stratz['data']['league']['matches'] as $match) {
    $matches[] = $match['id'];
  }

  $i++;
}

$matches = implode("\n", $matches);
echo "\n";

file_put_contents($_file, $matches);