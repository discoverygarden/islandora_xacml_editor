<?php

/**
 * @file
 * Drush hook implementations and associated functions.
 */

use Drupal\islandora_xacml_api\IslandoraXacml;

/**
 * Implements hook_drush_command().
 */
function islandora_xacml_api_drush_command() {
  $command = [];

  $command['islandora-xacml-api-relationships-update-batch'] = [
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'options' => [
      'limit' => [
        'value' => 'optional',
        'description' => dt('The max number of objects for which to query at a time. (affects memory usage, defaults to 100)'),
      ],
    ],
    'drupal dependencies' => [
      'islandora',
      'islandora_xacml_api',
    ],
    'examples' => [
      'drush -u 1 islandora-xacml-api-relationships-update-batch',
    ],
  ];
  $command['islandora-xacml-api-relationships-missing-rels-int-batch'] = [
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'aliases' => [
      'ixarmrib',
    ],
    'options' => [
      'limit' => [
        'value' => 'optional',
        'description' => dt('The max number of objects for which to query at a time. (affects memory usage, defaults to 100)'),
      ],
      'commit' => [
        'description' => dt('Whether to commit the changes to the relations of the POLICY. Defaults to FALSE.'),
        'required' => FALSE,
      ],
    ],
    'drupal dependencies' => [
      'islandora',
      'islandora_xacml_api',
    ],
    'examples' => [
      'drush -u 1 -v islandora-xacml-api-relationships-missing-rels-int-batch',
    ],
  ];
  return $command;
}

/**
 * Command callback; start the update batch.
 */
function drush_islandora_xacml_api_relationships_update_batch() {
  $batch = [
    'operations' => [
      [
        'islandora_xacml_api_relationships_update_batch_operation',
        [
          drush_get_option('limit', 100),
        ],
      ],
    ],
  ];

  batch_set($batch);

  drush_backend_batch_process();
}

/**
 * Batch operation; update a PID.
 */
function islandora_xacml_api_relationships_update_batch_operation($limit, &$context) {
  if (!\Drupal::config('islandora_xacml_api.settings')->get('islandora_xacml_api_save_relationships')) {
    drush_log(t('Relationships are disabled, nothing to do.'));
  }
  $sandbox =& $context['sandbox'];

  // XXX: Can occasionally get be initialized as a different user...
  drupal_static_reset('islandora_get_tuque_connection');
  $tuque = islandora_get_tuque_connection();

  $query = <<<EOQ
SELECT ?object ?created
FROM <#ri>
WHERE {
  ?object <fedora-view:disseminates> ?ds ;
          <fedora-model:createdDate> ?created .
  ?ds <fedora-view:disseminationType> <info:fedora/*/POLICY> .
  !filter
}
ORDER BY ?created

EOQ;

  if (!isset($sandbox['total'])) {
    $sandbox['progress'] = 0;
    $sandbox['total'] = $tuque->repository->ri->countQuery(strtr($query, [
      '!filter' => '',
    ]), 'sparql');
    if ($sandbox['total'] == 0) {
      // Nothing to process.
      $context['finished'] = 1;

      $context['message'] = t('Nothing to fix.');
      return;
    }
    $sandbox['result_stash'] = [];
  }

  if (empty($sandbox['result_stash'])) {
    $limited_query = strtr($query, [
      '!filter' => (isset($sandbox['last_date']) ?
        "FILTER(?created > \"{$sandbox['last_date']}\"^^<xsd:dateTime>)" :
        ''),
    ]) . <<<EOQ
LIMIT $limit
EOQ;

    drush_log(dt('Querying for @count objects.', ['@count' => $limit]));
    $sandbox['result_stash'] = $tuque->repository->ri->sparqlQuery($limited_query);
    if (empty($sandbox['result_stash'])) {
      // Ran out of items early?
      $context['finished'] = 1;

      $context['message'] = t('Updated @count PID(s) and ran out of items early... Somebody manually updated a document?', [
        '@count' => $sandbox['progress'],
      ]);
      return;
    }
  }

  $result = array_shift($sandbox['result_stash']);
  $updating_pid = $result['object']['value'];
  $sandbox['last_date'] = $result['created']['value'];
  $object_to_update = islandora_object_load($updating_pid);

  module_load_include('inc', 'islandora_xacml_api', 'includes/islandora_xacml');
  $xacml = new IslandoraXacml($object_to_update, $object_to_update['POLICY']->content);
  $xacml->writeBackToFedora();

  \Drupal::logger('islandora_xacml_api')->notice('Rewrote policy and relationships for @pid.', [
    '@pid' => $updating_pid,
  ]);

  $sandbox['progress'] = min($sandbox['total'], $sandbox['progress'] + 1);
  $context['finished'] = $sandbox['progress'] / $sandbox['total'];

  $context['message'] = t('Updated @progress of @total.', [
    '@progress' => $sandbox['progress'],
    '@total' => $sandbox['total'],
  ]);
}

/**
 * Drush command callback for RELS-INT inconsistencies batch.
 */
function drush_islandora_xacml_api_relationships_missing_rels_int_batch() {
  $batch = [
    'operations' => [
      [
        'islandora_xacml_api_relationships_missing_rels_int_update_batch_operation',
        [
          drush_get_option('limit', 100),
          drush_get_option('commit', FALSE),
        ],
      ],
    ],
  ];

  batch_set($batch);

  drush_backend_batch_process();
}

/**
 * Batch operation; identify and update a PID.
 */
function islandora_xacml_api_relationships_missing_rels_int_update_batch_operation($limit, $commit, &$context) {
  if (!\Drupal::config('islandora_xacml_api.settings')->get('islandora_xacml_api_save_relationships')) {
    drush_log(t('Relationships are disabled, nothing to do.'));
  }
  $sandbox =& $context['sandbox'];

  // XXX: Can occasionally get be initialized as a different user...
  drupal_static_reset('islandora_get_tuque_connection');
  $tuque = islandora_get_tuque_connection();

  $query = <<<EOQ
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
SELECT DISTINCT ?object ?created FROM <#ri>
WHERE {
  ?object <fedora-view:disseminates> ?ds ;
          <fedora-view:disseminates> ?ds2 ;
          <fedora-model:createdDate> ?created .
  ?ds <fedora-view:disseminationType> <info:fedora/*/POLICY> .
  {
    ?ds2 <http://islandora.ca/ontology/relsint#isViewableByRole> "administrator"
  }
  UNION
  {
    ?ds2 <http://islandora.ca/ontology/relsint#isViewableByUser> "fedoraAdmin"
  }
  !filter
}
ORDER BY ?created ?pid

EOQ;
  if (!isset($sandbox['total'])) {
    $sandbox['progress'] = 0;
    $sandbox['total'] = $tuque->repository->ri->countQuery(strtr($query, [
      '!filter' => '',
    ]), 'sparql');
    if ($sandbox['total'] == 0) {
      $context['finished'] = 1;
      $context['message'] = t('Nothing to verify.');
      return;
    }
    $sandbox['result_stash'] = [];
  }
  if (empty($sandbox['result_stash'])) {
    if (isset($sandbox['last_date'])) {
      $filter = strtr('FILTER(?created > "!created"^^xsd:dateTime || (?created = "!created"^^xsd:dateTime && xsd:string(?object) > "info:fedora/!pid"^^xsd:string))', [
        '!created' => $sandbox['last_date'],
        '!pid' => $sandbox['last_pid'],
      ]);
    }
    $limited_query = strtr($query, [
      '!filter' => isset($filter) ? $filter : '',
    ]) . <<<EOQ
LIMIT $limit
EOQ;

    drush_log(dt('Querying for @count objects.', ['@count' => $limit]));
    $sandbox['result_stash'] = $tuque->repository->ri->sparqlQuery($limited_query);
    if (empty($sandbox['result_stash'])) {
      $context['finished'] = 1;
      $context['message'] = t('Updated @count PID(s) and ran out of items early. POLICY was manually removed?', [
        '@count' => $sandbox['progress'],
      ]);
      return;
    }
  }

  $result = array_shift($sandbox['result_stash']);
  $sandbox['last_date'] = $result['created']['value'];
  $sandbox['last_pid'] = $result['object']['value'];
  $object = islandora_object_load($result['object']['value']);
  drush_log(dt('Processing @pid.', [
    '@pid' => $object->id,
  ]));
  // Get the POLICY and compare it to the RELS-INT statements.
  $xacml = new IslandoraXacml($object);
  $to_update = FALSE;
  $datastream_data = $xacml->datastreamRule->getRuleArray();
  foreach ($datastream_data['dsids'] as $dsid) {
    // Only check DSIDs that exist on the object given the nature of how the API
    // writes RELS-INT statements when that datastream gets ingested.
    if (isset($object[$dsid])) {
      // Ensure the users and roles match up.
      foreach ($datastream_data['users'] as $user) {
        $user_rels = $object[$dsid]->relationships->get(ISLANDORA_RELS_INT_URI, 'isViewableByUser', $user, RELS_TYPE_PLAIN_LITERAL);
        if (!$user_rels) {
          $to_update = TRUE;
          break 2;
        }
      }
      foreach ($datastream_data['roles'] as $role) {
        $role_rels = $object[$dsid]->relationships->get(ISLANDORA_RELS_INT_URI, 'isViewableByRole', $role, RELS_TYPE_PLAIN_LITERAL);
        if (!$role_rels) {
          $to_update = TRUE;
          break 2;
        }
      }
    }
  }
  if ($to_update) {
    drush_log(dt('The POLICY for @pid is missing RELS-INT statements.', [
      '@pid' => $object->id,
    ]));
    if ($commit) {
      drush_log(dt('Updating the RELS-INT statements for @pid.', [
        '@pid' => $object->id,
      ]));
      $xacml->writeBackToFedora();
    }
  }
  $sandbox['progress'] = min($sandbox['total'], $sandbox['progress'] + 1);
  $context['finished'] = $sandbox['progress'] / $sandbox['total'];
  $context['message'] = t('Checked @progress of @total.', [
    '@progress' => $sandbox['progress'],
    '@total' => $sandbox['total'],
  ]);
}
