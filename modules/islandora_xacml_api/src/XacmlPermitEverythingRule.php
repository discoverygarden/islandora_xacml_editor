<?php
namespace Drupal\islandora_xacml_api;

/**
 * Concrete implementaion of a XacmlRule that allows everything.
 *
 * It needs to be added to the end of every XACML policy to allow anything not
 * explicitly forbidden by the policy. Otherwise XACML defaults to
 * denying access.
 *
 * This is entirely managed by Xacml object so not much needs to be said about
 * it.
 */
// @codingStandardsIgnoreLine
class XacmlPermitEverythingRule extends XacmlRule {

  /**
   * Calls the parent constructor.
   *
   * @param Xacml $xacml
   *   A reference to a Xacml object.
   */
  public function __construct(Xacml $xacml) {
    parent::__construct(NULL, $xacml);
    $this->rule = $this->initializeRule(ISLANDORA_XACML_API_PERMIT_RULE, 'Permit');
  }

  /**
   * Retrieves the rule array.
   *
   * @return array
   *   Rule datastructure.
   */
  public function getRuleArray() {
    // Make sure fedoraAdmin can see everything.
    return $this->rule;
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
    return parent::internalHasPermission($user, $roles);
  }

}
