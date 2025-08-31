<?php

/**
 * Function to calculate element's rank based on
 * Wilson score confidence interval for a Bernoulli parameter
 *
 * Based on: http://www.evanmiller.org/how-not-to-sort-by-average-rating.html
 */

function wilson_rating($positive, $total = 0, $confidence = 0.95) {
  if (!$total) return 0;

  $pnorm = pnormaldist(1-(1-$confidence)/2);

  $percentage = $positive / $total;
  $score = ( $percentage + $pnorm*$pnorm / (2*$total) - $pnorm * sqrt( ( $percentage*(1-$percentage) + $pnorm*$pnorm / (4*$total) )/$total ) ) /
              ( 1 + $pnorm*$pnorm / $total );
  return $score;
}

function pnormaldist($qn) {
  $b = array(
    1.570796288, 0.03706987906, -0.8364353589e-3,
    -0.2250947176e-3, 0.6841218299e-5, 0.5824238515e-5,
    -0.104527497e-5, 0.8360937017e-7, -0.3231081277e-8,
    0.3657763036e-10, 0.6936233982e-12);

  if ($qn < 0.0 || 1.0 < $qn)
    return 0.0;

  if ($qn == 0.5)
    return 0.0;

  $w1 = $qn;

  if ($qn > 0.5)
    $w1 = 1.0 - $w1;

  $w3 = - log(4.0 * $w1 * (1.0 - $w1));
  $w1 = $b[0];

  for ($i = 1;$i <= 10; $i++)
    $w1 += $b[$i] * pow($w3,$i);

  if ($qn > 0.5)
    return sqrt($w1 * $w3);

  return - sqrt($w1 * $w3);
}

function inverse_ncdf($p) {
  //Inverse ncdf approximation by Peter John Acklam, implementation adapted to
  //PHP by Michael Nickerson, using Dr. Thomas Ziegler's C implementation as
	//a guide.  http://home.online.no/~pjacklam/notes/invnorm/index.html
	//I have not checked the accuracy of this implementation.  Be aware that PHP
	//will truncate the coeficcients to 14 digits.

	//You have permission to use and distribute this function freely for
	//whatever purpose you want, but please show common courtesy and give credit
	//where credit is due.

	//Input paramater is $p - probability - where 0 < p < 1.

  //Coefficients in rational approximations
  $a = array(1 => -3.969683028665376e+01, 2 => 2.209460984245205e+02,
    			 3 => -2.759285104469687e+02, 4 => 1.383577518672690e+02,
    			 5 => -3.066479806614716e+01, 6 => 2.506628277459239e+00);

  $b = array(1 => -5.447609879822406e+01, 2 => 1.615858368580409e+02,
          		 3 => -1.556989798598866e+02, 4 => 6.680131188771972e+01,
    			 5 => -1.328068155288572e+01);

  $c = array(1 => -7.784894002430293e-03, 2 => -3.223964580411365e-01,
    	 			 3 => -2.400758277161838e+00, 4 => -2.549732539343734e+00,
    			 5 => 4.374664141464968e+00, 6 => 2.938163982698783e+00);

  $d = array(1 => 7.784695709041462e-03, 2 => 3.224671290700398e-01,
    	 			 3 => 2.445134137142996e+00, 4 => 3.754408661907416e+00);

  //Define break-points.
  $p_low =  0.02425;									 //Use lower region approx. below this
  $p_high = 1 - $p_low;								 //Use upper region approx. above this

  //Define/list variables (doesn't really need a definition)
  //$p (probability), $sigma (std. deviation), and $mu (mean) are user inputs
  $q = NULL; $x = NULL; $y = NULL; $r = NULL;

  //Rational approximation for lower region.
  if (0 < $p && $p < $p_low) {
    $q = sqrt(-2 * log($p));
    $x = ((((($c[1] * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]) *
   	 	 	 $q + $c[6]) / (((($d[1] * $q + $d[2]) * $q + $d[3]) * $q + $d[4]) *
  	 		 $q + 1);
  }

  //Rational approximation for central region.
  elseif ($p_low <= $p && $p <= $p_high) {
    $q = $p - 0.5;
    $r = $q * $q;
    $x = ((((($a[1] * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) *
   	 	 	 $r + $a[6]) * $q / ((((($b[1] * $r + $b[2]) * $r + $b[3]) * $r +
  	 		 $b[4]) * $r + $b[5]) * $r + 1);
  }

  //Rational approximation for upper region.
  elseif ($p_high < $p && $p < 1) {
    $q = sqrt(-2 * log(1 - $p));
    $x = -((((($c[1] * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q +
   	 	 	 $c[5]) * $q + $c[6]) / (((($d[1] * $q + $d[2]) * $q + $d[3]) *
  	 		 $q + $d[4]) * $q + 1);
  }

  //If 0 < p < 1, return a null value
  else {
  	$x = NULL;
  }

  return $x;
  //END inverse ncdf implementation.
}

function compound_ranking_sort(&$a, &$b, $total_matches) {
  $a_contest = ($a['matches_picked'] + $a['matches_banned'])/$total_matches;
  $a_oi_rank = ($a['matches_picked']*$a['winrate_picked'] + $a['matches_banned']*$a['winrate_banned']) / $total_matches;
  $a_rank = wilson_rating( ($a['matches_picked']*$a['winrate_picked'] + $a['matches_banned']*$a['winrate_banned']/2), ($a['matches_picked'] + $a['matches_banned']/2), 1-$a_contest ) * ($a_oi_rank/4+0.75);

  $b_contest = ($b['matches_picked'] + $b['matches_banned'])/$total_matches;
  $b_oi_rank = ($b['matches_picked']*$b['winrate_picked'] + $b['matches_banned']*$b['winrate_banned']) / $total_matches;
  $b_rank = wilson_rating( ($b['matches_picked']*$b['winrate_picked'] + $b['matches_banned']*$b['winrate_banned']/2), ($b['matches_picked'] + $b['matches_banned']/2), 1-$b_contest ) * ($b_oi_rank/4+0.75);

  if($a_rank == $b_rank) return 0;
  else return ($a_rank < $b_rank) ? 1 : -1;
}

function compound_ranking(&$arr, $total_matches) {
  foreach ($arr as $k => $v) {
    $v_contest = ($v['matches_picked'] + $v['matches_banned'])/$total_matches;
    if ($v_contest > 1) {
      $v_contest = 1;
    }
    $v_oi_rank = ($v['matches_picked']*$v['winrate_picked'] + $v['matches_banned']*$v['winrate_banned']) / $total_matches;
    $arr[$k]['wrank'] = wilson_rating(
      ($v['matches_picked']*$v['winrate_picked'] + $v['matches_banned']*$v['winrate_banned']/2),
      ($v['matches_picked'] + $v['matches_banned']/2), 1-$v_contest
    ) * ($v_oi_rank/4+0.75);
  }
}

function compound_ranking_laning(&$arr, $total_matches, $median_adv, $median_disadv) {
  foreach ($arr as $k => $v) {
    $v_popularity = $v['matches']/$total_matches;
    $v_adv_factor = ($v['avg_advantage'] > 0 && $median_adv > 0 ? $v['avg_advantage']/$median_adv : 0) + 
      ($v['avg_disadvantage'] > 1 ? $median_disadv/$v['avg_disadvantage'] : 0);
    $v_matches = $v['matches'] ? $total_matches*(0.7+$v_popularity*0.3) : 0;

    if ($v['matches']) {
      $v_m = $v_matches * $v['lane_wr'] * (
        (($v['won_from_won']+$v['won_from_tie']+$v['won_from_behind'])/$v['matches'])/4
      ) * $v_adv_factor;
    } else $v_m = 0;

    if ($v_matches < $v_m) {
      $v_m = $v_matches;
    }

    $arr[$k]['wrank'] = wilson_rating( $v_m, $v_matches, 1-$v_popularity );
  }
}

function compound_ranking_laning_sort($a, $b, $total_matches, $median_adv, $median_disadv) {
  $a_popularity = $a['matches']/$total_matches;
  $b_popularity = $b['matches']/$total_matches;

  $a_adv_factor = ($a['avg_advantage'] > 0 && $median_adv > 0 ? $a['avg_advantage']/$median_adv : 0)+($a['avg_disadvantage'] > 1 ? $median_disadv/$a['avg_disadvantage'] : 0);
  $b_adv_factor = ($b['avg_advantage'] > 0 && $median_adv > 0 ? $b['avg_advantage']/$median_adv : 0)+($b['avg_disadvantage'] > 1 ? $median_disadv/$b['avg_disadvantage'] : 0);


  $a_matches = $a['matches'] ? $total_matches*(0.7+$a_popularity*0.3) : 0;
  $b_matches = $b['matches'] ? $total_matches*(0.7+$b_popularity*0.3) : 0;

  if ($a['matches']) {
    $a_m = $a_matches * $a['lane_wr'] * (
      (($a['won_from_won']+$a['won_from_tie']+$a['won_from_behind'])/$a['matches'])/4
    ) * $a_adv_factor;
  } else $a_m = 0;

  if ($b['matches']) {
    $b_m = $b_matches * $b['lane_wr'] * (
      (($b['won_from_won']+$b['won_from_tie']+$b['won_from_behind'])/$b['matches'])/4
    ) * $b_adv_factor;
  } else $b_m = 0;

  $a_rank = wilson_rating( $a_m, $a_matches, 1-$a_popularity );
  $b_rank = wilson_rating( $b_m, $b_matches, 1-$b_popularity );

  if($a_rank == $b_rank) return 0;
  else return ($a_rank < $b_rank) ? 1 : -1;
}

function positions_ranking_sort($a, $b, $total_matches) {
  $a_matches = $a['matches_s'] ?? $a['matches'];
  $a_winrate = $a['winrate_s'] ?? $a['winrate'];
  $b_matches = $b['matches_s'] ?? $b['matches'];
  $b_winrate = $b['winrate_s'] ?? $b['winrate'];

  $a_popularity = $a_matches/$total_matches;
  $b_popularity = $b_matches/$total_matches;

  $a_rank = wilson_rating( $a_matches*$a_winrate, $a_matches, 1-$a_popularity );
  $b_rank = wilson_rating( $b_matches*$b_winrate, $b_matches, 1-$b_popularity );

  if($a_rank == $b_rank) return 0;
  else return ($a_rank < $b_rank) ? 1 : -1;
}

function positions_ranking(&$arr, $total_matches) {
  foreach ($arr as $k => $v) {
    $v_matches = $v['matches_s'] ?? $v['matches'];
    $v_winrate = $v['winrate_s'] ?? $v['winrate'];
    $v_popularity = $v_matches/$total_matches;
    $arr[$k]['wrank'] = wilson_rating( $v_matches*$v_winrate, $v_matches, 1-$v_popularity );
  }
}

function items_ranking_sort($a, $b) {
  $a_rank = wilson_rating( $a['wins'], $a['purchases'], 1-$a['prate'] );
  $b_rank = wilson_rating( $b['wins'], $b['purchases'], 1-$b['prate'] );

  if($a_rank == $b_rank) return 0;
  else return ($a_rank < $b_rank) ? 1 : -1;
}

function items_ranking(&$arr) {
  foreach ($arr as $k => $v) {
    if (!$v['purchases'] || !$v['prate']) {
      $arr[$k]['wrank'] = 0; continue;
    }
    $arr[$k]['wrank'] = wilson_rating( $v['wins'], $v['purchases'], 1-$v['prate'] );
  }
}
