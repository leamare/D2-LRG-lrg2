<?php 

function array_psplice(&$array, $offset = 0, $length = 1) {
	$return = array_slice($array, $offset, $length, true);

	foreach ($return as $key => $value) {
		unset($array[$key]);
	}

	return $return;
}