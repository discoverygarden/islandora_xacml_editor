<?php

/**
 * @file
 * Houses the forms used in the Islandora XACML Editor.
 */

use Drupal\islandora_xacml_api\Xacml;
use Drupal\Core\Render\Element;

/**
 * Retrieves the users selected in each 'Users' portion for datastream rules.
 *
 * @param Drupal\islandora_xacml_api\Xacml $xacml
 *   The XACML object.
 * @param bool $new_xacml
 *   Whether there already exists an XACML policy or not.
 * @param string $rule
 *   The rule which we are checking against.
 *
 * @return array
 *   An array of users that are selected for the specific rule.
 */
function islandora_xacml_editor_retrieve_users(Xacml $xacml, $new_xacml, $rule) {
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
 * @param Drupal\islandora_xacml_api\Xacml $xacml
 *   The XACML object.
 * @param bool $new_xacml
 *   Whether there already exists an XACML policy or not.
 * @param string $rule
 *   The rule which we are checking against.
 *
 * @return array
 *   An array of roles that are selected for the specific rule.
 */
function islandora_xacml_editor_retrieve_roles(Xacml $xacml, $new_xacml, $rule) {
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
  $headers = [
    'filter' => t('Filter'),
    'type' => t('Type'),
  ];

  $rows = [];
  foreach ($passed_rows as $key => $value) {
    $filter = $value['Filter'];
    $type = $value['Type'];
    $rows[$key] = [
      'filter' => $filter,
      'type' => $type,
    ];
  }

  $table = [
    '#type' => 'tableselect',
    '#header' => $headers,
    '#options' => $rows,
  ];
  return $table;
}

/**
 * Theme the XACML policy management table.
 *
 * @param array $variables
 *   Variables passed to this theme function.
 *
 * @return string
 *   Markup representing the table for rendering.
 */
function islandora_xacml_editor_policy_management_table(array $variables) {
  $row_elements = $variables['table']['rows'];

  $rows = [];
  foreach (Element::children($row_elements) as $key) {
    $columns = [];
    $row_element = $row_elements[$key];
    foreach (Element::children($row_element) as $key) {
      $column_element = $row_element[$key];
      $columns[] = [
        'data' => \Drupal::service('renderer')->render($column_element),
      ];
    }
    $rows[] = $columns;
  }
  $return = [
    '#type' => 'table',
    '#header' => $variables['table']['#header'],
    '#attached' => ['library' => ['core/drupal.tableselect']],
    '#rows' => $rows,
    '#attributes' => $variables['table']['#attributes'],
    'caption' => NULL,
    'colgroups' => NULL,
    'sticky' => NULL,
    '#empty' => t('No child collection(s).'),
  ];
  return \Drupal::service('renderer')->render($return);
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
