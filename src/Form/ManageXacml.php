<?php

namespace Drupal\islandora_xacml_editor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

use Drupal\islandora_xacml_api\IslandoraXacml;
use AbstractObject;

/**
 * Upload form when ingesting PDF objects.
 */
class ManageXacml extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_xacml_editor_manage_xacml_form';
  }

  /**
   * Define the xacml management form.
   *
   * @param array $form
   *   The Drupal form definition.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The Drupal form state.
   * @param AbstractObject $object
   *   The collection to move child objects from.
   *
   * @return array
   *   The Drupal form definition.
   */
  public function buildForm(array $form, FormStateInterface $form_state, AbstractObject $object = NULL) {
    if (\Drupal::moduleHandler()->moduleExists('islandora_basic_collection')) {
      module_load_include('inc', 'islandora', 'includes/utilities');
      // Hard code the XACML pager element because it needs to be unique.
      $pager_element = 3;
      $page = pager_find_page($pager_element);
      list($count, $results) = islandora_basic_collection_get_member_objects($object, $page, 10, 'manage', 'islandora:collectionCModel');
      pager_default_initialize($count, 10, $pager_element);
      $rows = [];
      $options = ['none' => 'None'];
      // Get the pids of the children for this collection.
      foreach ($results as $result) {
        $pid = $result['object']['value'];
        $child_collection = islandora_object_load($pid);
        $parent_pids = islandora_basic_collection_get_parent_pids($child_collection);
        $rels_ext = $child_collection->relationships->get(ISLANDORA_RELS_EXT_URI, 'inheritXacmlFrom');
        foreach ($parent_pids as $parent_pid) {
          $parent_object = islandora_object_load($parent_pid);
          $options[$parent_object->id] = $parent_object->label;
        }
        $default_value = (isset($rels_ext[0]) ? $rels_ext[0]['object']['value'] : 'none');
        $rows[$pid] = [
          'selected' => [
            '#type' => 'checkbox',
          ],
          'title' => [
            '#markup' => Link::createFromRoute(
              $this->t('@label (@pid)', ['@label' => $child_collection->label, '@pid' => $pid]),
              'islandora.view_object',
              ['object' => $pid])
            ->toString(),
          ],
          'parents' => [
            '#type' => 'select',
            '#options' => $options,
            '#default_value' => $default_value,
          ],
        ];
      }
      $pager_element = [
        '#type' => 'pager',
        '#quantity' => 20,
        '#element' => $pager_element,
      ];
      $pager = \Drupal::service('renderer')->render($pager_element);
      $pager = islandora_basic_collection_append_fragment_to_pager_url($pager, '#manage-xacml');
      return [
        '#action' => Url::fromRoute(
          '<current>',
          [],
          ['query' => $this->getRequest()->query->all(), 'fragment' => '#manage-xacml']
        )->toString(),
        'help' => [
          '#type' => 'item',
          '#markup' => $this->t('XACML Inheritance Policies'),
        ],
        'table' => [
          '#tree' => TRUE,
          '#theme' => 'islandora_xacml_editor_policy_management_table',
          '#header' => [
            '' => '',
            'title' => $this->t('COLLECTION(PID)'),
            'parents' => 'INHERIT FROM',
          ],
          'rows' => $rows,
          '#prefix' => $pager,
          '#suffix' => $pager,
        ],
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Update XACML Inheritance'),
          '#access' => count($rows),
        ],
      ];
    }
    else {
      return [];
    }
  }

  /**
   * Submit handler for the manage XACML form.
   *
   * @param array $form
   *   The Drupal form definition.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The Drupal form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $child_pids = array_keys($form_state->getValue(['table', 'rows']));
    $count = 0;
    foreach ($form_state->getValue(['table', 'rows']) as $row) {
      // Check if selected.
      if ($row['selected'] > 0) {
        $parent_object = islandora_object_load($row['parents']);
        // Are we adding the XACML POLICY or removing it? Just
        // check for a valid fedora object.
        if ($parent_object) {
          $object = islandora_object_load($child_pids[$count]);
          // Make sure there is a POLICY to inherit.
          if ($parent_object['POLICY']) {
            $object->relationships->add(ISLANDORA_RELS_EXT_URI, 'inheritXacmlFrom', $parent_object->id, RELS_TYPE_URI);
            $xacml = new IslandoraXacml($object, $parent_object['POLICY']->content);
            $xacml->writeBackToFedora();
            drupal_set_message($this->t('@child now inherits XACML from @parent.', [
              '@child' => $object->id,
              '@parent' => $parent_object->id]), 'status');
          }
          else {
            drupal_set_message($this->t('@parent does not have an XACML policy.', [
              '@parent' => $parent_object->id]), 'status');
          }
        }
        else {
          // 'None' is currently selected for this row, so if it is selected,
          // we must remove the current XACML policy if it exists.
          $object = islandora_object_load($child_pids[$count]);
          if (isset($object['POLICY'])) {
            $object->relationships->remove(ISLANDORA_RELS_EXT_URI, 'inheritXacmlFrom');
            $object->purgeDatastream('POLICY');
            drupal_set_message($this->t('@child no longer inherits XACML.', ['@child' => $object->id]), 'status');
          }
        }
      }
      $count++;
    }
  }

}
