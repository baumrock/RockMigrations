<?php

namespace ProcessWire;

/**
 * Provide access to RockMigrations
 * @return RockMigrations
 */
function rockmigrations(): RockMigrations
{
  return wire()->modules->get('RockMigrations');
}
