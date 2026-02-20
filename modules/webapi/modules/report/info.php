<?php 

#[Endpoint(name: 'info')]
#[Description('Report info/descriptor (reserved)')]
#[ReturnSchema(schema: 'InfoResult')]
class Info extends EndpointTemplate {
public function process() {
  return null;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('InfoResult', TypeDefs::obj([]));
}
