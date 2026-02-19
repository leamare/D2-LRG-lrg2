<?php 

$result['tickets'] = [];

$sql = "SELECT leagueID, COUNT(matchid) as matches FROM matches GROUP BY leagueID ORDER BY matches DESC;";

$query_res = $conn->query($sql);
if ($query_res === FALSE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
else echo "[S] Requested data for TICKETS\n";

for ($row = $query_res->fetch_assoc(); $row != null; $row = $query_res->fetch_assoc()) {
  $result['tickets'][$row['leagueID']] = [
    'matches' => (int)$row['matches'],
  ];
}

$query_res->free_result();

if ($schema['leagues'] ?? false) {
  $league_ids = array_keys($result['tickets']);
  if (!empty($league_ids)) {
    $league_ids_str = implode(',', array_map('intval', $league_ids));
    $sql = "SELECT ticket_id, name FROM leagues WHERE ticket_id IN ($league_ids_str);";
    
    $query_res = $conn->query($sql);
    if ($query_res !== FALSE) {
      for ($row = $query_res->fetch_assoc(); $row != null; $row = $query_res->fetch_assoc()) {
        if (isset($result['tickets'][$row['ticket_id']])) {
          $result['tickets'][$row['ticket_id']]['name'] = $row['name'];
        }
      }
      $query_res->free_result();
    }
  }
}