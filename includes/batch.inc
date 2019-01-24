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
 * @param string $xml
 *   The XML content defining an XACML policy.
 * @param string $pid
 *   The PID of the object we are going to update.
 * @param array $query_array
 *   An associative array where the key is the unique ID and contains:
 *   -type: The type of query, either sparql or itql.
 *   -query: The defined query string.
 *   -description: The human-readable description of the query.
 * @param DrushBatchContext|array $context
 *   Context array used in the batch.
 */
function islandora_xacml_editor_batch_function($xml, $pid, array $query_array, &$context) {
  $sandbox = &$context['sandbox'];
  if (!isset($sandbox['pids'])) {
    $query = new IslandoraXacmlEditorQuery($pid, $query_array);
    $sandbox['progress'] = 0;
    $sandbox['pids'] = $query->getPids();
    $sandbox['items'] = count($context['sandbox']['pids']);
    $context['results']['redirect'] = $pid;
    $context['results']['success'] = [];
    $context['results']['fail'] = [];
  }
  if ($sandbox['items'] == 0) {
    // If no items are found exit the operation.
    $context['finished'] = 1;
    return;
  }

  $sandbox['progress']++;
  $targetpid = array_pop($sandbox['pids']);

  $policy_update = new IslandoraUpdatePolicy($targetpid, $xml);
  $success = $policy_update->updatePolicy();

  if ($success) {
    $context['results']['success'][] = $targetpid;
  }
  else {
    $context['results']['fail'][] = $targetpid;
  }

  $context['finished'] = $sandbox['progress'] / $sandbox['items'];
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
function islandora_xacml_editor_batch_finished($success, array $results, array $operations) {
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
