<?php

function instaquery(&$conn, $query) {
  $r = [];

  if ($conn->multi_query($query) !== TRUE) 
    throw new Exception("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

  $query_res = $conn->store_result();
  for ($row = $query_res->fetch_assoc(); $row != null; $row = $query_res->fetch_assoc()) {
    $r[] = $row;
  }
  $query_res->free_result();

  return $r;
}