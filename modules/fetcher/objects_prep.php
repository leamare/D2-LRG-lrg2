<?php
/** @var mysqli $conn */

$lg_settings['main']['teams']        = !empty($schema['teams']);
$lg_settings['main']['items']        = !empty($schema['items']);
$lg_settings['main']['itemslines']   = !empty($schema['itemslines']);
$lg_settings['main']['skill_builds'] = !empty($schema['skill_builds']);
$lg_settings['main']['starting']     = !empty($schema['starting_items']);

if ($lg_settings['main']['fantasy'] && !$schema['fantasy_mvp']) {
  create_fantasy_mvp_tables($conn);
}

$json = "";
if ($lg_settings['main']['teams']) {
  $t_teams = [];
  $sql = "SELECT teamid, name, tag FROM teams";
  if ($conn->multi_query($sql)) {
    $res = $conn->store_result();
    while ($row = $res->fetch_row()) {
      $t_teams[$row[0]] = ["name" => $row[1], "tag" => $row[2], "added" => true];
    }
    $res->free();
  }
}

$t_players = [];
$sql = "SELECT playerid, nickname FROM players;";
if ($conn->multi_query($sql) === TRUE) {
  $res = $conn->store_result();
  for ($row = $res->fetch_row(); $row != null; $row = $res->fetch_row()) {
    $t_players[(int)$row[0]] = $row[1];
  }
  $res->free_result();
} else die("Something went wrong: " . $conn->error . "\n");

$t_leagues = [];
if ($schema['leagues'] ?? false) {
  $sql = "SELECT ticket_id, name, url, description FROM leagues;";
  if ($conn->multi_query($sql) === TRUE) {
    $res = $conn->store_result();
    for ($row = $res->fetch_row(); $row != null; $row = $res->fetch_row()) {
      $t_leagues[(int)$row[0]] = [
        "name"        => $row[1],
        "url"         => $row[2],
        "description" => $row[3],
        "added"       => true,
      ];
    }
    $res->free_result();
  } else die("Something went wrong when loading leagues: " . $conn->error . "\n");
}
