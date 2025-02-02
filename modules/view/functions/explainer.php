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