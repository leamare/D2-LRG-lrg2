<?php

#[\Attribute(\Attribute::TARGET_CLASS)]
class Endpoint {
	public function __construct(
		public string $name,
		public ?string $path = null,
		public bool $isRouter = false
	) {}
}

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class GetParam {
	public function __construct(
		public string $name,
		public bool $required = false,
		public array $schema = [ 'type' => 'string' ],
		public string $description = ''
	) {}
}

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class ModlineVar {
	public function __construct(
		public string $name,
		public array $schema = [ 'type' => 'string' ],
		public string $description = ''
	) {}
}

#[\Attribute(\Attribute::TARGET_CLASS)]
class ReturnSchema {
	public function __construct(
		public array|string $schema = [ 'type' => 'object' ]
	) {}
}

#[\Attribute(\Attribute::TARGET_CLASS)]
class Description {
	public function __construct(
		public string $text
	) {}
}


