<?php

function resetbltime() {
  global $__bltime, $__extime, $__sttime;

  $__bltime = 0;

  $__extime = microtime(true);
  $__sttime = $__extime;
}

function echobltime() {
  global $__bltime, $__extime;

  $__bltime = microtime(true) - $__extime;
  $__extime += $__bltime;

  return round($__bltime, 2);
}