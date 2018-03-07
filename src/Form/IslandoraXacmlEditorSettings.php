<?php

/**
 * @file
 * Contains \Drupal\islandora_xacml_editor\Form\IslandoraXacmlEditorSettings.
 */

namespace Drupal\islandora_xacml_editor\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class IslandoraXacmlEditorSettings extends ConfigFormBase {

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

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['islandora_xacml_editor.settings'];
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // @FIXME
// The Assets API has totally changed. CSS, JavaScript, and libraries are now
// attached directly to render arrays using the #attached property.
//
//
// @see https://www.drupal.org/node/2169605
// @see https://www.drupal.org/node/2408597
// drupal_add_css(drupal_get_path('module', 'islandora_xacml_editor') . '/css/islandora_xacml_editor.css');

    $form = [];

    $form['islandora_xacml_editor_show_dsidregex'] = [
      '#type' => 'checkbox',
      '#title' => t('Display the DSID regex textfield?'),
      '#default_value' => \Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_show_dsidregex'),
    ];
    $form['islandora_xacml_editor_show_mimeregex'] = [
      '#type' => 'checkbox',
      '#title' => t('Display the MIME type regex textfield?'),
      '#default_value' => \Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_show_mimeregex'),
    ];
    $form['islandora_xacml_editor_restrictions'] = [
      '#type' => 'fieldset',
      '#title' => t('Restrictions for DSID and MIME type'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => 'DSIDs and MIMEs that will not appear in the autocomplete fields or be allowed as filters.',
    ];
    $form['islandora_xacml_editor_restrictions']['islandora_xacml_editor_restricted_dsids'] = [
      '#type' => 'textarea',
      '#title' => t('DSID'),
      '#default_value' => \Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_restricted_dsids'),
    ];
    $form['islandora_xacml_editor_restrictions']['islandora_xacml_editor_restricted_mimes'] = [
      '#type' => 'textarea',
      '#title' => t('MIME type'),
      '#default_value' => \Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_restricted_mimes'),
    ];
    $form['islandora_xacml_editor_defaults'] = [
      '#type' => 'fieldset',
      '#title' => t('Default users and roles'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => t('The users and roles that will appear as the default selected unless there is a existing XACML policy attached to an object.'),
    ];

    // Get the user list.
    $users = [];
    $result = db_query('SELECT u.uid, u.name FROM {users} u');
    foreach ($result as $user) {
      $user->id() == 0 ? $users['anonymous'] = 'anonymous' : $users[$user->getAccountName()] = $user->getAccountName();
      if ($user->id() == 1) {
        $admin_user = $user->getAccountName();
        $form_state->set(['islandora_xacml', 'admin_user'], $user->getAccountName());
      }
    }

    // Get role list.
    $roles = [];
    $result = db_query('SELECT r.rid, r.name FROM {role} r');
    foreach ($result as $role) {
      $role->rid == 0 ? $roles['anonymous'] = 'anonymous' : $roles[$role->name] = $role->name;
    }

    $form['islandora_xacml_editor_defaults']['islandora_xacml_editor_default_users'] = [
      '#type' => 'select',
      '#title' => t('Users'),
      '#options' => $users,
      '#default_value' => \Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_default_users'),
      '#multiple' => TRUE,
      '#size' => 10,
      '#prefix' => '<div class="islandora_xacml_selects">',
    ];
    $form['islandora_xacml_editor_defaults']['islandora_xacml_editor_default_roles'] = [
      '#type' => 'select',
      '#title' => t('Roles'),
      '#default_value' => \Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_default_roles'),
      '#options' => $roles,
      '#multiple' => TRUE,
      '#size' => 10,
      '#suffix' => '</div>',
    ];
    return parent::buildForm($form, $form_state);
  }

}
