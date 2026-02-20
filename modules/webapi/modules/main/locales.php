<?php 

#[Endpoint(name: 'locales')]
#[Description('Load and return locales by keys')]
#[GetParam(name: 'gets', required: true, schema: ['type' => 'array','items' => ['type' => 'string']], description: 'Locales to load')]
#[ReturnSchema(schema: 'LocalesResult')]
class Locales extends EndpointTemplate {
public function process() {
  global $strings;
  if (empty($this->vars['gets'])) return null;
  foreach ($this->vars['gets'] as $loc) {
    include_locale($loc);
  }
  return $strings;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('LocalesResult', TypeDefs::mapOf(TypeDefs::obj([])));
}
