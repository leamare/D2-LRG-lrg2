<?php 

#[Endpoint(name: 'metadata')]
#[Description('Return requested metadata chunks by keys listed in GET gets')]
#[GetParam(name: 'gets', required: true, schema: ['type' => 'array', 'items' => ['type' => 'string']], description: 'Comma-delimited list of metadata keys')]
#[ReturnSchema(schema: [
  'type' => 'object',
  'description' => 'Object with requested metadata keys as properties',
  'additionalProperties' => [
    'oneOf' => [
      ['type' => 'string'],
      ['type' => 'number'],
      ['type' => 'integer'],
      ['type' => 'boolean'],
      ['type' => 'array'],
      ['type' => 'object']
    ]
  ]
])]
class Metadata extends EndpointTemplate {
	public function process() {
		global $meta;
		$res = [];
		if (empty($this->vars['gets'])) return null;
		foreach ($this->vars['gets'] as $m) {
			$meta[$m];
			if (isset($meta[$m])) {
				$res[$m] = $meta[$m];
			}
		}
		return $res;
	}
}
