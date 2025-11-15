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