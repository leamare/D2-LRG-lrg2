<?php

/**
 * LRG Library - wrap_data v 0.1
 * Originally ported from D2-LRG-Simon codebase
 * wrap_data and unwrap_data functions used to wrap and unwrap similar JSON structures
 * @author Darien "leamare" Fawkes
 * @license Apache2
 */

function wrap_data ($array, $with_keys = false, $deep = false, $explicit = false) {
  if (!is_array($array) || !is_array(reset($array))) return $array;

  $r = [];

  if ($explicit) {
    $r['head'] = [];
    foreach ($array as $h) {
      if(!is_array($h) && $h !== null) {
        return $array;
      } else if ($h === null) {
        continue;
      }
      foreach ($h as $k => $v) {
        if (!in_array($k, $r['head']))
          $r['head'][] = $k;
      }
    }
  } else {
    $r['head'] = array_keys(reset($array));
  }

  if($with_keys)
    $r['keys'] = array_keys($array);

  $r['data'] = [];

  foreach($array as $a) {
    if (!is_array($a) || $a === null) {
      $r['data'][] = $a;
      continue;
    }
    if (empty($a)) {
      $r['data'][] = [];
    } else if ($explicit) {
      $data = [];
      foreach ($r['head'] as $key) {
        if (isset($a[$key]))
          $data[] = $a[$key];
        else
          $data[] = null;
      }
      $r['data'][] = $data;
    } else {
      $r['data'][] = array_values($a);
    }
  }

  $lastval = 0;
  foreach ($r['data'] as $i => $v) {
    if ($v !== null) $lastval = $i;
  }
  if ($lastval < sizeof($r['data'])-1) {
    $r['data'] = array_slice($r['data'], 0, $lastval+1);
  }

  if ($deep && isset($r['data'][0][0]) && is_array($r['data'][0][0])) {
    $r['head'] = [ $r['head'] ];

    foreach ($r['data'] as $k => $hv) {
      $hv = wrap_data($hv, false, $deep, $explicit);
      if(!isset($hv['data'])) continue;
      $r['data'][$k] = $hv['data'];
      if(!isset($r['head'][1])) {
        if(!is_array($hv['head'][0])) $hv['head'] = [ $hv['head'] ];
        $r['head'] = array_merge($r['head'], $hv['head']);
      }
    }
  }

  // foreach ($r['data'] as &$l)
  //   $l = implode('||', $l);
  // $r['data'] = json_encode($r['data']);

  return $r;
}

function unwrap_data ($array) {
  $r = [];
  if (empty($array) || empty($array['head']))
    return $r;
  if(is_array($array['head'][0])){
    $head = $array['head'][0];
    $array['head'] = array_splice($array['head'], 1);
  } else $head = $array['head'];

  $head_sz = sizeof($head);

  foreach ($array['data'] as $data) {
    if ($data === null) {
      $r[] = null;
      continue;
    }
    if(is_array($array['head']) && is_array(reset($array['head'])) && is_array(reset($data)) && sizeof($array['head'])) {
      $data = unwrap_data([ 'head' => $array['head'], 'data' => $data ]);
    }
    

    if (is_array($data) && sizeof($data) < $head_sz) {
      $data = array_merge( $data, array_fill(0, $head_sz - sizeof($data), null) );
    }
    $r[] = is_array($data) ? array_combine($head, $data) : $data;
  }
  if(isset($array['keys']))
    $r = array_combine($array['keys'], $r);
  return $r;
}

function is_wrapped ($array) {
  return is_array($array) && isset($array['head']) && isset($array['data']);
}