<?php  $__postfix = "?v=25063"; ?>
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
       $rep_sm_title = $instance_title;
       if (!empty($leaguetag)) {
          $rep_sm_title .= " $title_separator ".$report['league_name'];
          $rep_sm_desc = ($report['league_name'] ?? "Tournaments")." Stats";
          $rep_sm_desc .= " $title_separator ".$report['league_desc'];

          $title = explode("-", $mod);
          if ($title_slice_max) $title = array_slice($title, 0, $title_slice_max);
          $loc_titles = [];
          foreach ($title as $m) {
            $loc_titles[] = locale_string($m);
          }
          $rep_sm_title .= ' '.$title_separator.' '.implode(' '.$title_separator.' ', $loc_titles);
        } else {
          $rep_sm_title .= " $title_separator $instance_title_postfix";
          $rep_sm_desc = $instance_title;
          $rep_sm_desc .= " $title_separator $instance_long_desc";

          if (isset($cat) && $cat != 'main') {
            $rep_sm_title .= ' '.$title_separator.' '.$head_name;
            if (!empty($head_desc))
              $rep_sm_desc .= ' '.$title_separator.' '.$head_desc;
          }
        }
       
       $host_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 
                "https" : "http") . "://" . $_SERVER['HTTP_HOST'] .  
                dirname($_SERVER['REQUEST_URI']); 
    ?>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=1600px, initial-scale=0.4">
    <?php
      if (isset($report['league_id']) && isset($league_logo_provider)) 
        $league_logo_link = str_replace('%LID%', $report['league_id'], $league_logo_provider);
      else 
        $league_logo_link = $host_link."/res/header_grafenium.jpg";

      echo "<meta name=\"title\" content=\"$rep_sm_title\">";
      echo "<meta name=\"description\" content=\"$rep_sm_desc\">";
      echo "<meta name=\"og:title\" content=\"$rep_sm_title\">";
      echo "<meta name=\"og:description\" content=\"$rep_sm_title\">";
      echo "<meta name=\"og:image\" content=\"".$league_logo_link."\">";
      echo "<meta name=\"twitter:title\" content=\"$rep_sm_title\">";
      echo "<meta name=\"twitter:description\" content=\"$rep_sm_title\">";
      echo "<meta name=\"twitter:image\" content=\"".$league_logo_link."\">";
      
      if (!empty($meta_keywords)) echo "<meta name=\"keywords\" content=\"$meta_keywords\">";

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
    <header class="navBar">
      <div class="navLinks">
        <div class="navItem dotalogo"><a href="<?php echo $main_path; ?>"></a></div>
        <div class="navItem bold"><a href=".<?php if(!empty($linkvars)) echo "?".$linkvars; ?>" title="Dota 2 League Reports"><?php echo locale_string("leag_reports")?></a></div>
        <?php
          foreach($title_links as $link) {
            echo "<div class=\"navItem\"><a href=\"".$link['link']."\" target=\"_blank\" rel=\"noopener\" title=\"".$link['title']."\">".$link['text']."</a></div>";
          }
         ?>
      </div>
      <div class="locale-changer">
        <?php
        $link = substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], "/")+1);
          echo '<label><select onchange="setLocale(this.value);" class="select-locale">';
          foreach($locales as $loc => $lname) {
            $loc = str_replace(".json", "", $loc);
            if($loc == $locale)
             echo '<option selected>'.$lname.'</option>';
            else
             echo '<option value="'.$loc.'">'.$lname.'</option>';
          }
          echo '</select></label>';

        ?>
      </div>
    </header>
    <?php 
      if (!empty($support_me_block)) echo "<div class=\"support-me-block\">$support_me_block</div>";
    ?>
    <?php 
      if (!empty($support_me_block_second)) echo "<div class=\"support-me-block-secondary\">$support_me_block_second</div>";
    ?>
    <?php 
      if (!empty($ads_block) && !empty($leaguetag)) echo "<div class=\"ads-block-report\">$ads_block</div>";
    ?>
    <div id="content-wrapper">
      <div id="header-image" class="section-header">
    <?php if (!empty($leaguetag)) { ?>
      <?php if(!isset($custom_logo)) {?>
        <h1><?php echo $report['league_name']; ?></h1>
        <h2><?php echo $report['league_desc']; ?></h2>
        <h3><?php echo locale_string($h3).": ".$report['random'][$h3]; ?></h3>
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
      <div class="modal" id="modal-box">
        <div class="modal-content">
          <div class="modal-header"></div>
          <div id="modal-text" class="modal-text"></div>
          <div id="modal-sublevel" class="modal-sublevel"></div>
        </div>
      </div>
      <script type="text/javascript" src="res/dependencies/jquery.min.js<?php echo $__postfix; ?>"></script>
      <script type="text/javascript" src="res/dependencies/jquery.tablesorter.min.js<?php echo $__postfix; ?>"></script>
      <script type="text/javascript" src="res/reports.js<?php echo $__postfix; ?>"></script>
    </body>
  </html>
