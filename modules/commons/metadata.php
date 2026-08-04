<?php

class lrg_metadata implements ArrayAccess {
  private $metadata = [];
  private $dir;

  public function __construct($dir = "metadata") {
    $this->dir = $dir;
  }

  private function load($metafile) {
    if (!file_exists("{$this->dir}/$metafile.json"))
      throw new UserInputException("wrong metadata endpoint"."-- {$this->dir}/$metafile.json");
    $content = file_get_contents("{$this->dir}/$metafile.json");
    $decoded = json_decode($content, true);
    if (!is_array($decoded)) {
      throw new UserInputException("invalid metadata JSON"."-- {$this->dir}/$metafile.json");
    }
    $this->metadata[ $metafile ] = $decoded;
  }

  public function & get($metafile) {
    if (!isset($this->metadata[ $metafile ]))
      $this->load($metafile);

    return $this->metadata[ $metafile ];
  }

  public function & __get($name) {
    return $this->get($name);
  }


  public function offsetSet($offset, $value): void {
      $this->get($offset);
  }

  public function offsetExists($offset): bool {
      if (isset($this->metadata[$offset])) return true;
      return file_exists("{$this->dir}/$offset.json");
  }

  public function offsetUnset($offset): void {
      unset($this->metadata[$offset]);
  }

  public function & offsetGet($offset): array {
      return $this->get($offset);
  }
}
