<?php
//ini_set('memory_limit', '1024M');
require_once("modules/commons/versions.php");
require_once("modules/commons/readline.php");
require_once("modules/commons/instaquery.php");
require_once("modules/commons/unzero.php");

if(isset($argv)) {
    $options = getopt("l:m:d:FfKT:o:c:SsRrZQw:LAP:N:e:uUpG:nW");

    if(isset($options['l'])) {
      $lrg_league_tag = $options['l'];

      if( is_array($lrg_league_tag) ) {
        $tmp_len = 0;
        foreach ($lrg_league_tag as $val) {
            if ( strlen($val) > $tmp_len ) {
                $tmp_val = $val;
                $tmp_len = strlen($val);
            }
        }
        unset($tmp_len);
        $lrg_league_tag = $tmp_val;
        unset($tmp_val);
      }
    }
    if(isset($options['K'])) {
        $ignore_api_key = true;
    }
  }

# global settings

  $lrg_version = [2, 26, 1, 0, 0];

  $settings = json_decode(file_get_contents("rg_settings.json"), true);

  $lrg_sql_host  = $settings['mysql_host'] ?? '';
  $lrg_sql_user  = $settings['mysql_user'] ?? '';
  $lrg_sql_pass  = $settings['mysql_pass'] ?? '';
  $lrg_db_prefix = $settings['mysql_prefix'] ?? '';
  $steamapikey   = $settings['steamapikey'] ?? '';
  $stratztoken   = $settings['stratztoken'] ?? '';
  $odapikey      = $settings['odapikey'] ?? '';
  $mysql_median  = (bool)($settings['mysql_stats_func'] ?? false);
  $fallback_valveapi = $settings['steampi_fallback'] ?? false;

  unset($settings);

  if(isset($lrg_league_tag) && file_exists("leagues/".$lrg_league_tag.".json")) {
    $lrg_sql_db   = $lrg_db_prefix."_".$lrg_league_tag;

    $lg_settings = file_get_contents("leagues/".$lrg_league_tag.".json");
    $lg_settings = json_decode($lg_settings, true);
  }
  $lrg_use_cache = true;
