<?php

class TypeDefs {
	public static function str() { return [ 'type' => 'string' ]; }
	public static function int() { return [ 'type' => 'integer' ]; }
	public static function num() { return [ 'type' => 'number' ]; }
	public static function bool() { return [ 'type' => 'boolean' ]; }
	public static function any() { return [ ]; }
	public static function arrayOf($item) { return [ 'type' => 'array', 'items' => self::resolve($item) ]; }
	public static function obj($props = [], $required = []) {
		$resolved = [];
		foreach ($props as $k => $v) $resolved[$k] = self::resolve($v);
		$schema = [ 'type' => 'object', 'properties' => $resolved ];
		if (!empty($required)) $schema['required'] = $required;
		return $schema;
	}
	public static function mapOf($value) {
		return [ 'type' => 'object', 'additionalProperties' => self::resolve($value) ];
	}
	public static function mapOfIdKeys($value) {
		$schema = self::mapOf($value);
		$schema['x-numeric-keys'] = true;
		return $schema;
	}
	public static function mapWithPattern($value, $pattern) {
		return [
			'type' => 'object',
			'patternProperties' => [ $pattern => self::resolve($value) ],
			'additionalProperties' => false
		];
	}
	public static function mapWithKeys($value, $keys, $required = []) {
		$props = [];
		foreach ($keys as $key) $props[$key] = self::resolve($value);
		$schema = [
			'type' => 'object',
			'properties' => $props,
			'additionalProperties' => false
		];
		if (!empty($required)) $schema['required'] = $required;
		return $schema;
	}
	public static function oneOf($schemas) {
		return [ 'oneOf' => array_map([self::class, 'resolve'], $schemas) ];
	}
	public static function literal($enum) { return [ 'type' => 'string', 'enum' => (array)$enum ]; }
	public static function ref($name) { return [ '$ref' => '#/components/schemas/'.$name ]; }
	private static function resolve($schema) { return is_string($schema) ? [ '$ref' => '#/components/schemas/'.$schema ] : $schema; }
}

class SchemaRegistry {
	private static $schemas = [];

	public static function register($name, $schema) { self::$schemas[$name] = $schema; }
	public static function get($name) { return self::$schemas[$name] ?? null; }
	public static function all() { return self::$schemas; }
}

// Core reusable types should be registered from endpoint files or typedef files.

if (!function_exists('is_docs_mode')) {
	function is_docs_mode() {
		return isset($_GET['openapi']) || isset($_GET['swagger']);
	}
}

function normalize_response_by_schema($data, $endpoint_name) {
	global $endpointObjects;
	
	if (!isset($endpointObjects[$endpoint_name])) return $data;
	
	// Get the endpoint's return schema
	$factory = $endpointObjects[$endpoint_name];
	$mods = []; $vars = []; $report = [];
	$obj = $factory($mods, $vars, $report);
	$ref = new \ReflectionClass($obj);
	
	$returnSchema = null;
	foreach ($ref->getAttributes() as $attr) {
		if ($attr->getName() === 'ReturnSchema') {
			$args = $attr->getArguments();
			$returnSchema = $args['schema'] ?? ($args[0] ?? null);
			break;
		}
	}
	
	if (!$returnSchema || is_string($returnSchema)) {
		// If it's a string reference, get the actual schema
		if (is_string($returnSchema)) {
			$returnSchema = SchemaRegistry::get($returnSchema);
		}
	}
	
	if (!$returnSchema) return $data;
	
	return normalize_data_by_schema($data, $returnSchema);
}

function normalize_data_by_schema($data, $schema) {
	if (!is_array($schema)) return $data;
	
	$type = $schema['type'] ?? null;
	
	switch ($type) {
		case 'object':
			if (!is_array($data)) return $data;
			
			$normalized = [];
			foreach ($data as $key => $value) {
				if (isset($schema['properties'][$key])) {
					$normalized[$key] = normalize_data_by_schema($value, $schema['properties'][$key]);
				} else {
					$normalized[$key] = $value;
				}
			}
			return $normalized;
			
		case 'array':
			if (!is_array($data)) return $data;
			
			$items = $schema['items'] ?? null;
			if ($items) {
				return array_map(function($item) use ($items) {
					return normalize_data_by_schema($item, $items);
				}, $data);
			}
			return $data;
			
		case 'integer':
			return is_numeric($data) ? (int)$data : $data;
			
		case 'number':
			return is_numeric($data) ? (float)$data : $data;
			
		case 'boolean':
			return is_scalar($data) ? (bool)$data : $data;
			
		case 'string':
			return is_scalar($data) ? (string)$data : $data;
			
		default:
			return $data;
	}
}


