<?php 

#[Endpoint(name: 'matchcards')]
#[Description('Return match cards for provided match ids')]
#[GetParam(name: 'gets', required: true, schema: ['type' => 'array','items' => ['type' => 'integer']], description: 'Match ids')]
#[ReturnSchema(schema: 'MatchcardsResult')]
class Matchcards extends EndpointTemplate {
public function process() {
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  $res = [];
  foreach($vars['gets'] as $m) {
    if (isset($report['matches_additional'][$m]))
      $res[] = match_card($m);
  }
  return $res;
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('MatchcardsResult', TypeDefs::arrayOf(TypeDefs::obj([])));
}
