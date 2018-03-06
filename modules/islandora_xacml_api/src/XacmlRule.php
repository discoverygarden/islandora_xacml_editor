<?php

namespace Drupal\islandora_xacml_api;

/**
 * This abstract class represents a general XACML Rule.
 *
 * The XACML object contains 4 standard XACML rules, which are all extended
 * from this base class.
 */
abstract class XacmlRule {

  /**
   * Private internal representation of the XACML rule.
   *
   * Containing rules that can be parsed by XacmlWriter
   * and XacmlParser.
   *
   * @var array
   */
  protected $rule;

  /**
   * The XACML object.
   *
   * This points to the XACML object that this rule is instantiated inside of,
   * so that references to other rules can be made.
   *
   * @var Xacml
   */
  protected $xacml;

  /**
   * Initialized a rule datastructure for XacmlWriter.
   *
   * @param string $id
   *   Takes the ID for the new rule as a string.
   * @param string $effect
   *   The effect of the rule (Permit or Deny)
   *
   * @return array
   *   A structure that is parsable by XacmlWriter.
   */
  protected function initializeRule($id, $effect) {
    $rule = [];

    $rule['ruleid'] = $id;
    $rule['effect'] = $effect;

    $rule['dsids'] = [];
    $rule['mimes'] = [];
    $rule['dsidregexs'] = [];
    $rule['mimeregexs'] = [];
    $rule['methods_default'] = [];
    $rule['methods'] = [];
    $rule['users'] = [];
    $rule['roles'] = [];

    return $rule;
  }

  /**
   * Helper function. Allows strings or arrays of strings to be passed in.
   *
   * @param string $type
   *   Array key to modify in internal $rules datastructure.
   * @param mixed $data
   *   Data to be added.
   */
  protected function setValue($type, $data) {
    if (is_array($data)) {
      $this->rule[$type] = array_merge($this->rule[$type], array_values($data));
    }
    else {
      $this->rule[$type][] = $data;
    }
  }

  /**
   * Helper function. Internal arrays may have repeated values, fixes this.
   *
   * @param string $type
   *   Array key in internal datastructure to return.
   *
   * @return array
   *   Array requested.
   */
  protected function getValues($type) {
    return array_unique($this->rule[$type]);
  }

  /**
   * Uses array_diff to remove data from internal rule representation.
   *
   * @todo This could all be made more efficient.
   *
   * @param string $type
   *   Array key to work on.
   * @param mixed $data
   *   Data to be removed.
   */
  protected function removeValues($type, $data) {
    if (!is_array($data)) {
      $data = [$data];
    }

    $this->rule[$type] = array_diff($this->rule[$type], $data);
  }

  /**
   * Constructs new XacmlRule.
   *
   * @param array $arg1
   *   An array containing an pre-exisitng XACML rule or NULL.
   * @param Xacml $xacml
   *   A reference to the XACML dobject that this datastructure is part of.
   *
   * @note
   *   This generic constructor does not set any methods. It assumes if arg1 is
   *   an array that array is an existing XACML rule datastructure. Concrete
   *   implementations should call parent::__construct then initialize the
   *   datastructure correctly if arg1 is NULL by calling
   *   parent::initializeRule() with the proper methods.
   */
  public function __construct(array $arg1, Xacml $xacml) {
    if (is_array($arg1)) {
      $this->rule = $arg1;
      /* remove them now, add them later */
      $this->setValue('users', 'fedoraAdmin');
      $this->setValue('roles', 'administrator');
    }

    $this->xacml = $xacml;
  }

  /**
   * Clear the settings for the given rule.
   *
   * @param mixed $type
   *   Either a type or array of types to clear, or NULL (something which casts
   *   to an empty array) to clear all--except those values which are required--
   *   from the rule array.
   */
  public function clear($type = NULL) {
    $dont_touch = [
      'ruleid',
      'effect',
      'methods',
      'methods_default',
    ];

    $clearable = array_diff(array_keys($this->rule), $dont_touch);
    if (($types = (array) $type) && (count($types) > 0)) {
      $clearable = array_intersect($clearable, $types);
    }

    foreach ($clearable as $to_clear) {
      $this->rule[$to_clear] = [];
    }
  }

  /**
   * Returns true if the rule is populated with data, otherwise returns false.
   *
   * For example a rule can be created that has no users or roles.
   * This rule has no meaning in XACML. We need Users and Roles associated with
   * the rule. This function lets us know if the rule has be populated.
   *
   * @return bool
   *   Whether this XACML is populated.
   */
  public function isPopulated() {
    return $this->getUsers() || $this->getRoles();
  }

  /**
   * Check if the user with the specified username and roles has permission.
   *
   * @param string $user
   *   A string containing the user being tested.
   * @param array $roles
   *   An array of strings containing the roles being tested. Or NULL.
   * @param bool $default
   *   The default value to return.
   */
  protected function internalHasPermission($user, array $roles = NULL, $default = TRUE) {
    // We always allow the administrator role.
    if (in_array('administrator', $roles)) {
      return TRUE;
    }

    // If the rule is not populated we return the default value.
    if (!$this->isPopulated()) {
      return $default;
    }

    // Otherwise we see if they are allowed.
    $boolean_user = in_array($user, $this->getUsers());
    $boolean_role = array_intersect($this->getRoles(), $roles);

    return ($boolean_user || $boolean_role);
  }

  /**
   * Add a user to the XACML rule.
   *
   * @param mixed $user
   *   String or array or strings containing users to add.
   */
  public function addUser($user) {
    $this->setValue('users', $user);
  }

  /**
   * Add roles to the XACML rule.
   *
   * @param mixed $role
   *   String or array of string containing roles to add.
   */
  public function addRole($role) {
    $this->setValue('roles', $role);
  }

  /**
   * Remove users from XACML Rule.
   *
   * @param mixed $user
   *   String or array of strings with users to remove.
   */
  public function removeUser($user) {
    $this->removeValues('users', $user);
  }

  /**
   * Remove roles from XACML rule.
   *
   * @param mixed $role
   *   String or array of string with roles to remove.
   */
  public function removeRole($role) {
    $this->removeValues('roles', $role);
  }

  /**
   * Get users associated with this XACML rule.
   *
   * @return array
   *   Array containing the users.
   */
  public function getUsers() {
    return $this->getValues('users');
  }

  /**
   * Get roles associated with this XACML rule.
   *
   * @return array
   *   Array containing the roles.
   */
  public function getRoles() {
    return $this->getValues('roles');
  }

  /**
   * Return the $rule datastructure associated with this object.
   *
   * @note
   *   This can be parsed by XacmlWriter. While the above functions only give
   *   the users and roles explicitly added to this object, this returns the
   *   datastructure containing all users and roles. It makes sure that the
   *   fedoraAdmin user and administrator role are always added so we don't lock
   *   administrators out of objects.
   *
   * @return array
   *   An array containing the XACML datastructure.
   */
  public function getRuleArray() {
    // Make sure fedoraAdmin can see everything.
    $rule = $this->rule;
    $rule['users'][] = 'fedoraAdmin';
    $rule['roles'][] = 'administrator';
    $rule['users'] = array_unique($rule['users']);
    $rule['roles'] = array_unique($rule['roles']);
    return $rule;
  }

  /**
   * Check if all default methods are set in the rule.
   *
   * @return bool
   *   Whether the rule has all the default methods set or not.
   */
  public function validateDefaultMethods() {
    $return = TRUE;
    if (isset($this->rule['methods_default']) && count($this->rule['methods_default']) > 0) {
      $compare = array_intersect($this->rule['methods_default'], $this->rule['methods']);
      if (count($compare) < count($this->rule['methods_default'])) {
        $return = FALSE;
      }
    }
    return $return;
  }

}
