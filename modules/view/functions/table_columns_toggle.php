<?php 

function table_columns_toggle($table_id, $groups, $wide = false, $priorities = []) {
  return "<div class=\"table-column-toggles ".($wide ? "wide" : "")." ".(empty($priorities) ? 'greedy' : '')."\" data-table=\"$table_id\">".
    "<span class=\"table-column-toggles-name\">".locale_string('columns_toggle')."</span>".
    implode('', array_map(function($a, $p) use ($table_id) {
      $colgr = str_replace('.', '-', $a);

      return // "<button class=\"table-column-toggle-button\" data-group=\"$a\">".
        "<input type=\"checkbox\" id=\"$table_id-column-toggle-$a\" data-group=\"$colgr\" data-group-priority=\"".($p ?? 1)."\" />".
        "<label class=\"table-column-toggle-button\" for=\"$table_id-column-toggle-$a\">".
          locale_string($a).
        "</label>";
      // "</button>";
    }, $groups, $priorities)).
  "</div>";
}