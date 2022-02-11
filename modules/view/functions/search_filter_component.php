<?php

function search_filter_component($table_id, $wide = false, $inside = false) {
  return "<input
    name=\"filter\" 
    type=\"search\"
    class=\"search-filter".($wide ? " wide" : "").($inside ? " inside" : "")."\" 
    data-table-filter-id=\"$table_id\" 
    placeholder=\"".locale_string('filter_placeholder')."\" 
  />";
}