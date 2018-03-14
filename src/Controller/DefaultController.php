<?php

namespace Drupal\islandora_xacml_editor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Html;

/**
 * Default controller for the islandora_xacml_editor module.
 */
class DefaultController extends ControllerBase {

  /**
   * Callback that performs autocomplete operations.
   */
  public function dsidAutocomplete($pid, $string) {
    $object = islandora_object_load($pid);
    $dsids = [];

    foreach ($object as $datastream) {
      if ($string != '*') {
        if (strpos(Unicode::strtoupper($datastream->id), Unicode::strtoupper($string)) !== FALSE) {
          $dsids[$datastream->id] = Html::escape($datastream->id);
        }
      }
      else {
        $dsids[$datastream->id] = Html::escape($datastream->id);
      }
    }
    $restricted_dsids = $this->config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_restricted_dsids');
    $restricted_dsids = preg_split('/[\s,]+/', $restricted_dsids);

    $dsids = array_diff($dsids, $restricted_dsids);

    $output = [];
    foreach ($dsids as $dsid => $escaped_dsid) {
      $output[] = ['value' => $dsid, 'label' => $escaped_dsid];
    }
    return new JsonResponse($output);
  }

  /**
   * Callback that performs autocomplete operations.
   */
  public function mimeAutocomplete($pid, $string) {
    module_load_include('inc', 'islandora', 'includes/utilities');

    $mimes = [];
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
        if (strpos(Unicode::strtoupper($key), Unicode::strtoupper($string)) !== FALSE) {
          $mimes[$key] = Html::escape($key);
        }
      }
      else {
        $mimes[$key] = Html::escape($key);
      }
    }
    $restricted_mimes = $this->config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_restricted_mimes');
    $restricted_mimes = preg_split('/[\s,]+/', $restricted_mimes);

    $mimes = array_diff($mimes, $restricted_mimes);
    $output = [];
    foreach ($mimes as $mime => $escaped_mime) {
      $output[] = ['value' => $mime, 'label' => $escaped_mime];
    }

    return new JsonResponse($output);
  }

}
