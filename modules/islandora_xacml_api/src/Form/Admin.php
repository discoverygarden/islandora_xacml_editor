<?php

namespace Drupal\islandora_xacml_api\Form;

use Drupal\islandora\Form\ModuleHandlerAdminForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Module administration form.
 */
class Admin extends ModuleHandlerAdminForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_xacml_api_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('islandora_xacml_api.settings');

    $config->set('islandora_xacml_api_save_relationships', $form_state->getValue('islandora_xacml_api_save_relationships'));
    $config->set('islandora_xacml_api_rels_viewable_role', $form_state->getValue('islandora_xacml_api_rels_viewable_role'));
    $config->set('islandora_xacml_api_rels_viewable_user', $form_state->getValue('islandora_xacml_api_rels_viewable_user'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['islandora_xacml_api.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['islandora_xacml_api_save_relationships'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Save relationships'),
      '#description' => $this->t('Causes relationships to be written to the REL-INT/EXT when the policy is saved.'),
      '#default_value' => $this->config('islandora_xacml_api.settings')->get('islandora_xacml_api_save_relationships'),
    ];
    $form['islandora_xacml_api_rels_viewable_role'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solr RELS-EXT ViewableByRole field'),
      '#default_value' => $this->config('islandora_xacml_api.settings')->get('islandora_xacml_api_rels_viewable_role'),
    ];
    $form['islandora_xacml_api_rels_viewable_user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solr RELS-EXT ViewableByUser field'),
      '#default_value' => $this->config('islandora_xacml_api.settings')->get('islandora_xacml_api_rels_viewable_user'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

}
