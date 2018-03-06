<?php

/**
 * @file
 * Batch functionality for the Islandora XACML Editor.
 */

use Drupal\islandora_xacml_editor\IslandoraUpdatePolicy;
use Drupal\islandora_xacml_editor\IslandoraXacmlEditorQuery;

/**
 * Batch callback function which updates the POLICY for a target pid.
 *
 * @param XML $xml
 *   The XML content defining an XACML policy.
 * @param string $pid
 *   The PID of the object we are going to update.
 * @param array $query_array
 *   An associative array where the key is the unique ID and contains:
 *   -type: The type of query, either sparql or itql.
 *   -query: The defined query string.
 *   -description: The human-readable description of the query.
 * @param array $context
 *   Context array used in the batch.
 */
function islandora_xacml_editor_batch_function($xml, $pid, $query_array, &$context) {
  if (empty($context['sandbox'])) {
    $query = new IslandoraXacmlEditorQuery($pid, $query_array);
    $context['sandbox'] = [];
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['pids'] = $query->getPids();
    $context['sandbox']['items'] = count($context['sandbox']['pids']);
    $context['results']['redirect'] = $pid;
    $context['results']['success'] = [];
    $context['results']['fail'] = [];
  }
  $targetpid = array_pop($context['sandbox']['pids']);
  $context['sandbox']['progress']++;

  $policy_update = new IslandoraUpdatePolicy($targetpid, $xml);
  $success = $policy_update->updatePolicy();

  if ($success) {
    $context['results']['success'][] = $targetpid;
  }
  else {
    $context['results']['fail'][] = $targetpid;
  }

  // Make sure we don't divide by zero.
  $context['finished'] = $context['sandbox']['items'] == 0 ? 1 : $context['sandbox']['progress'] / $context['sandbox']['items'];
}

/**
 * Finished function for the update policy batch.
 *
 * @param bool $success
 *   Whether the batch was successful or not.
 * @param array $results
 *   An array containing the results of the batch operations.
 * @param array $operations
 *   The operations array that was used in the batch.
 */
function islandora_xacml_editor_batch_finished($success, $results, $operations) {
  if ($success) {
    $message = \Drupal::translation()->formatPlural(count($results['success']), 'One policy updated.', '@count policies updated.');
  }
  else {
    $message = t('Finished with an error.');
  }
  drupal_set_message($message);

  if ($results['fail']) {
    foreach ($results['fail'] as $fail) {
      drupal_set_message(t("Failed to update: @failed_object. You do not have permission to update this object.", ['@failed_object' => $fail]), 'error');
    }
  }
}
