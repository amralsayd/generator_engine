<?php

namespace Drupal\generator_engine\Classes\Entities;

use Drupal\generator_engine\Classes\EntitiesNodesGenerator;
use Drupal\generator_engine\Classes\NodesGeneratorInterface;

/**
 * Generates user entities.
 *
 * Registered as the "user" entity type's nodes generator by
 * HelpersService::getEntitiesConfigFieldsMain(). The base implementation is
 * sufficient; per-account work happens in EntityUserSingleGenerator.
 */
class EntityUsersGenerator extends EntitiesNodesGenerator implements NodesGeneratorInterface {

}
