<?php 

define( 'REGEX_MASK', "/.()[]<>{}?-+^$\\" );

function create_fuzzy_regex(string $string) {
  $len = mb_strlen($string);

  if ($len < 4) return addcslashes($string, REGEX_MASK);

  $res = [];

  $ending = '';
  if ($len > 8) {
    $ending = '('.mb_substr($string, $len-2, 1).'|.)?('.mb_substr($string, $len-1, 1).'|.)?';
    $len -= 2;
  }

  for ($i = 1; $i < $len; $i++) {
    $left = mb_substr($string, 0, $i);
    $right = mb_substr($string, $i+1, $len-$i-1);
    $res[] = addcslashes($left, REGEX_MASK)."(.){1,2}".addcslashes($right, REGEX_MASK).$ending;
  }

  error_log('(('.implode(')|(', $res).'))');

  return '(('.implode(')|(', $res).'))';
}

function create_search_filters(string $searchstring) {
  global $cats;

  $token = ' ';

  $base = [];
  $filters = [];
  $words = [];

  $quotes = false;
  $lastword = "";

  $word = strtok($searchstring, $token);
  while ($word !== false) {
    if ($quotes || ($word[0] == '"' && $word[strlen($word)-1] == '"')) {
      if (!$quotes) {
        $lastword = $word;
      } else {
        $lastword .= ' '.$word;
      }
      if ($lastword[strlen($lastword)-1] == '"') {
        $words[] = addcslashes(mb_substr($lastword, 1, -1), REGEX_MASK);
      }
      $quotes = false;
    } else {
      if (strcasecmp($word, "ranked") === 0 || mb_stripos($word, "рейтинг")  === 0) {
        $word = "!type:pvp";
      }

      if ($word[0] == '!') {
        $command = explode(':', substr(strtolower($word), 1), 2);
        
        switch ($command[0]) {
          case 'cat': 
            if (!empty($cats) && isset($cats[$command[1]])) {
              $base = $cats[$command[1]]['filters'];
            }
            break;
          case 'region':
            $vals = explode(',', $command[1]);
            $regs = [];
            foreach ($vals as $v) {
              if (!is_numeric($v)) {
                if ($v == 'na' || $v == 'us') {
                  $regs[] = 1;
                  $regs[] = 2;
                  $regs[] = 101;
                  $regs[] = 108;
                } else if ($v == 'sa' || $v == 'peru' || $v == 'brazil' || $v == 'brasil') {
                  $regs[] = 10;
                  $regs[] = 105;
                  $regs[] = 14;
                  $regs[] = 15;
                  $regs[] = 108;
                } else if ($v == 'sea' || $v == 'korea' || $v == 'dubai' || $v == 'india') {
                  $regs[] = 4;
                  $regs[] = 5;
                  $regs[] = 6;
                  $regs[] = 16;
                  $regs[] = 103;
                } else if ($v == 'china') {
                  $regs[] = 12;
                  $regs[] = 107;
                } else if ($v == 'japan') {
                  $regs[] = 19;
                } else if ($v == 'asia') {
                  $regs[] = 4;
                  $regs[] = 5;
                  $regs[] = 6;
                  $regs[] = 16;
                  $regs[] = 19;
                  $regs[] = 103;
                  $regs[] = 12;
                  $regs[] = 107;
                } else if ($v == 'europe' || $v == 'eu') {
                  $regs[] = 3;
                  $regs[] = 8;
                  $regs[] = 9;
                  $regs[] = 102;
                  $regs[] = 1021;
                  $regs[] = 1022;
                } else if ($v == 'eue' || $v == 'eueast' || $v == 'cis' || $v == 'russia') {
                  $regs[] = 8;
                  $regs[] = 9;
                  $regs[] = 102;
                  $regs[] = 1022;
                } else if ($v == 'euw' || $v == 'euwest') {
                  $regs[] = 3;
                  $regs[] = 102;
                  $regs[] = 1021;
                } else if ($v == 'meta') {
                  $regs[] = 2001;
                  $regs[] = 299;
                } else if ($v == 'africa') {
                  $regs[] = 11;
                } else if ($v == 'australia') {
                  $regs[] = 7;
                }
              } else {
                $regs[] = $v;
                if ($v == 101) {
                  $regs[] = 1;
                  $regs[] = 2;
                } else if ($v == 102) {
                  $regs[] = 3;
                  $regs[] = 8;
                  $regs[] = 9;
                } else if ($v == 103) {
                  $regs[] = 4;
                  $regs[] = 5;
                  $regs[] = 6;
                  $regs[] = 16;
                  $regs[] = 19;
                } else if ($v == 105) {
                  $regs[] = 10;
                  $regs[] = 14;
                  $regs[] = 15;
                } else if ($v == 107) {
                  $regs[] = 12;
                } else if ($v == 108) {
                  $regs[] = 1;
                  $regs[] = 2;
                  $regs[] = 10;
                  $regs[] = 14;
                  $regs[] = 15;
                } else if ($v == 1021) {
                  $regs[] = 3;
                } else if ($v == 1022) {
                  $regs[] = 8;
                  $regs[] = 9;
                }
              }
            }
            $filters[] = [ LRG_TAG_FILTER_REGION_IN, $regs ];
            break;
          case 'team':
            $vals = explode(',', $command[1]);
            foreach ($vals as $v) {
              $v = (int)$v;
              if (!$v) continue;
              $filters[] = [ LRG_TAG_FILTER_TEAM_IN, $v ];
            }
            break;
          case 'player':
            $vals = explode(',', $command[1]);
            $filters[] = [ LRG_TAG_FILTER_PLAYER_IN, $vals ];
            break;
          case 'lid':
            $vals = explode(',', $command[1]);
            foreach ($vals as $v) {
              $filters[] = [ LRG_TAG_FILTER_ID, $v ];
            }
            break;
          case 'tag-has':
            $vals = explode(',', $command[1]);
            foreach ($vals as $v) {
              $filters[] = [ LRG_CAT_FILTER_TAG, "/".addcslashes($v, REGEX_MASK)."/" ];
            }
            break;
          case 'mid-from':
            $vals = explode(',', $command[1]);
            $filters[] = [ LRG_TAG_FILTER_MID_START, (int)min($vals) ];
            break;
          case 'mid-to':
            $vals = explode(',', $command[1]);
            $filters[] = [ LRG_TAG_FILTER_MID_END, (int)min($vals) ];
            break;
          case 'date-from':
            $vals = explode(',', $command[1]);
            $filters[] = [ LRG_TAG_FILTER_DATE_START, (int)min($vals) ];
            break;
          case 'date-to':
            $vals = explode(',', $command[1]);
            $filters[] = [ LRG_TAG_FILTER_DATE_END, (int)max($vals) ];
            break;
          case 'type':
            if ($command[1] == 'tvt' || $command[1] == 'teams' || $command[1] == 'competitive') {
              $filters[] = [ LRG_TAG_FILTER_REPORT_TYPE_TEAMS, true ];
            } else if ($command[1] == 'pvp' || $command[1] == 'players' || $command[1] == 'ranked') {
              $filters[] = [ LRG_TAG_FILTER_REPORT_TYPE_TEAMS, false ];
            }
            break;
          case 'matches-less':
            $vals = explode(',', $command[1]);
            $filters[] = [ LRG_TAG_FILTER_MATCHES_TOTAL_LESS, (int)min($vals) ];
            break;
          case 'matches-more':
            $vals = explode(',', $command[1]);
            $filters[] = [ LRG_TAG_FILTER_MATCHES_TOTAL_MORE, (int)max($vals) ];
            break;
          case 'days-less':
            $vals = explode(',', $command[1]);
            $filters[] = [ LRG_TAG_FILTER_DAYS_NUM_LESS, (int)min($vals) ];
            break;
          case 'days-more':
            $vals = explode(',', $command[1]);
            $filters[] = [ LRG_TAG_FILTER_DAYS_NUM_MORE, (int)max($vals) ];
            break;
          case 'patch':
            $vals = explode(',', $command[1]);
            foreach ($vals as $v) {
              if (strpos($v, '.') !== false) $v = unconvert_patch($v);
              $filters[] = [ LRG_TAG_FILTER_PATCH_IN, (int)$v ];
            }
          
            break;
          case 'patch-from':
            $vals = explode(',', $command[1]);
            $patchcodes = [];
            foreach ($vals as $v) {
              if (strpos($v, '.') !== false) $v = unconvert_patch($v);
              $patchcodes[] = (int)$v;
            }
            
            $filters[] = [ LRG_TAG_FILTER_PATCH_FROM, min($patchcodes) ];
            break;
          case 'patch-to':
            $vals = explode(',', $command[1]);
            $patchcodes = [];
            foreach ($vals as $v) {
              if (strpos($v, '.') !== false) $v = unconvert_patch($v);
              $patchcodes[] = (int)$v;
            }
            
            $filters[] = [ LRG_TAG_FILTER_PATCH_TO, max($patchcodes) ];
            break;
            
        }

      } elseif ($word[0] == '-') {
        $filters[] = [ LRG_TAG_FILTER_NAMEDESC_EXCLUSIVE, "/^(?!.*".addcslashes(mb_substr($word, 1), REGEX_MASK).").*$/iu" ];
      } elseif ($word[0] == '"') {
        $quotes = true;
        $lastword .= $word;
      } else {
        $words[] = create_fuzzy_regex($word);
      }
    }

    $word = strtok($token);
  }

  foreach ($words as $w) {
    $filters[] = [ LRG_TAG_FILTER_NAMEDESC, "/$w/iu" ];
  }

  $r = [];
  if (!empty($base)) {
    foreach ($base as $b) {
      $r[] = array_merge($b, $filters);
    }
  } else {
    $r = [ $filters ];
  }

  return $r;
}