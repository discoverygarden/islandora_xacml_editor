<?php

namespace Drupal\islandora_xacml_editor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

use Drupal\islandora_xacml_api\IslandoraXacml;
use Drupal\islandora_xacml_api\Xacml;
use Drupal\islandora_xacml_api\XacmlException;

/**
 * The XACML editing form.
 */
class IslandoraXacmlEditorForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_xacml_editor_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $object = NULL) {
    // @FIXME
// The Assets API has totally changed. CSS, JavaScript, and libraries are now
// attached directly to render arrays using the #attached property.
//
//
// @see https://www.drupal.org/node/2169605
// @see https://www.drupal.org/node/2408597
// drupal_add_css(drupal_get_path('module', 'islandora_xacml_editor') . '/css/islandora_xacml_editor.css');

    module_load_include('inc', 'islandora', 'includes/utilities');
    module_load_include('inc', 'islandora', 'includes/breadcrumb');

    // @FIXME
    // drupal_set_title() has been removed. There are now a few ways to set the title
    // dynamically, depending on the situation.
    //
    //
    // @see https://www.drupal.org/node/2067859
    // drupal_set_title(t('Islandora XACML Editor'));

    if ($form_state->get(['islandora_xacml'])) {
      $form_state->set(['islandora_xacml'], []);
      $form_state->set(['islandora_xacml', 'pid'], $object->id);
    }

    if (!islandora_is_valid_pid($object->id)) {
      drupal_not_found();
      exit();
    }

    if (!$object) {
      drupal_not_found();
      exit();
    }

    drupal_set_breadcrumb(islandora_get_breadcrumbs($object));

    // Get the user list.
    $users = [];
    $user_storage = \Drupal::service('entity_type.manager')->getStorage('user');
    $ids = $user_storage->getQuery()->execute();
    foreach ($ids as $id) {
      $user = $user_storage->load($id);
      $user->id() == 0 ? $users['anonymous'] = 'anonymous' : $users[$user->getAccountName()] = $user->getAccountName();
      if ($user->id() == 1) {
        $form_state->set(['islandora_xacml', 'admin_user'], $user->getAccountName());
      }
    }

    // Current user.
    $form_state->set(['islandora_xacml', 'current_user'], \Drupal::currentUser()->getAccountName());

    // Get role list.
    $roles = [];
    $role_storage = \Drupal::service('entity_type.manager')->getStorage('user_role');
    $ids = $role_storage->getQuery()->execute();
    foreach ($ids as $id) {
      $role = $role_storage->load($id);
      $roles[$role->id()] = $role->label();
    }

    $new_xacml = FALSE;

    if (isset($object['POLICY'])) {
      // Some basic error handling.
      try {
        $xacml = new Xacml($object['POLICY']->content);

        if ($xacml->managementRule->isPopulated() && !$xacml->managementRule->validateDefaultMethods()) {
          drupal_set_message($this->t('The management XACML policy is not valid.'), 'error');
        }

        if ($xacml->viewingRule->isPopulated() && !$xacml->viewingRule->validateDefaultMethods()) {
          drupal_set_message($this->t('The viewing XACML policy is not valid.'), 'error');
        }

        if ($xacml->datastreamRule->isPopulated() && !$xacml->datastreamRule->validateDefaultMethods()) {
          drupal_set_message($this->t('The datastream XACML policy is not valid.'), 'error');
        }
      }
      catch (XacmlException $e) {
        \Drupal::logger('islandora_xacml_editor')->error('Exception in Islandora Xacml: @message', [
          '@message',
          $e->getMessage(),
        ]);
        drupal_set_message($e->getMessage());
        drupal_set_message($this->t("Xacml Parser failed to parse @object_pid. It is likely this POLICY wasn't written by the islandora XACML editor, it will have to be modified by hand.", [
          "@object_pid" => $object->id,
        ]));
        drupal_not_found();
        exit();
      }
    }
    else {
      $new_xacml = TRUE;
      $xacml = new Xacml();
    }

    $form = ['#tree' => TRUE];

    $form['access_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable XACML Restrictions on Object Viewing'),
      '#default_value' => $xacml->viewingRule->isPopulated(),
    ];

    $form['access'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Object Viewing'),
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="access_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['access']['users'] = [
      '#type' => 'select',
      '#title' => $this->t('Allowed Users'),
      '#default_value' => islandora_xacml_editor_retrieve_users($xacml, $new_xacml, 'viewing'),
      '#options' => $users,
      '#multiple' => TRUE,
      '#size' => 10,
      '#prefix' => '<div class="islandora_xacml_selects">',
    ];

    $form['access']['roles'] = [
      '#type' => 'select',
      '#title' => $this->t('Allowed Roles'),
      '#options' => $roles,
      '#multiple' => TRUE,
      '#size' => 10,
      '#default_value' => islandora_xacml_editor_retrieve_roles($xacml, $new_xacml, 'viewing'),
      '#suffix' => '</div>',
    ];

    // Grab original value used in comparisons.
    if ($form_state->get([
      'islandora_xacml',
      'access',
      'enabled',
    ])) {
      $form_state->set(['islandora_xacml', 'access', 'enabled'], $form['access_enabled']['#default_value']);
    }

    $form['manage_enabled'] = [
      '#weight' => -2,
      '#type' => 'checkbox',
      '#title' => $this->t('Enable XACML Restrictions on Object Management'),
      '#default_value' => $xacml->managementRule->isPopulated(),
    ];

    $form['manage'] = [
      '#weight' => -1,
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Object Management'),
      '#description' => $this->t('Select the Users and Roles that are allowed to manage this object. These users will also be able to view the object even if not explicitly allowed to in the object access section. WARNING: If you unselect yourself you will be locked out of the object.'),
      '#states' => [
        'visible' => [
          ':input[name="manage_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['manage']['users'] = [
      '#type' => 'select',
      '#title' => $this->t('Users'),
      '#options' => $users,
      '#default_value' => islandora_xacml_editor_retrieve_users($xacml, $new_xacml, 'management'),
      '#multiple' => TRUE,
      '#size' => 10,
      '#prefix' => '<div class="islandora_xacml_selects">',
    ];

    $form['manage']['roles'] = [
      '#type' => 'select',
      '#title' => $this->t('Roles'),
      '#default_value' => islandora_xacml_editor_retrieve_roles($xacml, $new_xacml, 'management'),
      '#options' => $roles,
      '#multiple' => TRUE,
      '#size' => 10,
      '#suffix' => '</div>',
    ];

    $form['dsid_mime_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable XACML Restrictions on DSIDs and MIME types'),
      '#default_value' => $xacml->datastreamRule->isPopulated(),
    ];

    $form['dsid_mime'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Datastreams and MIME types'),
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="dsid_mime_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    // Grab the original value to be used in comparisons.
    if ($form_state->get([
      'islandora_xacml',
      'dsid_mime',
      'enabled',
    ])) {
      $form_state->set(['islandora_xacml', 'dsid_mime', 'enabled'], $form['dsid_mime_enabled']['#default_value']);
    }

    // Call CModel oriented variants first.
    $query_choices = [];
    foreach (islandora_build_hook_list('islandora_xacml_editor_child_query', $object->models) as $hook) {
      $temp = \Drupal::moduleHandler()->invokeAll($hook, [$object]);
      if (!empty($temp)) {
        $query_choices = array_merge_recursive($query_choices, $temp);
        // We are doing this to handle the "flat" use case where we are not
        // recursing more than one level. This is a unique case so this is the
        // easiest way to handle it.
        if (isset($query_choices['all_children'])) {
          $query_choices['flat_collection'] = $query_choices['all_children'];
          $query_choices['flat_collection']['description'] = $this->t('All immediate children of the collection (shallow traversal)');
          $query_choices['flat_collection']['restricted_cmodels'] = [
            'islandora:collectionCModel',
          ];
        }
      }
    }

    if (!empty($query_choices)) {
      // The "newchildren" option is applied automatically through ingest steps.
      $update_options = [
        'newchildren' => $this->t('New children of this object.'),
      ];
      foreach ($query_choices as $key => $query) {
        $form_state->set(['islandora_xacml', 'query_choices', $key], $query);
        $update_options[$key] = $query['description'];
      }
      $form['update_options'] = [
        '#type' => 'select',
        '#title' => $this->t('What items would you like to apply this policy to?'),
        '#default_value' => 'newchildren',
        '#options' => $update_options,
      ];

      if (!$form_state->get(['islandora_xacml', 'child_option'])) {
        $form['update_options']['#value'] = $form_state->get([
          'islandora_xacml',
          'child_option',
        ]);
      }

      $form['update_options_warning'] = [
        '#type' => 'container',
        '#states' => [
          'invisible' => [
            ':input[name="update_options"]' => [
              'value' => 'newchildren',
            ],
          ],
        ],
        'markup' => [
          '#markup' => $this->t('<strong>Warning:</strong> Overriding existing policies can break the expected behavoir of other modules which rely on full control of an objects POLICY. If any children have embargoes set by <em>Islandora Scholar Embargo</em> they will be lost when this action is committed.'),
        ],
      ];

    }
    $form['dsid_mime']['users'] = [
      '#type' => 'select',
      '#title' => $this->t('Users'),
      '#options' => $users,
      '#default_value' => islandora_xacml_editor_retrieve_users($xacml, $new_xacml, 'datastream'),
      '#multiple' => TRUE,
      '#size' => 10,
      '#prefix' => '<div class="islandora_xacml_selects">',
    ];

    $form['dsid_mime']['roles'] = [
      '#type' => 'select',
      '#title' => $this->t('Roles'),
      '#default_value' => islandora_xacml_editor_retrieve_roles($xacml, $new_xacml, 'datastream'),
      '#options' => $roles,
      '#multiple' => TRUE,
      '#size' => 10,
      '#suffix' => '</div>',
    ];

    // AJAX callbacks processing.
    if (!$form_state->getTriggeringElement()) {
      // Add DSID.
      if ($form_state->getTriggeringElement() == 'dsid_add_button' || $form_state->getTriggeringElement() == 'dsid_add_textfield') {
        $object = islandora_object_load($form_state->get([
          'islandora_xacml',
          'pid',
        ]));

        if ($form_state->get(['islandora_xacml', 'add_dsid'])) {
          $form_state->set(['islandora_xacml', 'add_dsid'], []);
        }

        $add_text = trim($form_state->getUserInput());

        if (!empty($add_text) && !ctype_space($add_text)) {
          $restricted_dsids = \Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_restricted_dsids');
          $restricted_dsids = preg_split('/[\s,]+/', $restricted_dsids);

          if (!(!$form_state->get(['islandora_xacml', 'add_dsid']) && in_array($add_text, $form_state->get([
            'islandora_xacml',
            'add_dsid',
          ]))) && !(!$form_state->get(['islandora_xacml', 'selected_dsid']) && in_array($add_text, $form_state->get([
            'islandora_xacml',
            'selected_dsid',
          ]))) && !in_array($add_text, $restricted_dsids)) {
            $form_state->set(['islandora_xacml', 'add_dsid'], $add_text);
          }
          elseif (in_array($add_text, $restricted_dsids)) {
            drupal_set_message($this->t('The DSID @dsid was not added as it is restricted from the admin settings page!', [
              '@dsid' => $add_text,
            ]), 'warning');
          }
          else {
            drupal_set_message($this->t('The DSID @dsid was not added as it already exists as a filter!', [
              '@dsid' => $add_text
            ]), 'warning');
          }
        }
        else {
          drupal_set_message($this->t('No DSID value entered!'), 'error');
        }
      }
        // ADD DSID Regex.
      elseif ($form_state->getTriggeringElement() == 'dsid_regex_add_button' || $form_state->getTriggeringElement() == 'dsid_regex_add_textfield') {
        if ($form_state->get(['islandora_xacml', 'dsid_regexs'])) {
          $form_state->set(['islandora_xacml', 'dsid_regexs'], []);
        }
        $add_text = trim($form_state->getUserInput());

        // Check the additional dsids and the dsids from the XACML rules.
        if (!empty($add_text) && !ctype_space($add_text)) {
          if (!(!$form_state->get(['islandora_xacml', 'dsid_regexs']) && in_array($add_text, $form_state->get([
            'islandora_xacml',
            'dsid_regexs',
          ]))) && !(!$form_state->get([
            'islandora_xacml',
            'selected_dsid_regexs',
          ]) && in_array($add_text, $form_state->get([
            'islandora_xacml',
            'selected_dsid_regexs',
          ])))) {
            $form_state->set(['islandora_xacml', 'dsid_regexs'], $add_text);
          }
          else {
            drupal_set_message($this->t('The DSID regex @regex was not added as it already exists as a filter!', [
              '@regex' => $add_text,
            ]), 'warning');
          }
        }
        else {
          drupal_set_message($this->t('No DSID regex value entered!'), 'error');
        }
      }
      // Add MIME Regex.
      elseif ($form_state->getTriggeringElement() == 'mime_regex_add_button' || $form_state->getTriggeringElement() == 'mime_regex_add_textfield') {
        // Store and checks.
        if ($form_state->get([
          'islandora_xacml',
          'mime_regexs',
        ])) {
          $form_state->set(['islandora_xacml', 'mime_regexs'], []);
        }

        $add_text = trim($form_state->getUserInput());

        if (!empty($add_text) && !ctype_space($add_text)) {
          if (!(!$form_state->get(['islandora_xacml', 'mime_regexs']) && in_array($add_text, $form_state->get([
            'islandora_xacml',
            'mime_regexs',
          ]))) && !(!$form_state->get([
            'islandora_xacml',
            'selected_mime_regexs',
          ]) && in_array($add_text, $form_state->get([
            'islandora_xacml',
            'selected_mime_regexs',
          ])))) {
            $form_state->set(['islandora_xacml', 'mime_regexs'], $add_text);
          }
          else {
            drupal_set_message($this->t('The MIME type regex @regex was not added as it already exists as a filter!', [
              '@regex' => $add_text
            ]), 'warning');
          }
        }
        else {
          drupal_set_message($this->t('No MIME type regex value entered!'), 'error');
        }
      }
      // Add MIME type.
      elseif ($form_state->getTriggeringElement() == 'mime_add_button' || $form_state->getTriggeringElement() == 'mime_add_textfield') {
        if ($form_state->get(['islandora_xacml', 'add_mime'])) {
          $form_state->set(['islandora_xacml', 'add_mime'], []);
        }

        $add_text = $form_state->getUserInput();
        if (!empty($add_text) && !ctype_space($add_text)) {
          $restricted_mimes = \Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_restricted_mimes');
          $restricted_mimes = preg_split('/[\s,]+/', $restricted_mimes);

          if (!(!$form_state->get(['islandora_xacml', 'add_mime']) && in_array($add_text, $form_state->get([
            'islandora_xacml',
            'add_mime',
          ]))) && !(!$form_state->get(['islandora_xacml', 'selected_mime']) && in_array($add_text, $form_state->get([
            'islandora_xacml',
            'selected_mime',
          ]))) && !in_array($add_text, $restricted_mimes)) {
            $form_state->set(['islandora_xacml', 'add_mime'], $add_text);
          }
          elseif (in_array($add_text, $restricted_mimes)) {
            drupal_set_message($this->t('The MIME type @mime was not added as it is restricted from the admin settings page!', [
              '@mime' => $add_text,
            ]), 'warning');
          }
          else {
            drupal_set_message($this->t('The MIME type @mime was not added as it already exists as a filter!', [
              '@mime' => $add_text,
            ]), 'warning');
          }
        }
        else {
          drupal_set_message($this->t('No MIME type value entered!'), 'error');
        }
      }
      elseif ($form_state->getTriggeringElement() == 'islandora_xacml_editor_remove_all') {
        $remove_count = 0;

        foreach ($form_state->get(['islandora_xacml', 'rows']) as $key => $value) {
          $type = \Drupal\Component\Utility\Unicode::strtolower($value['Type']);
          $filter = $value['Filter'];

          if ($type == 'dsid') {
            $form_state->set(['islandora_xacml', 'remove_dsid'], $filter);
          }
          elseif ($type == 'mime type') {
            $form_state->set(['islandora_xacml', 'remove_mime'], $filter);
          }
          elseif ($type == 'mime type regex') {
            $form_state->set(['islandora_xacml', 'remove_mime_regex'], $filter);
          }
          elseif ($type == 'dsid regex') {
            $form_state->set(['islandora_xacml', 'remove_dsid_regex'], $filter);
          }
          $remove_count++;
        }

        $remove_output = \Drupal::translation()->formatPlural($remove_count, '@filter_count applied filter was removed.', '@filter_count applied filters were removed.', [
          '@filter_count' => $remove_count,
        ]);
        drupal_set_message($remove_output);
      }
      elseif ($form_state->getTriggeringElement() == 'islandora_xacml_editor_remove_selected') {
        $remove_row = [];
        foreach ($form_state->getValue(['dsid_mime', 'rules', 'table']) as $checkbox => $value) {
          if ($value !== 0) {
            $remove_row[] = $checkbox;
          }
        }
        if (count($remove_row) > 0) {
          $remove_count = 0;

          foreach ($remove_row as $row) {
            $remove_vals = explode('---', $row);
            $type = $remove_vals[0];
            $filter = $remove_vals[1];

            if ($type == 'dsid') {
              $form_state->set(['islandora_xacml', 'remove_dsid'], $filter);
            }
            elseif ($type == 'mime') {
              $form_state->set(['islandora_xacml', 'remove_mime'], $filter);
            }
            elseif ($type == 'mime_regexs') {
              $form_state->set(['islandora_xacml', 'remove_mime_regex'], $filter);
            }
            elseif ($type == 'dsid_regexs') {
              $form_state->set(['islandora_xacml', 'remove_dsid_regex'], $filter);
            }
            $remove_count++;
          }
          $remove_output = \Drupal::translation()->formatPlural($remove_count, '@filter_count applied filter was removed.', '@filter_count applied filters were removed.', [
            '@filter_count' => $remove_count
            ]);
          drupal_set_message($remove_output);
        }
        else {
          drupal_set_message($this->t('Please select the filters you wish to remove.'), 'error');
        }
      }
    }

    // Grab these values to handle removal.
    $temp_mime = $xacml->datastreamRule->getMimetypes();
    $temp_dsid = $xacml->datastreamRule->getDsids();
    $temp_mime_regexs = $xacml->datastreamRule->getMimetypeRegexs();
    $temp_dsid_regexs = $xacml->datastreamRule->getDsidRegexs();

    if (!$form_state->get(['islandora_xacml', 'remove_dsid'])) {
      foreach ($form_state->get(['islandora_xacml', 'remove_dsid']) as $value) {
        $key = array_search($value, $temp_dsid);

        // If the value is not one of our 'hidden DSIDs'.
        if (is_numeric($key)) {
          $xacml->datastreamRule->removeDsid($temp_dsid[$key]);
          $form_state->set(['islandora_xacml', 'hidden_dsids'], $value);
        }

        if (!$form_state->get(['islandora_xacml', 'add_dsid'])) {
          $search = array_search($value, $form_state->get([
            'islandora_xacml',
            'add_dsid',
          ]));

          if (is_numeric($search)) {
            $form_state->set(['islandora_xacml', 'add_dsid', $search], NULL);
          }
        }
      }
      $form_state->set(['islandora_xacml', 'remove_dsid'], NULL);
    }

    if (!$form_state->get(['islandora_xacml', 'remove_mime'])) {
      foreach ($form_state->get(['islandora_xacml', 'remove_mime']) as $value) {
        $key = array_search($value, $temp_mime);

        // If the value is not one of our 'hidden mimes'.
        if (is_numeric($key)) {
          $xacml->datastreamRule->removeMimetype($temp_mime[$key]);
          $form_state->set(['islandora_xacml', 'hidden_mimes'], $value);
        }

        if (!$form_state->get(['islandora_xacml', 'add_mime'])) {
          $search = array_search($value, $form_state->get([
            'islandora_xacml',
            'add_mime',
          ]));

          if (is_numeric($search)) {
            unset($form_state->get(['islandora_xacml', 'add_mime', $search]));
          }
        }
      }
      unset($form_state->get(['islandora_xacml', 'remove_mime']));
    }

    if (!$form_state->get(['islandora_xacml', 'remove_mime_regex'])) {
      foreach ($form_state->get(['islandora_xacml', 'remove_mime_regex']) as $value) {
        $key = array_search($value, $temp_mime_regexs);

        // If the value is not one of our 'hidden mime regexs'.
        if (is_numeric($key)) {
          $xacml->datastreamRule->removeMimetypeRegex($temp_mime_regexs[$key]);
          $form_state->set(['islandora_xacml', 'hidden_mime_regexs'], $value);
        }

        if (!$form_state->get(['islandora_xacml', 'mime_regexs'])) {
          $search = array_search($value, $form_state->get([
            'islandora_xacml',
            'mime_regexs',
          ]));

          if (is_numeric($search)) {
            unset($form_state->get(['islandora_xacml', 'mime_regexs', $search]));
          }
        }
      }
      unset($form_state->get(['islandora_xacml', 'remove_mime_regex']));
    }

    if (!$form_state->get(['islandora_xacml', 'remove_dsid_regex'])) {
      foreach ($form_state->get(['islandora_xacml', 'remove_dsid_regex']) as $value) {
        $key = array_search($value, $temp_dsid_regexs);

        // If the value is not one of our 'hidden mimes'.
        if (is_numeric($key)) {
          $xacml->datastreamRule->removeDsidRegex($temp_dsid_regexs[$key]);
          $form_state->set(['islandora_xacml', 'hidden_dsid_regexs'], $value);
        }

        if (!$form_state->get(['islandora_xacml', 'dsid_regexs'])) {
          $search = array_search($value, $form_state->get([
            'islandora_xacml',
            'dsid_regexs',
          ]));

          if (is_numeric($search)) {
            unset($form_state->get(['islandora_xacml', 'dsid_regexs', $search]));
          }
        }
      }
      unset($form_state->get(['islandora_xacml', 'remove_dsid_regex']));
    }

    // If we are carrying values from the original rule that need to be removed
    // remove them.
    if (!$form_state->get([
      'islandora_xacml',
      'hidden_mimes',
    ])) {
      foreach ($form_state->get(['islandora_xacml', 'hidden_mimes']) as $key => $value) {
        $xacml->datastreamRule->removeMimetype($value);
      }
    }

    if (!$form_state->get(['islandora_xacml', 'hidden_dsids'])) {
      foreach ($form_state->get(['islandora_xacml', 'hidden_dsids']) as $key => $value) {
        $xacml->datastreamRule->removeDsid($value);
      }
    }

    if (!$form_state->get(['islandora_xacml', 'hidden_mime_regexs'])) {
      foreach ($form_state->get(['islandora_xacml', 'hidden_mime_regexs']) as $key => $value) {
        $xacml->datastreamRule->removeMimetypeRegex($value);
      }
    }

    if (!$form_state->get(['islandora_xacml', 'hidden_dsid_regexs'])) {
      foreach ($form_state->get(['islandora_xacml', 'hidden_dsid_regexs']) as $key => $value) {
        $xacml->datastreamRule->removeDsidRegex($value);
      }
    }

    // Grab the updated values to handle addition of rules.
    $temp_mime = $xacml->datastreamRule->getMimetypes();
    $temp_dsid = $xacml->datastreamRule->getDsids();
    $temp_mime_regexs = $xacml->datastreamRule->getMimetypeRegexs();
    $temp_dsid_regexs = $xacml->datastreamRule->getDsidRegexs();

    // Add values we are carrying in the form storage to the datastream rules.
    if (!$form_state->get([
      'islandora_xacml',
      'add_dsid',
    ])) {
      foreach ($form_state->get(['islandora_xacml', 'add_dsid']) as $key => $value) {
        $search = array_search($value, $temp_dsid);

        if (!is_numeric($search)) {
          $xacml->datastreamRule->addDsid($value);

          if (!$form_state->get(['islandora_xacml', 'hidden_sids'])) {
            $remove_dsid = array_search($value, $form_state->get([
              'islandora_xacml',
              'hidden_dsids',
            ]));

            if (is_numeric($remove_dsid)) {
              unset($form_state->get([
                'islandora_xacml',
                'hidesids',
                $remove_dsid,
              ]));
            }
          }
        }
      }
    }

    if (!$form_state->get(['islandora_xacml', 'add_mime'])) {
      foreach ($form_state->get(['islandora_xacml', 'add_mime']) as $key => $value) {
        $search = array_search($value, $temp_mime);

        if (!is_numeric($search)) {
          $xacml->datastreamRule->addMimetype($value);

          if (!$form_state->get(['islandora_xacml', 'hidden_mimes'])) {
            $remove_mime = array_search($value, $form_state->get([
              'islandora_xacml',
              'hidden_mimes',
            ]));

            if (is_numeric($remove_mime)) {
              unset($form_state->get([
                'islandora_xacml',
                'hidden_mimes',
                $remove_mime,
              ]));
            }
          }
        }
      }
    }

    if (!$form_state->get(['islandora_xacml', 'dsid_regexs'])) {
      foreach ($form_state->get(['islandora_xacml', 'dsid_regexs']) as $key => $value) {
        $search = array_search($value, $temp_dsid_regexs);

        if (!is_numeric($search)) {
          $xacml->datastreamRule->addDsidRegex($value);

          if (!$form_state->get(['islandora_xacml', 'hidden_dsid_regexs'])) {
            $remove_dsid = array_search($value, $form_state->get([
              'islandora_xacml',
              'hidden_dsid_regexs',
            ]));

            if (is_numeric($remove_dsid)) {
              unset($form_state->get([
                'islandora_xacml',
                'hidden_dsid_regexs',
                $remove_dsid,
              ]));
            }
          }
        }
      }
    }

    if (!$form_state->get(['islandora_xacml', 'mime_regexs'])) {
      foreach ($form_state->get(['islandora_xacml', 'mime_regexs']) as $key => $value) {
        $search = array_search($value, $temp_mime_regexs);

        if (!is_numeric($search)) {
          $xacml->datastreamRule->addMimetypeRegex($value);

          if (!$form_state->get(['islandora_xacml', 'hidden_mime_regexs'])) {
            $remove_mime = array_search($value, $form_state->get([
              'islandora_xacml',
              'hidden_mime_regexs',
            ]));

            if (is_numeric($remove_mime)) {
              unset($form_state->get([
                'islandora_xacml',
                'hidden_mime_regexs',
                $remove_mime,
              ]));
            }
          }
        }
      }
    }

    // Grab the values one last time for storage and use in constructing the
    // rules table.
    $selected_mime = $xacml->datastreamRule->getMimetypes();
    $selected_dsid = $xacml->datastreamRule->getDsids();
    $selected_mime_regexs = $xacml->datastreamRule->getMimetypeRegexs();
    $selected_dsid_regexs = $xacml->datastreamRule->getDsidRegexs();

    // We store these values for use in the AJAX callbacks.
    $form_state->set([
      'islandora_xacml',
      'dsid_mime',
      'dsid',
    ], $selected_dsid);
    $form_state->set(['islandora_xacml', 'dsid_mime', 'mime'], $selected_mime);
    $form_state->set(['islandora_xacml', 'dsid_mime', 'dsid_regexs'], $selected_dsid_regexs);
    $form_state->set(['islandora_xacml', 'dsid_mime', 'mime_regexs'], $selected_mime_regexs);

    if (count($selected_mime) > 0) {
      $form_state->set(['islandora_xacml', 'selected_mime'], array_combine($selected_mime, $selected_mime));
    }
    else {
      unset($form_state->get(['islandora_xacml', 'selected_mime']));
    }

    if (count($selected_dsid) > 0) {
      $form_state->set(['islandora_xacml', 'selected_dsid'], array_combine($selected_dsid, $selected_dsid));
    }
    else {
      unset($form_state->get(['islandora_xacml', 'selected_dsid']));
    }

    if (count($selected_mime_regexs) > 0) {
      $form_state->set(['islandora_xacml', 'selected_mime_regexs'], array_combine($selected_mime_regexs, $selected_mime_regexs));
    }
    else {
      unset($form_state->get(['islandora_xacml', 'selected_mime_regexs']));
    }

    if (count($selected_dsid_regexs) > 0) {
      $form_state->set(['islandora_xacml', 'selected_dsid_regexs'], array_combine($selected_dsid_regexs, $selected_dsid_regexs));
    }
    else {
      unset($form_state->get(['islandora_xacml', 'selected_dsid_regexs']));
    }

    $rows = [];
    // Name the rows with a --- convention such that we can easily parse
    // for the selected row to remove down the road.
    if (!empty($selected_mime)) {
      foreach ($selected_mime as $mime) {
        $rows['mime---' . trim($mime)] = [
          'Filter' => trim($mime),
          'Type' => 'MIME Type',
        ];
      }
    }

    if (!empty($selected_mime_regexs)) {
      foreach ($selected_mime_regexs as $mime_regex) {
        $rows['mime_regexs---' . trim($mime_regex)] = [
          'Filter' => trim($mime_regex),
          'Type' => 'MIME Type Regex',
        ];
      }
    }

    if (!empty($selected_dsid)) {
      foreach ($selected_dsid as $dsid) {
        $rows['dsid---' . trim($dsid)] = [
          'Filter' => trim($dsid),
          'Type' => 'DSID',
        ];
      }
    }

    if (!empty($selected_dsid_regexs)) {
      foreach ($selected_dsid_regexs as $dsid_regex) {
        $rows['dsid_regexs---' . $dsid_regex] = [
          'Filter' => trim($dsid_regex),
          'Type' => 'DSID Regex',
        ];
      }
    }
    $form_state->set(['islandora_xacml', 'rows'], $rows);
    $form['dsid_mime']['rules'] = [
      '#prefix' => '<div id="islandora_xacml_dsid_mime">',
      '#suffix' => '</div>',
    ];

    $form['dsid_mime']['rules']['label'] = [
      '#type' => 'item',
      '#markup' => (count($rows) > 0) ? '<strong>Applied Rules:</strong>' : '<strong>No rules applied!</strong>',
    ];
    if (count($rows) > 0) {
      $form['dsid_mime']['rules']['table'] = islandora_xacml_editor_form_table($rows);

      $form['dsid_mime']['rules']['remove_selected'] = [
        '#type' => 'button',
        '#value' => $this->t('Remove selected'),
        '#name' => 'islandora_xacml_editor_remove_selected',
        '#ajax' => [
          'event' => 'click',
          'callback' => 'islandora_xacml_editor_remove_selected',
          'wrapper' => 'islandora_xacml_dsid_mime',
          'method' => 'replace',
        ],
      ];
      $form['dsid_mime']['rules']['remove_all'] = [
        '#type' => 'button',
        '#value' => $this->t('Remove all'),
        '#name' => 'islandora_xacml_editor_remove_all',
        '#ajax' => [
          'event' => 'click',
          'callback' => 'islandora_xacml_editor_remove_all',
          'wrapper' => 'islandora_xacml_dsid_mime',
        ],
      ];
    }

    $form['dsid_mime']['new_dsid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DSID'),
      '#autocomplete_path' => 'islandora/xacml/dsidautocomplete/' . $object->id,
      '#size' => 35,
      '#description' => $this->t('Type "*" to list all DSIDs.'),
      '#prefix' => '<div class="islandora_xacml_block_description">',
      '#name' => 'dsid_add_textfield',
      '#ajax' => [
        'event' => 'error',
        'callback' => 'islandora_xacml_editor_add_dsid_js',
        'wrapper' => 'islandora_xacml_dsid_mime',
        'keypress' => TRUE,
      ],
    ];
    $form['dsid_mime']['new_dsid_add'] = [
      '#name' => 'dsid_add_button',
      '#type' => 'button',
      '#value' => $this->t('Add'),
      '#suffix' => '</div>',
      '#ajax' => [
        'event' => 'click',
        'callback' => 'islandora_xacml_editor_add_dsid_js',
        'wrapper' => 'islandora_xacml_dsid_mime',
      ],
    ];

    if (\Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_show_dsidregex')) {
      $form['dsid_mime']['dsid_regex'] = [
        '#type' => 'textfield',
        '#title' => $this->t('DSID Regex'),
        '#description' => \Drupal::l($this->t('XML regex'), Url::fromUri('http://www.w3.org/TR/xmlschema-0/#regexAppendix')),
        '#size' => 35,
        '#prefix' => '<div class="islandora_xacml_block">',
        '#name' => 'dsid_regex_add_textfield',
        '#ajax' => [
          'event' => 'error',
          'callback' => 'islandora_xacml_editor_add_dsid_regex_js',
          'wrapper' => 'islandora_xacml_dsid_mime',
          'keypress' => TRUE,
        ],
      ];
      $form['dsid_mime']['dsid_regex_add'] = [
        '#name' => 'dsid_regex_add_button',
        '#type' => 'button',
        '#value' => $this->t('Add'),
        '#suffix' => '</div>',
        '#ajax' => [
          'event' => 'click',
          'callback' => 'islandora_xacml_editor_add_dsid_regex_js',
          'wrapper' => 'islandora_xacml_dsid_mime',
        ],
      ];
    }
    $form['dsid_mime']['new_mime'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MIME type'),
      '#autocomplete_path' => 'islandora/xacml/mimeautocomplete/' . $object->id,
      '#size' => 35,
      '#description' => $this->t('Type "*" to list all MIME types.'),
      '#prefix' => '<div class="islandora_xacml_block_description">',
      '#name' => 'mime_add_textfield',
      '#ajax' => [
        'event' => 'error',
        'callback' => 'islandora_xacml_editor_add_mime_js',
        'wrapper' => 'islandora_xacml_dsid_mime',
        'keypress' => TRUE,
      ],
    ];
    $form['dsid_mime']['new_mime_add'] = [
      '#name' => 'mime_add_button',
      '#type' => 'button',
      '#value' => $this->t('Add'),
      '#suffix' => '</div>',
      '#ajax' => [
        'event' => 'click',
        'callback' => 'islandora_xacml_editor_add_mime_js',
        'wrapper' => 'islandora_xacml_dsid_mime',
      ],
    ];

    if (\Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_show_mimeregex')) {
      $form['dsid_mime']['mime_regex'] = [
        '#type' => 'textfield',
        '#title' => $this->t('MIME type Regex'),
        '#description' => \Drupal::l($this->t('XML regex'), Url::fromUri('http://www.w3.org/TR/xmlschema-0/#regexAppendix')),
        '#size' => 35,
        '#prefix' => '<div class="islandora_xacml_block">',
        '#name' => 'mime_regex_add_textfield',
        '#ajax' => [
          'event' => 'error',
          'callback' => 'islandora_xacml_editor_add_mime_regex_js',
          'wrapper' => 'islandora_xacml_dsid_mime',
          'method' => 'replace',
          'keypress' => TRUE,
        ],
      ];
      $form['dsid_mime']['mime_regex_add'] = [
        '#name' => 'mime_regex_add_button',
        '#type' => 'button',
        '#value' => $this->t('Add'),
        '#suffix' => '</div>',
        '#ajax' => [
          'event' => 'click',
          'callback' => 'islandora_xacml_editor_add_mime_regex_js',
          'wrapper' => 'islandora_xacml_dsid_mime',
          'method' => 'replace',
        ],
      ];
    }
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Set Permissions'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // @FIXME
// The Assets API has totally changed. CSS, JavaScript, and libraries are now
// attached directly to render arrays using the #attached property.
//
//
// @see https://www.drupal.org/node/2169605
// @see https://www.drupal.org/node/2408597
// drupal_add_css(drupal_get_path('module', 'islandora_xacml_editor') . '/css/islandora_xacml_editor.css');

    $button_trig = [
      'dsid_add_button',
      'mime_add_button',
      'dsid_regex_add_button',
      'mime_regex_add_button',
    ];
    // Test if the user is locking themselves or the admin out of the object.
    $admin_user = $form_state->get([
      'islandora_xacml',
      'admin_user',
    ]);
    $current_user = $form_state->get(['islandora_xacml', 'current_user']);

    // Management functions.
    if ($form_state->getValue(['manage_enabled'])) {
      if (!array_key_exists($admin_user, $form_state->getValue([
        'manage',
        'users',
      ])) || !array_key_exists($current_user, $form_state->getValue([
        'manage',
        'users',
      ]))) {
        if ($admin_user == $current_user) {
          $form_state->setErrorByName('manage][users', "Please make sure that $admin_user is selected in the manage
          section to prevent locking yourself out of the object.");
        }
        else {
          $form_state->setErrorByName('manage][users', "Please make sure that $admin_user and $current_user are selected in the manage
          section to prevent locking yourself and the admin user out of the object.");
        }
      }
    }

    if ($form_state->getValue(['dsid_mime_enabled'])) {
      if (!array_key_exists($current_user, $form_state->getValue([
        'dsid_mime',
        'users',
      ]))) {
        if ($admin_user == $current_user) {
          $form_state->setErrorByName('dsid_mime][users', "Please make sure that $admin_user is selected in the manage
        section to prevent locking yourself out of the object.");
        }
        else {
          $form_state->setErrorByName('dsid_mime][users', "Please make sure that $admin_user and $current_user are selected in the manage
        section to prevent locking yourself and the admin user out of the object.");
        }
      }

      if (count($form_state->get(['islandora_xacml', 'rows'])) == 0 && (!in_array($form_state->getTriggeringElement(), $button_trig))) {
        $form_state->setErrorByName('dsid_mime][rules', "There are no filters applied in the datastream and MIME type section.");
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $object = islandora_object_load($form_state->get(['islandora_xacml', 'pid']));
    $pid = $object->id;
    $xacml = new IslandoraXacml($object);

    // Check datastreams and mime.
    $values = $form_state->getValue(['dsid_mime']);

    $xacml->datastreamRule->clear();
    if ($form_state->getValue(['dsid_mime_enabled'])) {
      if (array_key_exists('selected_mime', $form_state->get(['islandora_xacml']))) {
        $xacml->datastreamRule->addMimetype($form_state->get(['islandora_xacml', 'selected_mime']));
      }

      if (array_key_exists('selected_dsid', $form_state->get(['islandora_xacml']))) {
        $xacml->datastreamRule->addDsid($form_state->get(['islandora_xacml', 'selected_dsid']));
      }

      if (array_key_exists('selected_mime_regexs', $form_state->get(['islandora_xacml']))) {
        $xacml->datastreamRule->addMimetypeRegex($form_state->get(['islandora_xacml', 'selected_mime_regexs']));
      }

      if (array_key_exists('selected_dsid_regexs', $form_state->get(['islandora_xacml']))) {
        $xacml->datastreamRule->addDsidRegex($form_state->get(['islandora_xacml', 'selected_dsid_regexs']));
      }

      $xacml->datastreamRule->addUser($values['users']);
      $xacml->datastreamRule->addRole($values['roles']);
    }

    // Check admin (always have this rule).
    $values = $form_state->getValue(['manage']);
    $xacml->managementRule->clear();
    if ($form_state->getValue(['manage_enabled'])) {
      $xacml->managementRule->addUser($values['users']);
      $xacml->managementRule->addRole($values['roles']);
    }

    // Check access.
    $values = $form_state->getValue(['access']);
    $xacml->viewingRule->clear();
    if ($form_state->getValue(['access_enabled'])) {
      $xacml->viewingRule->addUser($values['users']);
      $xacml->viewingRule->addRole($values['roles']);
    }

    $xacml->writeBackToFedora();

    $form_state->set(['redirect'], ['islandora/object/' . $pid]);
    if (!$form_state->get(['islandora_xacml', 'query_choices']) && $form_state->getValue(['update_options']) != 'newchildren') {
      $option = $form_state->getValue(['update_options']);
      $query_array = $form_state->get(
        ['islandora_xacml', 'query_choices', $option]
      );
      $xml = $xacml->getXmlString();
      $batch = [
        'title' => $this->t('Updating Policies'),
        'progress_message' => $this->t('Please wait if many objects are being updated this could take a few minutes.'),
        'operations' => [
          [
            'islandora_xacml_editor_batch_function',
            [
              $xml,
              $pid,
              $query_array,
            ],
          ],
        ],
        'finished' => 'islandora_xacml_editor_batch_finished',
        'file' => drupal_get_path('module', 'islandora_xacml_editor') . '/includes/batch.inc',
      ];
      batch_set($batch);
    }
    else {
      unset($form_state->get(['islandora_xacml']));
      drupal_set_message($this->t('The configured POLICY datastream has been applied to @pid!', [
        '@pid' => $pid,
      ]));
    }
  }

}
