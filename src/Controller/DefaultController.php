<?php

namespace Drupal\islandora_xacml_editor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;

use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Cache\CacheableJsonResponse as JsonResponse;

use Drupal\islandora_basic_collection\CollectionPolicy;
use Drupal\islandora\Controller\DefaultController as IslandoraController;

use AbstractObject;

/**
 * Default controller for the islandora_xacml_editor module.
 */
class DefaultController extends ControllerBase {

  /**
   * Callback that performs autocomplete operations.
   */
  public function dsidAutocomplete(AbstractObject $object, Request $request) {
    $string = $request->query->get('q');
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

    $response = new JsonResponse($output);

    $response->getCacheableMetadata()
      ->addCacheableDependency($object)
      ->addCacheableDependency($this->config('islandora_xacml_editor.settings'))
      ->addCacheContexts([
        'url.query_args:q',
      ]);

    return $response;
  }

  /**
   * Callback that performs autocomplete operations.
   */
  public function mimeAutocomplete(AbstractObject $object, Request $request) {
    module_load_include('inc', 'islandora_xacml_editor', 'includes/autocomplete');

    $cache_meta = new CacheableMetadata();

    $string = $request->query->get('q');
    $mimes = [];
    if ($object['COLLECTION_POLICY']) {
      $collection_policy = new CollectionPolicy($object['COLLECTION_POLICY']->content);
      $collection_models = array_keys($collection_policy->getContentModels());
      $mime = islandora_xacml_editor_retrieve_mimes($collection_models);
      $cache_meta->addCacheTags([
        IslandoraController::LISTING_TAG,
      ]);
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

    $response = new JsonResponse($output);

    $response->getCacheableMetadata()
      ->addCacheableDependency($cache_meta)
      ->addCacheableDependency($object)
      ->addCacheableDependency($this->config('islandora_xacml_editor.settings'))
      ->addCacheContexts([
        'url.query_args:q',
      ])
      ->addCacheTags([
        IslandoraController::LISTING_TAG,
      ]);

    return $response;
  }

  /**
   * Access callback function as to whether display the editor or not.
   */
  public function manageAccess($object = NULL) {
    $object = islandora_object_load($object);
    $perm = islandora_xacml_editor_access($object);
    return AccessResult::allowedIf($perm)
      ->addCacheableDependency($object)
      ->cachePerPermissions();
  }

}
