<?php 

#[Endpoint(name: 'search')]
#[Description('Search reports by query across cache')]
#[GetParam(name: 'search', required: true, schema: ['type' => 'string'], description: 'Query string')]
#[ReturnSchema(schema: 'SearchResult')]
class SearchEndpoint extends EndpointTemplate {
public function process() {
  global $endpoints, $cat, $cats_file, $hidden_cat;
  $mods = $this->mods; $vars = $this->vars; $report = $this->report;
  $cache = $endpoints['getcache']($mods, $vars, $report);
  $reps = [];

  if (file_exists($cats_file)) {
    $cats = file_get_contents($cats_file);
    $cats = json_decode($cats, true);
  } else {
    $cats = [];
  }

  $searchfilter = create_search_filters($vars['search']);

  $reps = [];
  foreach($cache["reps"] as $tag => $rep) {
    if(check_filters($rep, $searchfilter))
      $reps[$tag] = $rep;
  }

  return [
    "query" => $vars['search'],
    "reports" => $reps
  ];
}
}

if (is_docs_mode()) {
  SchemaRegistry::register('SearchResult', TypeDefs::obj([
    'query' => TypeDefs::str(),
    'reports' => TypeDefs::mapOfIdKeys(TypeDefs::obj([])),
  ]));
}
