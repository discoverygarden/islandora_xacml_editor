<?php
/**
 * @file
 * Autocomplete functionality for the Islandora XACML Editor.
 */

/**
 * Callback that performs autocomplete operations.
 */
function islandora_xacml_editor_dsid_autocomplete($pid, $string) {
  $object = islandora_object_load($pid);
  $output = array();

  foreach ($object as $datastream) {
    if ($string != '*') {
      if (strpos(\Drupal\Component\Utility\Unicode::strtoupper($datastream->id), \Drupal\Component\Utility\Unicode::strtoupper($string)) !== FALSE) {
        $output[$datastream->id] = \Drupal\Component\Utility\Html::escape($datastream->id);
      }
    }
    else {
      $output[$datastream->id] = \Drupal\Component\Utility\Html::escape($datastream->id);
    }
  }
  $restricted_dsids = \Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_restricted_dsids');
  $restricted_dsids = preg_split('/[\s,]+/', $restricted_dsids);

  $output = array_diff($output, $restricted_dsids);

  drupal_json_output($output);
}

/**
 * Callback that performs autocomplete operations.
 */
function islandora_xacml_editor_mime_autocomplete($pid, $string) {
  module_load_include('inc', 'islandora', 'includes/utilities');

  $output = array();
  $object = islandora_object_load($pid);

  if ($object['COLLECTION_POLICY']) {
    $collection_policy = new CollectionPolicy($object['COLLECTION_POLICY']->content);
    $collection_models = array_keys($collection_policy->getContentModels());
    $mime = islandora_xacml_editor_retrieve_mimes($collection_models);
  }
  else {
    $mime = islandora_xacml_editor_retrieve_mimes($object->models);
  }
  foreach ($mime as $key => $value) {
    if ($string != "*") {
      if (strpos(\Drupal\Component\Utility\Unicode::strtoupper($key), \Drupal\Component\Utility\Unicode::strtoupper($string)) !== FALSE) {
        $output[$key] = \Drupal\Component\Utility\Html::escape($key);
      }
    }
    else {
      $output[$key] = \Drupal\Component\Utility\Html::escape($key);
    }
  }
  $restricted_mimes = \Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_restricted_mimes');
  $restricted_mimes = preg_split('/[\s,]+/', $restricted_mimes);

  $output = array_diff($output, $restricted_mimes);

  drupal_json_output($output);

}

/**
 * Retrieves the MIME Types from the DS-COMPOSITE for use in autocomplete.
 *
 * @param array $models
 *   An array of content models.
 *
 * @return array
 *   An array of MIME types.
 */
function islandora_xacml_editor_retrieve_mimes($models) {
  module_load_include('inc', 'islandora', 'includes/utilities');

  $mimes = array();
  $datastreams = islandora_get_datastreams_requirements_from_models($models);

  foreach ($datastreams as $key => $value) {
    foreach ($value['mime'] as $mime) {
      if (!in_array($mime, $mimes)) {
        $mimes[$mime] = $value['mime'];
      }
    }
  }
  return $mimes;
}
