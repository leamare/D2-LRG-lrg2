<?php
$res["region".$region]['records'] = "";

if(check_module($modstr."-records")) {
  include("$root/modules/view/generators/records.php");

  $res["region".$region]['records'] = rg_generator_records_ext($reg_report['records'], $reg_report['records_ext']);

  $res["region".$region]['records'] .= "<div class=\"content-text\">".locale_string("desc_records")."</div>";
}

?>
