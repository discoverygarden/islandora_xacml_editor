<?php

/**
 * @file
 * Autocomplete functionality for the Islandora XACML Editor.
 */

/**
 * Retrieves the MIME Types from the DS-COMPOSITE for use in autocomplete.
 *
 * @param array $models
 *   An array of content models.
 *
 * @return array
 *   An array of MIME types.
 */
function islandora_xacml_editor_retrieve_mimes(array $models) {
  module_load_include('inc', 'islandora', 'includes/utilities');

  $mimes = [];
  $datastreams = islandora_get_datastreams_requirements_from_models($models);

  foreach ($datastreams as $value) {
    foreach ($value['mime'] as $mime) {
      if (!in_array($mime, $mimes)) {
        $mimes[$mime] = $value['mime'];
      }
    }
  }
  return $mimes;
}
