<?php
namespace Drupal\islandora_xacml_api;

/**
 * Concrete implementation for the rule restricting who can view an object.
 */
// @codingStandardsIgnoreLine
class XacmlViewingRule extends XacmlRule {

  /**
   * This calls the parent constructor.
   *
   * @param mixed $arg1
   *   Existing Rule datastructure with ID ISLANDORA_XACML_API_VIEWING_RULE or
   *   NULL.
   * @param Xacml $xacml
   *   Reference to the parent XACML object.
   *
   * @note
   *   If $arg1 is NULL instantiates the rule as a new blank rule.
   */
  public function __construct($arg1, Xacml $xacml) {
    parent::__construct($arg1, $xacml);
    $methods_default = [
      'api-a',
      'getDatastreamHistory',
      'listObjectInResourceIndexResults',
    ];
    if ($arg1 == NULL) {
      $this->rule = $this->initializeRule(ISLANDORA_XACML_API_VIEWING_RULE, 'Deny');
      $this->rule['methods'] = $methods_default;
    }
    $this->rule['methods_default'] = $methods_default;
  }

  /**
   * Retrieves the ruleArray and adds roles and users.
   *
   * Calls parent::getRuleArray() and then adds the roles and users from the
   * managementRule and datastreamRule datastructues if they are populated.
   * This ensures that our xacml object works as expected. Otherwise it would
   * be possible to have people that could manage an object but not view
   * datastreams. An unexpected behavior.
   *
   * @return array
   *   Rule datastructure parsable by XacmlWriter.
   */
  public function getRuleArray() {
    $rule = parent::getRuleArray();
    if ($this->xacml->managementRule->isPopulated()) {
      $rule['users'] = array_unique(array_merge($rule['users'], $this->xacml->managementRule->getUsers()));
      $rule['roles'] = array_unique(array_merge($rule['roles'], $this->xacml->managementRule->getRoles()));
    }
    if ($this->xacml->datastreamRule->isPopulated()) {
      $rule['users'] = array_unique(array_merge($rule['users'], $this->xacml->datastreamRule->getUsers()));
      $rule['roles'] = array_unique(array_merge($rule['roles'], $this->xacml->datastreamRule->getRoles()));
    }
    return $rule;
  }

  /**
   * Check if the user with the specified username and roles has permission.
   *
   * @param string $user
   *   A string containing the user being tested.
   * @param mixed $roles
   *   An array of strings containing the roles being tested. Or NULL.
   */
  public function hasPermission($user, $roles = NULL) {
    return parent::internalHasPermission($user, $roles);
  }

}
