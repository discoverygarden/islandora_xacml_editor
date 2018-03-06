<?php
namespace Drupal\islandora_xacml_api;

/**
 * Concrete implementation for the rule restricting who can manage an object.
 */
// @codingStandardsIgnoreLine
class XacmlManagementRule extends XacmlRule {

  /**
   * Calls the parent constructor.
   *
   * @param mixed $arg1
   *   Existing Rule datastructure with ID ISLANDORA_XACML_API_MANAGEMENT_RULE
   *   or NULL.
   * @param Xacml $xacml
   *   Reference to the parent XACML object.
   *
   * @note
   *  If $arg1 is NULL instantiates the rule as a new blank rule.
   */
  public function __construct($arg1, Xacml $xacml) {
    parent::__construct($arg1, $xacml);
    $methods_default = [
      'addDatastream',
      'addDisseminator',
      'adminPing',
      'getDisseminatorHistory',
      'getNextPid',
      'ingest',
      'modifyDatastreamByReference',
      'modifyDatastreamByValue',
      'modifyDisseminator',
      'modifyObject',
      'purgeObject',
      'purgeDatastream',
      'purgeDisseminator',
      'setDatastreamState',
      'setDisseminatorState',
      'setDatastreamVersionable',
      'compareDatastreamChecksum',
      'serverShutdown',
      'serverStatus',
      'upload',
      'dsstate',
      'resolveDatastream',
      'reloadPolicies',
    ];
    if ($arg1 == NULL) {
      $this->rule = $this->initializeRule(ISLANDORA_XACML_API_MANAGEMENT_RULE, 'Deny');
      $this->rule['methods'] = $methods_default;
    }
    $this->rule['methods_default'] = $methods_default;
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
    // We pass FALSE because we want to deny management if there is no XACML
    // policy.
    return parent::internalHasPermission($user, $roles, FALSE);
  }

}
