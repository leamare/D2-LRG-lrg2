<?php

#[\Attribute(\Attribute::TARGET_CLASS)]
class Endpoint {
	public $name;
	public $path;
	public $isRouter;
	public function __construct($name, $path = null, $isRouter = false) {
		$this->name = $name;
		$this->path = $path;
		$this->isRouter = $isRouter;
	}
}

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class GetParam {
	public $name;
	public $required;
	public $schema;
	public $description;
	public function __construct($name, $required = false, $schema = [ 'type' => 'string' ], $description = '') {
		$this->name = $name;
		$this->required = $required;
		$this->schema = $schema;
		$this->description = $description;
	}
}

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class ModlineVar {
	public $name;
	public $schema;
	public $description;
	public function __construct($name, $schema = [ 'type' => 'string' ], $description = '') {
		$this->name = $name;
		$this->schema = $schema;
		$this->description = $description;
	}
}

#[\Attribute(\Attribute::TARGET_CLASS)]
class ReturnSchema {
	public $schema;
	public function __construct($schema = [ 'type' => 'object' ]) {
		$this->schema = $schema;
	}
}

#[\Attribute(\Attribute::TARGET_CLASS)]
class Description {
	public $text;
	public function __construct($text) {
		$this->text = $text;
	}
}


