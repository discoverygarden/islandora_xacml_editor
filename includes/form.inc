<?php

/**
 * @file
 * Houses the forms used in the Islandora XACML Editor.
 */

use Drupal\islandora_xacml_api\IslandoraXacml;
use Drupal\islandora_xacml_api\Xacml;
use Drupal\islandora_xacml_api\XacmlException;

/**
 * Retrieves the users selected in each 'Users' portion for datastream rules.
 *
 * @param Xacml $xacml
 *   The XACML object.
 *
 * @param bool $new_xacml
 *   Whether there already exists an XACML policy or not.
 *
 * @param string $rule
 *   The rule which we are checking against.
 *
 * @return array
 *   An array of users that are selected for the specific rule.
 */
function islandora_xacml_editor_retrieve_users($xacml, $new_xacml, $rule) {
  if ($new_xacml == FALSE) {
    if ($rule == 'viewing' && $xacml->viewingRule->isPopulated()) {
      return $xacml->viewingRule->getUsers();
    }
    elseif ($rule == 'datastream' && $xacml->datastreamRule->isPopulated()) {
      return $xacml->datastreamRule->getUsers();
    }
    elseif ($rule == 'management' && $xacml->managementRule->isPopulated()) {
      return $xacml->managementRule->getUsers();
    }
    else {
      return \Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_default_users');
    }
  }
  else {
    return \Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_default_users');
  }
}

/**
 * Retrieves the roles selected in each 'Roles' portion for datastream rules.
 *
 * @param Xacml $xacml
 *   The XACML object.
 *
 * @param bool $new_xacml
 *   Whether there already exists an XACML policy or not.
 *
 * @param string $rule
 *   The rule which we are checking against.
 *
 * @return array
 *   An array of roles that are selected for the specific rule.
 */
function islandora_xacml_editor_retrieve_roles($xacml, $new_xacml, $rule) {
  if ($new_xacml == FALSE) {
    if ($rule == 'viewing' && $xacml->viewingRule->isPopulated()) {
      return $xacml->viewingRule->getRoles();
    }
    elseif ($rule == 'datastream' && $xacml->datastreamRule->isPopulated()) {
      return $xacml->datastreamRule->getRoles();
    }
    elseif ($rule == 'management' && $xacml->managementRule->isPopulated()) {
      return $xacml->managementRule->getRoles();
    }
    else {
      return \Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_default_roles');
    }
  }
  else {
    return \Drupal::config('islandora_xacml_editor.settings')->get('islandora_xacml_editor_default_roles');
  }
}

/**
 * Constructs the tableselect used in slandora_xacml_editor_form().
 *
 * @param array $passed_rows
 *   The rows containing the data that need to be rendered.
 *
 * @return array
 *   An array that represents the tableselect to be rendered.
 */
function islandora_xacml_editor_form_table(array $passed_rows) {
  $headers = array(
    'filter' => t('Filter'),
    'type' => t('Type'),
  );

  $rows = array();
  foreach ($passed_rows as $key => $value) {
    $filter = $value['Filter'];
    $type = $value['Type'];
    $rows[$key] = array(
      'filter' => $filter,
      'type' => $type,
    );
  }

  $table = array(
    '#type' => 'tableselect',
    '#header' => $headers,
    '#options' => $rows,
  );
  return $table;
}

/**
 * Implements hook_validate().
 */
function islandora_xacml_editor_form_validate(&$form, &$form_state) {
  // @FIXME
// The Assets API has totally changed. CSS, JavaScript, and libraries are now
// attached directly to render arrays using the #attached property.
//
//
// @see https://www.drupal.org/node/2169605
// @see https://www.drupal.org/node/2408597
// drupal_add_css(drupal_get_path('module', 'islandora_xacml_editor') . '/css/islandora_xacml_editor.css');

  $button_trig = array(
    'dsid_add_button',
    'mime_add_button',
    'dsid_regex_add_button',
    'mime_regex_add_button',
  );
  // Test if the user is locking themselves or the admin out of the object.
  $admin_user = $form_state['islandora_xacml']['admin_user'];
  $current_user = $form_state['islandora_xacml']['current_user'];

  // Management functions.
  if ($form_state['values']['manage_enabled']) {
    if (!array_key_exists($admin_user, $form_state['values']['manage']['users']) ||
        !array_key_exists($current_user, $form_state['values']['manage']['users'])) {
      if ($admin_user == $current_user) {
        form_set_error('manage][users', "Please make sure that $admin_user is selected in the manage
          section to prevent locking yourself out of the object.");
      }
      else {
        form_set_error('manage][users', "Please make sure that $admin_user and $current_user are selected in the manage
          section to prevent locking yourself and the admin user out of the object.");
      }
    }
  }

  if ($form_state['values']['dsid_mime_enabled']) {
    if (!array_key_exists($current_user, $form_state['values']['dsid_mime']['users'])) {
      if ($admin_user == $current_user) {
        form_set_error('dsid_mime][users', "Please make sure that $admin_user is selected in the manage
        section to prevent locking yourself out of the object.");
      }
      else {
        form_set_error('dsid_mime][users', "Please make sure that $admin_user and $current_user are selected in the manage
        section to prevent locking yourself and the admin user out of the object.");
      }
    }

    if (count($form_state['islandora_xacml']['rows']) == 0 && (!in_array($form_state['triggering_element']['#name'], $button_trig))) {
      form_set_error('dsid_mime][rules', "There are no filters applied in the datastream and MIME type section.");
    }
  }
}

/**
 * The submit function where all the XACML magic happens. Abracadabra.
 */
function islandora_xacml_editor_form_submit(&$form, &$form_state) {
  $object = islandora_object_load($form_state['islandora_xacml']['pid']);
  $pid = $object->id;
  $xacml = new IslandoraXacml($object);

  // Check datastreams and mime.
  $values = $form_state['values']['dsid_mime'];

  $xacml->datastreamRule->clear();
  if ($form_state['values']['dsid_mime_enabled']) {
    if (array_key_exists('selected_mime', $form_state['islandora_xacml'])) {
      $xacml->datastreamRule->addMimetype($form_state['islandora_xacml']['selected_mime']);
    }

    if (array_key_exists('selected_dsid', $form_state['islandora_xacml'])) {
      $xacml->datastreamRule->addDsid($form_state['islandora_xacml']['selected_dsid']);
    }

    if (array_key_exists('selected_mime_regexs', $form_state['islandora_xacml'])) {
      $xacml->datastreamRule->addMimetypeRegex($form_state['islandora_xacml']['selected_mime_regexs']);
    }

    if (array_key_exists('selected_dsid_regexs', $form_state['islandora_xacml'])) {
      $xacml->datastreamRule->addDsidRegex($form_state['islandora_xacml']['selected_dsid_regexs']);
    }

    $xacml->datastreamRule->addUser($values['users']);
    $xacml->datastreamRule->addRole($values['roles']);
  }

  // Check admin (always have this rule).
  $values = $form_state['values']['manage'];
  $xacml->managementRule->clear();
  if ($form_state['values']['manage_enabled']) {
    $xacml->managementRule->addUser($values['users']);
    $xacml->managementRule->addRole($values['roles']);
  }

  // Check access.
  $values = $form_state['values']['access'];
  $xacml->viewingRule->clear();
  if ($form_state['values']['access_enabled']) {
    $xacml->viewingRule->addUser($values['users']);
    $xacml->viewingRule->addRole($values['roles']);
  }

  $xacml->writeBackToFedora();

  $form_state['redirect'] = array('islandora/object/' . $pid);
  if (isset($form_state['islandora_xacml']['query_choices']) && $form_state['values']['update_options'] != 'newchildren') {
    $option = $form_state['values']['update_options'];
    $query_array = $form_state['islandora_xacml']['query_choices'][$option];
    $xml = $xacml->getXmlString();
    $batch = array(
      'title' => t('Updating Policies'),
      'progress_message' => t('Please wait if many objects are being updated this could take a few minutes.'),
      'operations' => array(
        array(
          'islandora_xacml_editor_batch_function',
          array($xml, $pid, $query_array),
        ),
      ),
      'finished' => 'islandora_xacml_editor_batch_finished',
      'file' => drupal_get_path('module', 'islandora_xacml_editor') . '/includes/batch.inc',
    );
    batch_set($batch);
  }
  else {
    unset($form_state['islandora_xacml']);
    drupal_set_message(t('The configured POLICY datastream has been applied to @pid!', array(
      '@pid' => $pid,
    )));
  }
}

/**
 * Theme the xacml policy management table.
 *
 * @param array $variables
 *   Variables passed to this theme function.
 *
 * @return string
 *   Markup representing the table for rendering.
 */
function theme_islandora_xacml_editor_policy_management_table(array $variables) {
  // Manually add the table select javascript as we are using a custom table.
  // @FIXME
// The Assets API has totally changed. CSS, JavaScript, and libraries are now
// attached directly to render arrays using the #attached property.
//
//
// @see https://www.drupal.org/node/2169605
// @see https://www.drupal.org/node/2408597
// drupal_add_js('misc/tableselect.js');

  $table = $variables['table'];
  $row_elements = $table['rows'];
  $rows = array();
  foreach (\Drupal\Core\Render\Element::children($row_elements) as $key) {
    $columns = array();
    $row_element = $row_elements[$key];
    foreach (\Drupal\Core\Render\Element::children($row_element) as $key) {
      $column_element = $row_element[$key];
      $columns[] = array(
        'data' => \Drupal::service("renderer")->render($column_element),
        'class' => isset($cell_element['#attributes']['class']) ? $column_element['#attributes']['class'] : NULL,
      );
    }
    $rows[] = $columns;
  }
  $variables = array(
    'header' => $table['#header'],
    'rows' => $rows,
    'attributes' => $table['#attributes'],
    'caption' => NULL,
    'colgroups' => NULL,
    'sticky' => NULL,
    'empty' => t("No child collection(s)."),
  );
  // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// return theme('table', $variables);

}
/**
 * AJAX callback to remove the selected filters from the rules table.
 */
function islandora_xacml_editor_remove_selected($form, $form_state) {
  return $form['dsid_mime']['rules'];
}

/**
 * AJAX callback to remove all filters from the rules table.
 */
function islandora_xacml_editor_remove_all($form, $form_state) {
  return $form['dsid_mime']['rules'];
}

/**
 * AJAX callback to add a DSID Regex to the rules table.
 */
function islandora_xacml_editor_add_dsid_regex_js($form, $form_state) {
  return $form['dsid_mime']['rules'];
}

/**
 * AJAX callback to add a DSID to the rules table.
 */
function islandora_xacml_editor_add_dsid_js($form, $form_state) {
  return $form['dsid_mime']['rules'];
}

/**
 * AJAX callback to add a MIME Regex to the rules table.
 */
function islandora_xacml_editor_add_mime_regex_js($form, $form_state) {
  return $form['dsid_mime']['rules'];
}

/**
 * AJAX callback to add a MIME Type to the rules table.
 */
function islandora_xacml_editor_add_mime_js($form, $form_state) {
  return $form['dsid_mime']['rules'];
}
