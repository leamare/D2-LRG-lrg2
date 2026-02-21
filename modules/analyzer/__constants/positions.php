<?php

const POSITION_TO_ROLE_MAP = [
  1 => [
    1 => 1,
    2 => 2,
    3 => 3,
  ],
  0 => [
    1 => 5,
    3 => 4,
  ]
];

const ROLE_TO_POSITION_MAP = [
  1 => ['core' => 1, 'lane' => 1],
  2 => ['core' => 1, 'lane' => 2],
  3 => ['core' => 1, 'lane' => 3],
  4 => ['core' => 0, 'lane' => 3],
  5 => ['core' => 0, 'lane' => 1],
];
