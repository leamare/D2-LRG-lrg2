<?php 

function explainer_block($string) {
  return "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      implode(
        "",
        array_map(function($a) {
          return "<div class=\"line\">".$a."</div>";
        }, explode("\n", $string))
      ).
    "</div>".
  "</details>";
}

function simple_view_button() {
  return "<div class=\"content-text simple-view-solo\">".
    "<button type=\"button\" class=\"simple-view-toggle\" aria-pressed=\"false\">".
      locale_string("simple_view").
    "</button>".
  "</div>";
}

function simple_view_table_toggle() {
  return "<div class=\"filter-toggles wide simple-view-filter\">".
    "<input type=\"checkbox\" id=\"simple-view-toggle\" class=\"simple-view-input\" />".
    "<label for=\"simple-view-toggle\">".locale_string("simple_view")."</label>".
  "</div>";
}
