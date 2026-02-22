<?php 

#[Endpoint(name: 'list')]
#[Description('List reports, filterable by category')]
#[GetParam(name: 'cat', required: false, schema: ['type' => 'string'], description: 'Category key')]
#[ReturnSchema(schema: [
  'type' => 'object',
  'properties' => [
    'cat_selected' => ['type' => 'string'],
    'cat_list' => ['type' => 'array','items' => ['type' => 'string']],
    'count' => ['type' => 'integer'],
    'reports' => [
      'type' => 'object',
      'additionalProperties' => [
        'type' => 'object',
        'properties' => [
          'tag' => ['type' => 'string'],
          'name' => ['type' => 'string'],
          'desc' => ['type' => 'string'],
          'matches' => ['type' => 'integer'],
          'last_match' => ['type' => 'object','properties' => [
            'date' => ['type' => 'integer'],
            'mid' => ['type' => 'integer']
          ]],
          'versions' => ['type' => 'array','items' => ['type' => 'integer']],
          'tvt' => ['type' => 'boolean'],
          'last_update' => ['type' => 'integer']
        ],
        'required' => ['tag','name']
      ]
    ],
    'cat_groups' => ['type' => 'object'],
    'featured' => ['type' => 'array','items' => ['type' => 'string']]
  ]
])]
class ListEndpoint extends EndpointTemplate {
	public function process() {
		global $endpoints, $cat, $cats_file, $hidden_cat, $cats_groups_priority, $cats_groups_names, $cats_groups_icons, $cats_groups_hidden, $__featured_cats;
		$cache = $endpoints['getcache']($this->mods, $this->vars, $this->report);
		$reps = [];

		if (file_exists($cats_file)) {
			$cats = file_get_contents($cats_file);
			$cats = json_decode($cats, true);
		} else {
			$cats = [];
		}
		
		if (empty($cat)) $cat = "main";

		if(!empty($cats)) {
			if (isset($cats[$cat])) {
				$reps = [];
				foreach($cache["reps"] as $tag => $rep) {
					if(check_filters($rep, $cats[$cat]['filters'])) {
						if (($cats[$cat]['exclude_hidden'] ?? true) && isset($cats[$hidden_cat])) {
							if(check_filters($rep, $cats[$hidden_cat]['filters'])) {
								continue;
							}
						}
						$reps[$tag] = $rep;
					}
				}

				if (isset($cats[$cat]['orderby'])) {
					$orderby = $cats[$cat]['orderby'];
					uasort($reps, function($a, $b) use (&$orderby) {
						$res = 0;
						foreach ($orderby as $k => $dir) {
							$res = $dir ? (($b[$k] ?? 0) <=> ($a[$k] ?? 0)) : (($a[$k] ?? 0) <=> ($b[$k] ?? 0));
							if ($res) break;
						}
						
						return $res;
					});
				}
			} else if($cat == "main") {
				if(isset($cats[$hidden_cat])) {
					foreach($cache["reps"] as $tag => $rep) {
						if(!check_filters($rep, $cats[$hidden_cat]['filters']))
							$reps[$tag] = $rep;
					}
				} else {
					$reps = $cache["reps"];
				}
			} else if ($cat == "all") {
				$reps = $cache["reps"];
			} else {
				throw new UserInputException("No such category.");
			}
		} else {
			$reps = $cache["reps"];
		}

		if(isset($cats[$hidden_cat])) unset($cats[$hidden_cat]);
		$cats_list = array_keys($cats);
		$cats_list[] = "all";
		$cats_list[] = "main";
		$cats_list[] = "recent";

		return [
			"cat_selected" => $cat,
			"cat_list" => $cats_list,
			"count" => count($reps),
			"reports" => $reps,
			"cat_groups" => [
				"priority" => $cats_groups_priority ?? [],
				"names" => $cats_groups_names ?? [],
				"icons" => $cats_groups_icons ?? [],
				"hidden" => $cats_groups_hidden ?? []
			],
			"featured" => $__featured_cats ?? []
		];
	}
}
