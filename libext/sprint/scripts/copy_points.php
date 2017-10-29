#!/usr/bin/env php
<?php

// See <https://secure.phabricator.com/T10350> for discussion.

require_once 'scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->parseStandardArguments();
$args->parse(
    array(
        array(
            'name'  => 'field',
            'param' => 'key',
            'help'  => pht('Field to migrate.'),
        ),
    ));

$task = new ManiphestTask();
$fields = PhabricatorCustomField::getObjectFields(
    $task,
    PhabricatorCustomField::ROLE_EDIT);

$field_map = $fields->getFields();
$field_list = implode(', ', array_keys($field_map));

if (!$field_map) {
  throw new PhutilArgumentUsageException(
      pht(
          'You do not have any custom fields defined in Maniphest, so there is '.
          'nowhere that points can be copied from.'));
}

$field_key = $args->getArg('field');
if (!strlen($field_key)) {
  throw new PhutilArgumentUsageException(
      pht(
          'Use --field to specify which field to copy points from. Available '.
          'fields are: %s.',
          $field_list));
}

$field = idx($field_map, $field_key);
if (!$field) {
  throw new PhutilArgumentUsageException(
      pht(
          'Field "%s" is not a valid field. Available fields are: %s.',
          $field_key,
          $field_list));
}

$proxy = $field->getProxy();
if (!$proxy) {
  throw new PhutilArgumentUsageException(
      pht(
          'Field "%s" is not a standard custom field, and can not be migrated.',
          $field_key,
          $field_list));
}

if (!($proxy instanceof PhabricatorStandardCustomFieldText)) {
  throw new PhutilArgumentUsageException(
      pht(
          'Field "%s" is not an "int" field, and can not be migrated.',
          $field_key,
          $field_list));
}

$storage = $field->newStorageObject();
$conn_r = $storage->establishConnection('r');

$value_rows = queryfx_all(
    $conn_r,
    'SELECT objectPHID, fieldValue FROM %T WHERE fieldIndex = %s
    AND fieldValue IS NOT NULL',
    $storage->getTableName(),
    $field->getFieldIndex());
$value_map = ipull($value_rows, 'fieldValue', 'objectPHID');

$id_rows = queryfx_all(
    $conn_r,
    'SELECT phid, id, points FROM %T',
    $task->getTableName());
$id_map = ipull($id_rows, null, 'phid');

foreach ($value_map as $phid => $value) {
  $dict = idx($id_map, $phid, array());
  $id = idx($dict, 'id');
  $current_points = idx($dict, 'points');

  if (!$id) {
    echo 'ID NULL';
    continue;
  }

  if ($current_points !== null) {
    echo 'HAS POINTS';
    continue;
  }

  if ($value === null) {
    echo 'VALUE NULL';
    continue;
  }

  $sql = qsprintf(
      $conn_r,
      'UPDATE %T.%T SET points = %f WHERE id = %d;',
      'phabricator_maniphest',
      $task->getTableName(),
      $value,
      $id);

  echo $sql."\n";
}
