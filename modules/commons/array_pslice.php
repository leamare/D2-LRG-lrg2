<?php 

function array_psplice(&$array, $offset = 0, $length = 1) {
	$return = array_slice($array, $offset, $length, true);

	foreach ($return as $key => $value) {
		unset($array[$key]);
	}

	return $return;
}

function array_insert_before(array $array, $key, array $new) {
	$keys = array_keys( $array );
	$index = array_search( $key, $keys );
	$pos = false === $index ? count( $array ) : $index;

	return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
}