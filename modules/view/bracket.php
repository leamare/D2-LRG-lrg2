<?php

$modules['bracket'] = "";

function rg_view_generate_bracket() {
  global $unset_module, $mod, $root;
  include_once($root . "/modules/view/generators/bracket.php");

  // if ($mod == "bracket") $unset_module = true;
  return bracket_render();
}
