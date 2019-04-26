<?php

namespace Drupal\islandora_xacml_editor\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class IslandoraXacmlEditorCommands extends DrushCommands {

  /**
   * Module handler, for hook calls.
   *
   * @var Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructor.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * Apply XACML policy to target object.
   *
   * @option policy
   *   The path to an XML file containing the XACML policy configuration to be
   *   applied. It is expected that this policy file be generated from the UI's
   *   XACML Editor.
   * @option pid
   *   The PID of the object to apply the policy configuration to.
   * @option traversal
   *   Optional. When enabled, the policy configuration will be applied to the
   *   target object's children (shallow traversal is not supported for
   *   collection objects). Disabled by default.
   * @usage drush -v --user=1 islandora_xacml_editor_apply_policy --policy=/tmp/policy.xml --pid=islandora:57 --traversal
   *   Apply policy.xml to 'islandora:57' and use traversal to target child
   *   objects.
   *
   * @command islandora_xacml_editor:apply-policy
   * @aliases ixeap,islandora_xacml_editor_apply_policy
   *
   * @islandora-user-wrap
   * @islandora-require-option pid
   * @validate-module-enabled islandora_xacml_editor
   */
  public function islandoraXacmlEditorApplyPolicy(array $options = [
    'policy' => NULL,
    'pid' => NULL,
    'traversal' => NULL,
  ]) {
    module_load_include('inc', 'islandora', 'includes/utilities');

    $object = islandora_object_load($options['pid']);

    if (!$object) {
      throw new \Exception(dt('An error occurred while trying to load \'@pid\'.', [
        '@pid' => $options['pid'],
      ]));
    }

    $policy = $options['policy'];

    if (file_exists($policy) && is_file($policy)) {
      $xml = file_get_contents($policy);

      if (!$xml) {
        throw new \Exception(dt('Could not read policy file from @policy', ['@policy' => $policy]));
      }
    }
    else {
      throw new \Exception(dt('File path provided does not exist or is not a file.'));
    }

    $traversal = $options['traversal'];

    if ($traversal) {
      $query_array = [];

      foreach (islandora_build_hook_list('islandora_xacml_editor_child_query', $object->models) as $hook) {
        $temp = $this->moduleHandler->invokeAll($hook, [$object]);

        if (!empty($temp)) {
          $query_array = array_merge_recursive($query_array, $temp);
          // Need to reset the array as we expect only one query.
          $query_array = reset($query_array);
        }
      }

      if (empty($query_array)) {
        $this->logger()->notice('No child queries found, skipping traversal...');
      }

      $this->batch($xml, $object->id, $query_array);
    }
    else {
      module_load_include('inc', 'islandora_xacml_editor', 'includes/batch');

      $policy_update = new IslandoraUpdatePolicy($object->id, $xml);
      $success = $policy_update->updatePolicy();

      if (!$success) {
        throw new \Exception(dt('An error occurred while trying to update the \'POLICY\' datastream for @pid.', ['@pid' => $object->id]));
      }

      $this->output()->writeln(dt('The \'POLICY\' configuration has been applied to @pid!', ['@pid' => $object->id]));
    }
  }

  /**
   * Force all child objects to inherit an object's XACML policy configuration.
   *
   * @option pid
   *   The PID of the parent object. Must have a 'POLICY' datastream.
   * @option shallow_traversal
   *   Optional. If the target object is a collection, use shallow traversal to
   *   target only the immediate children. Disabled by default.
   * @usage drush -v --user=1 islandora_xacml_editor_force_policy_inheritance --pid=islandora:root --shallow_traversal
   *   Enforce policy inheritance to all immediate children of 'islandora:root'
   *   object.
   *
   * @command islandora_xacml_editor:force-policy-inheritance
   * @aliases ixefpi,islandora_xacml_editor_force_policy_inheritance
   *
   * @islandora-user-wrap
   * @islandora-require-option pid
   * @validate-module-enabled islandora_xacml_editor
   */
  public function islandoraXacmlEditorForcePolicyInheritance(array $options = [
    'pid' => self::REQ,
    'shallow_traversal' => FALSE,
  ]) {
    module_load_include('inc', 'islandora', 'includes/utilities');

    $object = islandora_object_load($options['pid']);

    if (!$object) {
      throw new \Exception(dt('An error occurred while trying to load \'@pid\'.', ['@pid' => $options['pid']]));
    }

    if ($object['POLICY']) {
      $xml = $object['POLICY']->content;

      if (!$xml) {
        throw new \Exception(dt('An error occurred while trying to load the \'POLICY\' datastream for @pid.', ['@pid' => $object->id]));
      }
    }
    else {
      throw new \Exception(dt('No \'POLICY\' datastream found for @pid.', ['@pid' => $object->id]));
    }

    $query_array = [];

    foreach (islandora_build_hook_list('islandora_xacml_editor_child_query', $object->models) as $hook) {
      $temp = $this->moduleHandler->invokeAll($hook, [$object]);

      if (!empty($temp)) {
        $query_array = array_merge_recursive($query_array, $temp);
        // If shallow traversal is enabled and the content models child query
        // found targets all children, add the shallow restriction to the query.
        if (isset($query_array['all_children']) && $options['shallow_traversal']) {
          $query_array['all_children']['restricted_cmodels'] = ['islandora:collectionCModel'];
        }
      }
    }

    if (empty($query_array)) {
      throw new \Exception(dt('No child query found for object\'s content model.'));
    }

    $this->batch($xml, $object->id, reset($query_array));
  }

  /**
   * Build and process batch operation.
   *
   * @param string $xml
   *   String containing the XACML policy configuration markup.
   * @param string $pid
   *   The PID of the target object.
   * @param array $query_array
   *   Array containing query child query, type and description.
   */
  protected function batch($xml, $pid, array $query_array) {
    $batch = [
      'operations' => [
        [
          'islandora_xacml_editor_batch_function',
          [$xml, $pid, $query_array],
        ],
      ],
      'finished' => 'islandora_xacml_editor_batch_finished',
      'file' => drupal_get_path('module', 'islandora_xacml_editor') . '/includes/batch.inc',
    ];

    $this->output()->writeln(dt('Please wait if many objects are being updated as this could take a few minutes.'));

    batch_set($batch);
    drush_backend_batch_process();
  }

}
