<?php

namespace Drupal\islandora_xacml_editor;

/**
 * Class used in executing queries to Fedora.
 */
class IslandoraXacmlEditorQuery {
  protected $pid;
  protected $query;

  /**
   * Constructorsaurusrex.
   *
   * @param string $pid
   *   The PID of the object using as a base for queries.
   * @param array $query_array
   *   An associative array where the key is the unique ID and contains:
   *   -type: The type of query, either sparql or itql.
   *   -query: The defined query string.
   *   -description: The human-readable description of the query.
   */
  public function __construct($pid, $query_array) {
    $this->pid = $pid;
    $this->query = $query_array;
    $this->result_pids = [];
  }

  /**
   * Executes a defined query.
   *
   * @param array $query_array
   *   An associative array where the key is the unique ID and contains:
   *   -type: The type of query, either sparql or itql.
   *   -query: The defined query string.
   *   -description: The human-readable description of the query.
   * @param string $object_pid
   *   The pid of the object we are using as a base for queries.
   *
   * @return array
   *   An array containing the results of our query.
   */
  public function query($query_array, $object_pid) {
    module_load_include('inc', 'islandora', 'includes/utilities');
    $object = islandora_object_load($object_pid);
    if ($query_array['type'] == 'itql') {
      $content = $object->repository->ri->itqlQuery($query_array['query']);
    }
    else {
      $content = $object->repository->ri->sparqlQuery($query_array['query']);
    }
    foreach ($content as $result) {
      // We need to potentially recurse down even more to cover differing data
      // structure for example books and newspapers.
      $result_pid = $result['object']['value'];
      if (!isset($this->result_pids[$result_pid])) {
        $this->result_pids[$result_pid] = TRUE;
        $restricted_cmodels = [];
        if (isset($query_array['restricted_cmodels'])) {
          $restricted_cmodels = $query_array['restricted_cmodels'];
        }
        $result_object = islandora_object_load($result_pid);
        if ($result_object) {
          $object_models = array_diff($result_object->models, $restricted_cmodels);
          foreach (islandora_build_hook_list('islandora_xacml_editor_child_query', $object_models) as $hook) {
            $query_implementations = \Drupal::moduleHandler()->invokeAll($hook, [$result_object]);
            if (!empty($query_implementations)) {
              foreach ($query_implementations as $query_choice) {
                $this->query($query_choice, $result['object']['value']);
              }
            }
          }
        }
      }
    }
  }

  /**
   * Helper function that retrieves all results of our query.
   * @return array
   *   An array of the PIDs returned from the defiend query.
   */
  public function getPids() {
    IslandoraXacmlEditorQuery::query($this->query, $this->pid);
    $result_pids = array_keys($this->result_pids);
    return $result_pids;
  }
}
