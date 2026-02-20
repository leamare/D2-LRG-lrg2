<?php 

#[Endpoint(name: 'tickets')]
#[Description('List of league tickets (Dota leagues) that matches in this report belong to, sorted by match count')]
#[ReturnSchema(schema: 'TicketsResult')]
class Tickets extends EndpointTemplate {
  public function process() {
    $mods = $this->mods; $vars = $this->vars; $report = $this->report; global $meta;
    if (empty($report['tickets'])) 
      return [];

    $res = [];
    
    foreach ($report['tickets'] as $lid => $data) {
      $league_name = "none";
      if ($lid && $lid > 0) {
        if (!empty($data['name'])) {
          $league_name = $data['name'];
        } else {
          $league_name = "League #" . $lid;
        }
      }
      
      $item = [
        'lid' => $lid,
        'matches' => $data['matches'],
        'league_name' => $league_name,
      ];
      
      if (!empty($data['url'])) {
        $item['url'] = $data['url'];
      }
      if (!empty($data['description'])) {
        $item['description'] = $data['description'];
      }
      
      $res[] = $item;
    }

    usort($res, function($a, $b) {
      return $b['matches'] <=> $a['matches'];
    });

    return $res;
  }
}

if (is_docs_mode()) {
  SchemaRegistry::register('TicketEntry', TypeDefs::obj([
    'lid' => TypeDefs::int(),
    'matches' => TypeDefs::int(),
    'league_name' => TypeDefs::str(),
    'url' => TypeDefs::str(),
    'description' => TypeDefs::str(),
  ]));

  SchemaRegistry::register('TicketsResult', TypeDefs::arrayOf('TicketEntry'));
}