<?php  $__postfix = "?v=227013"; ?>
<!DOCTYPE html>
<html lang="<?php echo $locale; ?>">
  <head>
    <!--
      League Report Generator
      Spectral Alliance
      leamare/d2_lrg on github
     -->
    <?php
       if(file_exists("res/favicon.ico")) echo "<link rel=\"shortcut icon\" href=\"res/favicon.ico\" />";
       $uni_title = "";
       $rep_sm_title = $instance_title;
       if (!empty($leaguetag)) {
          $rep_sm_title .= " $title_separator ".$report['league_name'];
          $rep_sm_desc = ($report['league_name'] ?? "Tournaments")." Stats";
          $rep_sm_desc .= " $title_separator ".$report['league_desc'];

          $uni_title .= $report['orig_name'] ?? $report['league_name'];

          $title = explode("-", $mod);
          if ($title_slice_max) $title = array_slice($title, 0, $title_slice_max);
          $loc_titles = [];
          $uni_names = [];
          foreach ($title as $m) {
            $loc_titles[] = locale_string($m);
            $uni_names[] = locale_string($m, [], 'en');
          }
          $rep_sm_title .= ' '.$title_separator.' '.implode(' '.$title_separator.' ', $loc_titles);
          $uni_title .= ' '.$title_separator.' '.implode(' '.$title_separator.' ', $uni_names);
        } else {
          $rep_sm_title .= " $title_separator $instance_title_postfix";
          $rep_sm_desc = $instance_title;
          $rep_sm_desc .= " $title_separator $instance_long_desc";

          $uni_title = $instance_title_postfix;

          $page = (!empty($_GET['cat']) || isset($searchstring) || empty($cats)) ? 'meow' : (
            stripos($mod, "cats") !== false ? 'cats' : 'index'
          );

          if ($page != "index") {
            $rep_sm_title .= ' '.$title_separator.' '.$head_name;
            $uni_title .= ' '.$title_separator.' '.$head_name;
            if (!empty($head_desc))
              $rep_sm_desc .= ' '.$title_separator.' '.$head_desc;
          }
        }
    ?>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=1600px, initial-scale=0.4">
    <?php
      if (!isset($social_lid)) {
        if (isset($report['league_id'])) $social_lid = $report['league_id'];
        elseif (!empty($__lid_fallbacks)) {
          foreach ($__lid_fallbacks as $preg => $lid) {
            if (preg_match($preg, $leaguetag ?? $cat)) {
              $social_lid = $lid;
              break;
            }
          }
        }
      }

      if (isset($social_lid) && isset($league_logo_provider)) 
        $league_logo_link = str_replace('%LID%', $social_lid, $league_logo_provider);
      else 
        $league_logo_link = $social_lid_fallback ?? $host_link."/res/header_grafenium.jpg";

      echo "<meta name=\"title\" content=\"$rep_sm_title\">";
      echo "<meta name=\"description\" content=\"$rep_sm_desc\">";
      echo "<meta name=\"og:title\" content=\"$rep_sm_title\">";
      echo "<meta name=\"og:description\" content=\"$rep_sm_title\">";
      echo "<meta name=\"og:image\" content=\"".$league_logo_link."\">";
      echo "<meta name=\"twitter:title\" content=\"$rep_sm_title\">";
      echo "<meta name=\"twitter:description\" content=\"$rep_sm_title\">";
      echo "<meta name=\"twitter:image\" content=\"".$league_logo_link."\">";
      
      echo "<meta name=\"keywords\" content=\"".locale_string("system_meta_keywords")."\">";

      echo "<title>$rep_sm_title</title>";
      
    ?>
    
    <link href="res/valve_mimic.css<?php echo $__postfix; ?>" rel="stylesheet" type="text/css" />
    <link href="res/reports.css<?php echo $__postfix; ?>" rel="stylesheet" type="text/css" />
    <?php
      if($use_graphjs) {
        echo "<script type=\"text/javascript\" src=\"res/dependencies/Chart.bundle.min.js$__postfix\"></script>";
      }
      if($use_graphjs_boxplots) {
        echo "<script type=\"text/javascript\" src=\"res/dependencies/Chart.BoxPlot.min.js$__postfix\"></script>";
      }
      if($use_visjs) {
        echo "<script type=\"text/javascript\" src=\"res/dependencies/vis.min.js$__postfix\"></script>";
        echo "<script type=\"text/javascript\" src=\"res/dependencies/vis-network.min.js$__postfix\"></script>";
        echo "<link href=\"res/dependencies/vis.min.css$__postfix\" rel=\"stylesheet\" type=\"text/css\" />";
        echo "<link href=\"res/dependencies/vis-network.min.css$__postfix\" rel=\"stylesheet\" type=\"text/css\" />";
      }

          if(isset($override_style) && file_exists("res/custom_styles/".$override_style.".css"))
              echo "<link href=\"res/custom_styles/".$override_style.".css$__postfix\" rel=\"stylesheet\" type=\"text/css\" />";
          else if(isset($custom_style))
              echo "<link href=\"res/custom_styles/".$custom_style.".css$__postfix\" rel=\"stylesheet\" type=\"text/css\" />";
          else {
            if(empty($leaguetag) && !empty($noleague_style))
              echo "<link href=\"res/custom_styles/".$noleague_style.".css$__postfix\" rel=\"stylesheet\" type=\"text/css\" />";
            else if(!empty($default_style))
              echo "<link href=\"res/custom_styles/".$default_style.".css$__postfix\" rel=\"stylesheet\" type=\"text/css\" />";
          }
          if(isset($custom_logo))
              echo "<link href=\"res/custom_styles/logos/".$custom_logo.".css$__postfix\" rel=\"stylesheet\" type=\"text/css\" />";
     if (!empty($custom_head)) echo $custom_head; ?>
  </head>
  <body>
    <?php if (!empty($custom_body)) echo $custom_body; ?>
    <header class="navBar <?php  if (!empty($previewcode) && $_earlypreview) echo "early-access"; ?>">
      <div class="navLinks">
        <div class="navItem arrow"><a href="<?php echo $main_path; ?>"></a></div>
        <div class="navItem dotalogo"><a href=".<?php if(!empty($linkvars)) echo "?".$linkvars; ?>" title="Dota 2 League Reports"></a></div>
        <?php
          echo process_menu($title_links);
         ?>
      </div>
      <div class="topbar-postnav">
        <?php 
          if (!empty($_topbar_postnav)) echo $_topbar_postnav;
        ?>
      </div>
      <div class="locale-changer">
        <?php
        $link = substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], "/")+1);
          echo '<label><select onchange="setLocale(this.value);" class="select-locale">';
          foreach($locales as $loc => $lname) {
            $loc = str_replace(".json", "", $loc);
            if($loc == $locale || ($isBetaLocale && $loc == $locale."_beta"))
             echo '<option selected>'.$lname.'</option>';
            else
             echo '<option value="'.$loc.'">'.$lname.'</option>';
          }
          echo '</select></label>';

        ?>
      </div>
    </header>
    <?php 
      if (!empty($previewcode) && $_earlypreview) echo "<div class=\"support-me-block early-access\">".locale_string("earlypreview")."</div>";
      else if (!empty($support_me_block)) {
        if (isset($support_me_block_link))
          echo "<a href=\"$support_me_block_link\" target=\"_blank\" class=\"support-me-block support-me-block-link\">$support_me_block</a>";
        else
          echo "<div class=\"support-me-block\">$support_me_block</div>";
      }
    ?>
    <?php 
      if (!empty($support_me_block_second)) echo "<div class=\"support-me-block-secondary\">$support_me_block_second</div>";
    ?>
    <?php 
      if (!empty($ads_block) && !empty($leaguetag)) echo "<div class=\"ads-block-report\">$ads_block</div>";
    ?>
    <div id="content-wrapper" <?php echo $__tmpl_trust ? "" : "class=\"shady\""; ?>>
      <div id="header-image" class="section-header">
    <?php if (!empty($leaguetag)) { ?>
      <?php if(!isset($custom_logo)) {?>
        <h1><?php echo $report['league_name']; ?></h1>
        <h2><?php echo $report['league_desc']; ?></h2>
        <h3><?php echo locale_string($h3).": ".$report['random'][$h3]; ?></h3>
        <?php if (isset($report['settings']['3rd_party'])) echo "<h3>".implode(', ', (array)$report['settings']['3rd_party'])."</h3>"; ?>
        <?php 
          if (isset($report['league_id']) && isset($league_logo_provider) && !isset($custom_logo)) 
            echo "<div class=\"league-banner\"><img src=\"".str_replace('%LID%', $report['league_id'], $league_logo_provider)."\" alt=\"league_banner\" /></div>"
        ?>
      <?php } else { ?>
        <div id="image-logo"></div>
      <?php } ?>
    <?php } else { ?>
        <?php if(!isset($custom_logo)) {?>
          <h1><?php echo $head_name; ?></h1>
          <h2><?php if(isset($head_desc)) echo $head_desc; ?></h2>
        <?php } else { ?>
          <div id="image-logo"></div>
        <?php } ?>
    <?php } ?>
    <?php 
      if ($__tmpl_trust) {
        if (!empty($global_partners) || (!empty($leaguetag) && !empty($report['sponsors']))) {
          $stratz_partner = false;
          $partners_raw = (!empty($leaguetag) && !empty($report['sponsors'])) ? $report['sponsors'] : ($global_partners ?? []);
          $partners = [];
          foreach ($partners_raw as $type => $link) {
            if (stripos($type, "stratz") !== false) $stratz_partner = true;
            $partners[] = "<a target=\"_blank\" href=\"".$link."\" class=\"shoutout-link\">".
            // FIXME:
              (stripos($type, "stratz") !== false ? "<img src=\"https://spectral.gg/res/social/stratz_white.png\" class=\"sponsor-icon\"> " : "").
            $type."</a>";
          }

          echo "<div class=\"partners-list".(isset($custom_logo) ? " special-logo" : "").($stratz_partner ? " stratz-partner" : "")."\">";
          
          echo implode(", ", $partners);

          echo "</div>";
        }
      } else {
        echo "<div class=\"partners-list special-logo\"><img src=\"res/warning.png\" class=\"sponsor-icon\" /> ".locale_string('shady_alert')."</div>";
      }
    ?>
    </div>
      <div id="main-section" class="content-section">
        <?php
          if (!empty($custom_content)) echo "<br />".$custom_content;

          $output = join_selectors($modules, 0);

          echo $output;
        ?>
      </div>
    </div>
      <footer>
        <a href="https://dota2.com" target="_blank" rel="noopener">Dota 2</a> is a registered trademark of <a href="https://valvesoftware.com" target="_blank" rel="noopener">Valve Corporation.</a>
        Match replay data provided by <a href="https://stratz.com/" target="_blank" rel="noopener">STRATZ</a> and <a href="https://opendota.com" target="_blank" rel="noopener">OpenDota</a>.<br />
        Graphs are made with <a href="https://visjs.org" target="_blank" rel="noopener">vis.js</a> and <a href="http://www.chartjs.org/" target="_blank" rel="noopener">chart.js</a>.<br />
        Made by Spectral Leamare.<br /> Klozi is a registered trademark of Grafensky.<br />
        <?php if (!empty($custom_footer)) echo $custom_footer."<br />";
          echo "LRG web version: <a>".parse_ver($lg_version)."</a>. ";
        ?>
      </footer>
      <dialog class="modal-content" id="modal-box">
        <div class="modal-dialog-container">
          <div class="modal-header"></div>
          <div id="modal-text" class="modal-text"></div>
          <div id="modal-sublevel" class="modal-sublevel"></div>
        </div>
      </dialog>
      <script type="text/javascript" src="res/dependencies/jquery.min.js<?php echo $__postfix; ?>"></script>
      <script type="text/javascript" src="res/dependencies/jquery.tablesorter.min.js<?php echo $__postfix; ?>"></script>
      <!-- <script type="text/javascript" src="res/dependencies/jquery.tablesorter-mod.js<?php echo $__postfix; ?>"></script> -->
      <script type="text/javascript" src="res/reports.js<?php echo $__postfix; ?>"></script>
    </body>
  </html>
