<?php

namespace Drupal\islandora_xacml_editor;

use Drupal\islandora_xacml_api\IslandoraXacml;
use Drupal\islandora_xacml_api\XacmlException;

/**
 * Class used in the batch updating of POLICY datastreams on objects.
 */
class IslandoraUpdatePolicy {
  protected $pid;

  /**
   * Constructorsaurusrex.
   *
   * @param string $pid
   *   The pid of the object we are batching.
   * @param XML $xml
   *   The XACML XML.
   */
  public function __construct($pid, $xml) {
    // Used at a couple different points...  Let's just load this here?
    $this->pid = $pid;
    $this->xml = $xml;
    $this->object = islandora_object_load($pid);
  }

  /**
   * Updates the POLICY datastream of the object.
   *
   * @return bool
   *   The success of the operation.
   */
  public function updatePolicy() {
    $user = \Drupal::currentUser();
    $success = FALSE;
    if (isset($this->object)) {
      try {
        $xacml = new IslandoraXacml($this->object);
        if (!isset($this->object['POLICY']) || !$xacml->managementRule->isPopulated() || $xacml->managementRule->hasPermission($user->name, $user->roles)) {
          $success = $this->addOrUpdateAllPolicies();
        }
      }
      catch (XacmlException $e) {
      }
    }
    return $success;
  }

  /**
   * Updates or adds the new POLICY datastream back to the object.
   */
  protected function addOrUpdateAllPolicies() {
    $object_policy = new IslandoraXacml($this->object, $this->xml);
    $object_policy->writeBackToFedora();
    return TRUE;
  }
}
