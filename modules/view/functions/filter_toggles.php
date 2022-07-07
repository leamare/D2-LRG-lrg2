<?php 

function filter_toggle_single_component($param, $table, $value, $label, $group = null) {
  return "<input type=\"checkbox\" id=\"filter-toggle-$table-$param\" class=\"filter-toggle\" 
    data-table=\"$table\" 
    data-param=\"data-value-$param\"
    data-value=\"$value\" ".
    ($group ? "data-filter-group=\"$group\" " : "").
  "/>
  <label for=\"filter-toggle-$table-$param\">".locale_string($label, [ 'value' => $value ])."</label>";
}

// group: string
// description: array [ 'param' => [ 'value' => ..., 'table' => string|null, 'label' ] ]
// table:

function filter_toggles_component($group, $description, $table = null, $modifiers = '') {
  $filters = [];

  foreach ($description as $param => $desc) {
    $filters[] = filter_toggle_single_component(
      $param, 
      $desc['table'] ?? $table ?? 'table',
      $desc['value'] ?? 1,
      $desc['label'] ?? 'data_filter_'.$param,
      $group
    );
  }

  return "<div class=\"filter-toggles $modifiers\" data-filter-toggles-group=\"$group\">".implode("\n", $filters)."</div>";
}
