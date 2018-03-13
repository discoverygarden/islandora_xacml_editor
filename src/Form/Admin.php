<?php

namespace Drupal\islandora_xacml_editor\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Module settings form.
 */
class Admin extends ConfigFormBase {

  protected $entityTypeManager;

  /**
   * Class constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

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

    $config->set('islandora_xacml_editor_show_dsidregex', $form_state->getValue('islandora_xacml_editor_show_dsidregex'));
    $config->set('islandora_xacml_editor_show_mimeregex', $form_state->getValue('islandora_xacml_editor_show_mimeregex'));
    $config->set('islandora_xacml_editor_restricted_dsids', $form_state->getValue('islandora_xacml_editor_restricted_dsids'));
    $config->set('islandora_xacml_editor_restricted_mimes', $form_state->getValue('islandora_xacml_editor_restricted_mimes'));
    $config->set('islandora_xacml_editor_default_users', $form_state->getValue('islandora_xacml_editor_default_users'));
    $config->set('islandora_xacml_editor_default_roles', $form_state->getValue('islandora_xacml_editor_default_roles'));

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
  public function buildForm(array $form, FormStateInterface $form_state) {
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
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Restrictions for DSID and MIME type'),
      '#description' => $this->t('DSIDs and MIMEs that will not appear in the autocomplete fields or be allowed as filters.'),
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
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Default users and roles'),
      '#description' => $this->t('The users and roles that will appear as the default selected unless there is a existing XACML policy attached to an object.'),
    ];

    // Get the user list.
    $users = [];
    $user_storage = $this->entityTypeManager->getStorage('user');
    $ids = $user_storage->getQuery()->execute();
    foreach ($ids as $id) {
      $user = $user_storage->load($id);
      $user->id() == 0 ? $users['anonymous'] = 'anonymous' : $users[$user->getAccountName()] = $user->getAccountName();
    }

    // Get role list.
    $roles = [];
    $role_storage = $this->entityTypeManager->getStorage('user_role');
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
