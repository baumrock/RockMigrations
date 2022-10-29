<?php

namespace ProcessWire;

trait RockMigrationsApiTrait
{
  public function __call($method, $args)
  {
    if (method_exists($this, $method)) return $this->$method(...$args);
    return parent::__call($method, $args);
  }
}
