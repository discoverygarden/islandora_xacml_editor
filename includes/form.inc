<?php

/**
 * @file
 * Houses the forms used in the Islandora XACML Editor.
 */

/**
 * Retrieves the users selected in each 'Users' portion for datastream rules.
 *
 * @param Xacml $xacml
 *   The XACML object.
 * @param bool $new_xacml
 *   Whether there already exists an XACML policy or not.
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
 * @param bool $new_xacml
 *   Whether there already exists an XACML policy or not.
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
