<?php

namespace Drupal\generator_engine\Classes;

use Drupal\generator_engine\Classes\NodesGenerator;
use Drupal\generator_engine\Classes\EntitiesNodesGenerator;
use Drupal\generator_engine\Classes\NodesGeneratorInterface;

/**
 * Default nodes generator used when a bundle declares no custom class.
 */
class EntityOrganizationNodesGenerator extends EntitiesNodesGenerator implements NodesGeneratorInterface {

}
