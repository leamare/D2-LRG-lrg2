<?php
//ini_set('memory_limit', '1024M');
require_once("modules/commons/versions.php");
require_once("modules/commons/readline.php");
require_once("modules/commons/instaquery.php");
require_once("modules/commons/unzero.php");

if(isset($argv)) {
    $options = getopt("l:m:d:Ff:KT:o:c:SsRrZQw:LAP:N:e:uUpG:nWaM:j:b:", ["repair"]);

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

  $lrg_version = [2, 32, 0, 0, 0];

  $settings = json_decode(file_get_contents("rg_settings.json"), true);

  // legacy flat mysql_* keys act as the last resource on the list ("_legacy")
  $lrg_resources = [];
  foreach (($settings['resources'] ?? []) as $r_name => $r) {
    if (!is_array($r)) continue;
    $lrg_resources[(string)$r_name] = [
      'driver'   => $r['driver'] ?? 'mysql',
      'host'     => $r['host'] ?? '',
      'port'     => (int)($r['port'] ?? 0),
      'user'     => $r['user'] ?? '',
      'pass'     => $r['pass'] ?? '',
      'prefix'   => $r['prefix'] ?? '',
      'database' => $r['database'] ?? null,
    ];
  }
  if (!isset($lrg_resources['_legacy'])) {
    $legacy = [
      'driver'   => 'mysql',
      'host'     => $settings['mysql_host'] ?? '',
      'port'     => (int)($settings['mysql_port'] ?? 0),
      'user'     => $settings['mysql_user'] ?? '',
      'pass'     => $settings['mysql_pass'] ?? '',
      'prefix'   => $settings['mysql_prefix'] ?? '',
      'database' => null,
    ];
    if ($legacy['host'] !== '' || $legacy['user'] !== '' || $legacy['pass'] !== '' ||
        $legacy['prefix'] !== '' || empty($lrg_resources)) {
      $lrg_resources['_legacy'] = $legacy;
    }
  }
  unset($legacy);

  $lrg_resource_name = (string)array_key_first($lrg_resources);
  $lrg_resource = $lrg_resources[$lrg_resource_name];

  $lrg_sql_host  = $lrg_resource['host'];
  $lrg_sql_user  = $lrg_resource['user'];
  $lrg_sql_pass  = $lrg_resource['pass'];
  $lrg_sql_port  = $lrg_resource['port'];
  $lrg_db_prefix = $lrg_resource['prefix'];
  $steamapikey   = $settings['steamapikey'] ?? '';
  $stratztoken   = $settings['stratztoken'] ?? '';
  $odapikey      = $settings['odapikey'] ?? '';
  $mysql_median  = (bool)($settings['mysql_stats_func'] ?? false);
  $fallback_valveapi = $settings['steampi_fallback'] ?? false;

  $sti_blocks_query = $settings['sti_blocks_query'] ?? false;
  $sti_blocks_size  = $settings['sti_blocks_size'] ?? null;
  $stratz_user_agent = $settings['stratz_user_agent'] ?? "STRATZ_API";
  $lrg_web_reports_dir = (string)($settings['web_reports_dir'] ?? '');
  $lrg_backup_chunk_max_size = $settings['backup_chunk_max_size'] ?? null;

  unset($settings);

  require_once __DIR__ . '/modules/commons/mysqli_lrg.php';

  if(isset($lrg_league_tag) && file_exists("leagues/".$lrg_league_tag.".json")) {
    $lg_settings = file_get_contents("leagues/".$lrg_league_tag.".json");
    $lg_settings = json_decode($lg_settings, true);

    // optional `resource` key; absent = first resource on the list
    $r_name = (string)($lg_settings['resource'] ?? '');
    if ($r_name !== '' && $r_name !== $lrg_resource_name) {
      if (isset($lrg_resources[$r_name])) {
        $lrg_resource_name = $r_name;
        $lrg_resource = $lrg_resources[$r_name];

        $lrg_sql_host  = $lrg_resource['host'];
        $lrg_sql_user  = $lrg_resource['user'];
        $lrg_sql_pass  = $lrg_resource['pass'];
        $lrg_sql_port  = $lrg_resource['port'];
        $lrg_db_prefix = $lrg_resource['prefix'];
      } else {
        echo "[W] Unknown resource `$r_name` for `$lrg_league_tag`, using `$lrg_resource_name`\n";
      }
    }

    $lrg_sql_db = lrg_resource_db_name($lrg_resource, $lrg_league_tag);
  }
  $lrg_use_cache = true;

  $ignore_api_key = null;