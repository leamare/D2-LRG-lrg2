<?php

include_once(__DIR__ . "/attributes.php");
include_once(__DIR__ . "/schema.php");

abstract class EndpointTemplate {

	protected $mods;
	protected $vars;
	protected $report;

	public function __construct(&$mods, &$vars, &$report) {
		$this->mods = $mods;
		$this->vars = $vars;
		$this->report = $report;
	}

	abstract public function process();

	public function toOpenApiPathItem() {
		$ref = new \ReflectionClass($this);
		$summary = '';
		$params = [];
		$modline = [];
		$returnSchema = null;
		$endpointName = strtolower($ref->getShortName());
		$tags = [];
		$filename = $ref->getFileName();
		if (strpos($filename, '/modules/main/') !== false) $tags[] = 'main';
		else $tags[] = 'report';
		foreach ($ref->getAttributes() as $attr) {
			$name = $attr->getName();
			if ($name === 'Description') {
				$args = $attr->getArguments();
				$summary = $args['text'] ?? ($args[0] ?? '');
			} else if ($name === 'GetParam') {
				$args = $attr->getArguments();
				$params[] = [
					'in' => 'query',
					'name' => $args['name'] ?? '',
					'required' => (bool)($args['required'] ?? false),
					'schema' => $args['schema'] ?? [ 'type' => 'string' ],
					'description' => $args['description'] ?? ''
				];
			} else if ($name === 'ModlineVar') {
				$args = $attr->getArguments();
				$desc = $args['description'] ?? '';
				$mlname = $args['name'] ?? '';
				$modline[] = [
					'in' => 'query',
					'name' => $mlname,
					'required' => false,
					'schema' => $args['schema'] ?? [ 'type' => 'string' ],
					'description' => trim($desc . (empty($mlname) ? '' : (strlen($desc)? ' ' : '') . "(modline: {$mlname}{id})"))
				];
			} else if ($name === 'ReturnSchema') {
				$args = $attr->getArguments();
				$returnSchema = $args['schema'] ?? ($args[0] ?? null);
			} else if ($name === 'Endpoint') {
				$args = $attr->getArguments();
				$endpointName = $args['name'] ?? $endpointName;
			}
		}

		$parameters = array_merge($params, $modline);

		// Extra optional flags for report endpoints
		if (in_array('report', $tags)) {
			$parameters[] = [
				'in' => 'query',
				'name' => 'league',
				'required' => false,
				'schema' => [ 'type' => 'string' ],
				'description' => 'Report tag (league) to load'
			];
			$parameters[] = [
				'in' => 'query',
				'name' => 'desc',
				'required' => false,
				'schema' => [ 'type' => 'boolean' ],
				'description' => 'Include report descriptor'
			];
			$parameters[] = [
				'in' => 'query',
				'name' => 'teamcard',
				'required' => false,
				'schema' => [ 'type' => 'boolean' ],
				'description' => 'Include team card when team is specified'
			];
		}

		$baseResponse = [
			'type' => 'object',
			'properties' => [
				'modline' => [ 'type' => 'string', 'nullable' => true ],
				'vars' => [ 'type' => 'object' ],
				'endpoint' => [ 'type' => 'string', 'nullable' => true ],
				'version' => [ 'type' => 'string', 'nullable' => true ],
				'result' => (is_string($returnSchema) ? [ '$ref' => '#/components/schemas/'.$returnSchema ] : ($returnSchema ?: [ 'type' => 'object' ])),
				'errors' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				'report' => [ 'type' => 'string', 'nullable' => true ],
				'report_desc' => [ 'type' => 'object' ],
				'team_card' => [ 'type' => 'object' ],
			]
		];

		$responses = [
			'200' => [
				'description' => 'OK',
				'content' => [
					'application/json' => [
						'schema' => $baseResponse
					]
				]
			]
		];

		return [
			'summary' => $summary,
			'tags' => $tags,
			'parameters' => $parameters,
			'responses' => $responses
		];
	}
}




