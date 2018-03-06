<?php
namespace Drupal\islandora_xacml_api;

/**
 * A concrete implementation to restrict certain mimetypes and datastreams.
 */
// @codingStandardsIgnoreLine
class XacmlDatastreamRule extends XacmlRule {

  /**
   * Initialized the rule.
   *
   * @param mixed $arg1
   *   NULL or an existing $rule array with ID
   *   ISLANDORA_XACML_API_DATASTREAM_RULE.
   * @param Xacml $xacml
   *   Reference to parent Xacml object.
   */
  public function __construct($arg1, Xacml $xacml) {
    parent::__construct($arg1, $xacml);
    $methods_default = [
      'getDatastreamDissemination',
    ];
    if ($arg1 == NULL) {
      $this->rule = $this->initializeRule(ISLANDORA_XACML_API_DATASTREAM_RULE, 'Deny');
      $this->rule['methods'] = $methods_default;
    }
    $this->rule['methods_default'] = $methods_default;
  }

  /**
   * Retrieves the ruleArray and adds roles and users.
   *
   * Calls parent::getRuleArray() and then adds the roles and users fromt the
   * managementRule object if they are populated. This ensures that our XACML
   * object works as expected. Otherwise it would be possible to have people
   * that could manage an object but not view datastreams.
   * An unexpected behavior.
   *
   * @return array
   *   Rule datastructure parsable by XacmlWriter.
   */
  public function getRuleArray() {
    $rule = parent::getRuleArray();
    $rule['dsids'] = $this->getValues('dsids');
    $rule['mimes'] = $this->getValues('mimes');
    $rule['dsidregexs'] = $this->getValues('dsidregexs');
    $rule['mimeregexs'] = $this->getValues('mimeregexs');

    if ($this->xacml->managementRule->isPopulated()) {
      $rule['users'] = array_unique(array_merge($rule['users'], $this->xacml->managementRule->getUsers()));
      $rule['roles'] = array_unique(array_merge($rule['roles'], $this->xacml->managementRule->getRoles()));
    }

    return $rule;
  }

  /**
   * Add a dsid to the rule.
   *
   * @param mixed $dsid
   *   String or array of strings containing the datastream to add.
   */
  public function addDsid($dsid) {
    $this->setValue('dsids', $dsid);
  }

  /**
   * Add a dsid regex to the rule.
   *
   * @param mixed $regex
   *   String or array of strings containing the datastream to add.
   */
  public function addDsidRegex($regex) {
    $this->setValue('dsidregexs', $regex);
  }

  /**
   * Add a mimetype to the rule.
   *
   * @param mixed $mime
   *   String or array of strings to add to the rule.
   */
  public function addMimetype($mime) {
    $this->setValue('mimes', $mime);
  }

  /**
   * Add a mimetype regex to the rule.
   *
   * @param mixed $regex
   *   String or array of strings to add to the rule.
   */
  public function addMimetypeRegex($regex) {
    $this->setValue('mimeregexs', $regex);
  }

  /**
   * Remove mimetypes from the rule.
   *
   * @param mixed $mime
   *   String or array ofs tring to remove from the rule.
   */
  public function removeMimetype($mime) {
    $this->removeValues('mimes', $mime);
  }

  /**
   * Remove mimetype regexs from the rule.
   *
   * @param mixed $regex
   *   String or array ofs tring to remove from the rule.
   */
  public function removeMimetypeRegex($regex) {
    $this->removeValues('mimeregexs', $regex);
  }

  /**
   * Remove dsids from the rule.
   *
   * @param mixed $dsid
   *   String or array of strings to remove from the rule.
   */
  public function removeDsid($dsid) {
    $this->removeValues('dsids', $dsid);
  }

  /**
   * Remove dsid regexs from the rule.
   *
   * @param mixed $regex
   *   String or array ofs tring to remove from the rule.
   */
  public function removeDsidRegex($regex) {
    $this->removeValues('dsidregexs', $regex);
  }

  /**
   * Mimetypes associated with this rule.
   *
   * @return array
   *   Array of mimetypes.
   */
  public function getMimetypes() {
    return $this->getValues('mimes');
  }

  /**
   * Mimetypes associated with this rule.
   *
   * @return array
   *   Array of mimetype regexs.
   */
  public function getMimetypeRegexs() {
    return $this->getValues('mimeregexs');
  }

  /**
   * Dsids associated with this rule.
   *
   * @return array
   *   Array of dsids.
   */
  public function getDsids() {
    return $this->getValues('dsids');
  }

  /**
   * Dsid regexs associated with this rule.
   *
   * @return array
   *   Array of dsid regexs.
   */
  public function getDsidRegexs() {
    return $this->getValues('dsidregexs');
  }

  /**
   * Returns TRUE if the rule is populated with data, otherwise returns FALSE.
   *
   * For example a rule can be created that has no users, roles, dsids or
   * mimetypes. This makes sure there is at least on role or user and at least
   * one mimtype or dsid.
   *
   * @return bool
   *   Whether the rule is populated or not.
   */
  public function isPopulated() {
    return parent::isPopulated() && ($this->getMimetypes() || $this->getDsids() || $this->getDsidRegexs() || $this->getMimetypeRegexs());
  }

  /**
   * Check if the user with the specified username and roles has permission.
   *
   * @param string $user
   *   A string containing the user being tested.
   * @param mixed $roles
   *   An array of strings containing the roles being tested. Or NULL.
   * @param string $mime
   *   String containing the mime.
   * @param string $dsid
   *   String containing the DSID.
   *
   * @return bool
   *   Whether the user has permission or not.
   */
  public function hasPermission($user, $roles, $mime, $dsid) {
    // We need to check the isPopulated function for this one because it is
    // overridden.
    if (!$this->isPopulated()) {
      return TRUE;
    }
    if (!parent::internalHasPermission($user, $roles)) {
      $boolean_mime = $mime ? in_array($mime, $this->getMimetypes()) : FALSE;
      $boolean_dsid = $dsid ? in_array($dsid, $this->getDsids()) : FALSE;
      $boolean_mimeregex = FALSE;
      $boolean_dsidregex = FALSE;

      $mimeregexs = $this->getMimetypeRegexs();

      if (isset($mimeregexs)) {
        foreach ($mimeregexs as $value) {
          preg_match('/' . $value . '/', $mime, $match);

          if (count($match) > 0) {
            $boolean_mimeregex = TRUE;
            break;
          }
        }
      }

      $dsidregexs = $this->getDsidRegexs();

      if (isset($dsidregexs)) {
        foreach ($dsidregexs as $value) {
          preg_match('/' . $value . '/', $dsid, $match);

          if (count($match) > 0) {
            $boolean_dsidregex = TRUE;
            break;
          }
        }
      }

      if ($boolean_mime || $boolean_dsid || $boolean_mimeregex || $boolean_dsidregex) {
        return FALSE;
      }
      else {
        return TRUE;
      }
    }
    return TRUE;
  }

}
