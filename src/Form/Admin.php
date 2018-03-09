<?php

namespace Drupal\islandora_xacml_editor\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Module settings form.
 */
class Admin extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_xacml_editor_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('islandora_xacml_editor.settings');

    foreach (Element::children($form) as $variable) {
      $config->set($variable, $form_state->getValue($form[$variable]['#parents']));
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['islandora_xacml_editor.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form = [];

    $form['#attached']['library'][] = 'islandora_xacml_editor/xacml-editor-css';
    $form['islandora_xacml_editor_show_dsidregex'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display the DSID regex textfield?'),
      '#default_value' => $this->config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_show_dsidregex'),
    ];
    $form['islandora_xacml_editor_show_mimeregex'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display the MIME type regex textfield?'),
      '#default_value' => $this->config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_show_mimeregex'),
    ];
    $form['islandora_xacml_editor_restrictions'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Restrictions for DSID and MIME type'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => 'DSIDs and MIMEs that will not appear in the autocomplete fields or be allowed as filters.',
    ];
    $form['islandora_xacml_editor_restrictions']['islandora_xacml_editor_restricted_dsids'] = [
      '#type' => 'textarea',
      '#title' => $this->t('DSID'),
      '#default_value' => $this->config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_restricted_dsids'),
    ];
    $form['islandora_xacml_editor_restrictions']['islandora_xacml_editor_restricted_mimes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('MIME type'),
      '#default_value' => $this->config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_restricted_mimes'),
    ];
    $form['islandora_xacml_editor_defaults'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default users and roles'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => $this->t('The users and roles that will appear as the default selected unless there is a existing XACML policy attached to an object.'),
    ];

    // Get the user list.
    $users = [];
    $user_storage = \Drupal::service('entity_type.manager')->getStorage('user');
    $ids = $user_storage->getQuery()->execute();
    foreach ($ids as $id) {
      $user = $user_storage->load($id);
      $user->id() == 0 ? $users['anonymous'] = 'anonymous' : $users[$user->getAccountName()] = $user->getAccountName();
      if ($user->id() == 1) {
        $admin_user = $user->getAccountName();
        $form_state->set(['islandora_xacml', 'admin_user'], $user->getAccountName());
      }
    }

    // Get role list.
    $roles = [];
    $role_storage = \Drupal::service('entity_type.manager')->getStorage('user_role');
    $ids = $role_storage->getQuery()->execute();
    foreach ($ids as $id) {
      $role = $role_storage->load($id);
      $roles[$role->id()] = $role->label();
    }

    $form['islandora_xacml_editor_defaults']['islandora_xacml_editor_default_users'] = [
      '#type' => 'select',
      '#title' => $this->t('Users'),
      '#options' => $users,
      '#default_value' => $this->config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_default_users'),
      '#multiple' => TRUE,
      '#size' => 10,
      '#prefix' => '<div class="islandora_xacml_selects">',
    ];
    $form['islandora_xacml_editor_defaults']['islandora_xacml_editor_default_roles'] = [
      '#type' => 'select',
      '#title' => $this->t('Roles'),
      '#default_value' => $this->config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_default_roles'),
      '#options' => $roles,
      '#multiple' => TRUE,
      '#size' => 10,
      '#suffix' => '</div>',
    ];
    return parent::buildForm($form, $form_state);
  }

}
