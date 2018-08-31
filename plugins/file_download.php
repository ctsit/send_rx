<?php

// Making sure project is defined.
if (!defined('PROJECT_ID')) {
    return;
}

// Checking for required fields.
foreach (['record', 'event_id', 'field_name'] as $key) {
    if (empty($_GET[$key])) {
        return;
    }
}

global $Proj;

// Making sure field and event are valid.
if (
    !isset($Proj->metadata[$_GET['field_name']]) ||
    !isset($Proj->eventInfo[$_GET['event_id']]) ||
    $Proj->metadata[$_GET['field_name']]['element_type'] != 'file'
) {
    return;
}

// Making sure record exists.
if (!Records::recordExists(PROJECT_ID, $_GET['record'])) {
    return;
}

$data = REDCap::getData(PROJECT_ID, 'array', $_GET['record'], $_GET['field_name'], $_GET['event_id']);
if (empty($data[$_GET['record']][$_GET['event_id']][$_GET['field_name']])) {
    return;
}

$file_id = intval($data[$_GET['record']][$_GET['event_id']][$_GET['field_name']]);
$params = [
    'pid' => PROJECT_ID,
    'record' => $_GET['record'],
    'event_id' => $_GET['event_id'],
    'instance' => 1,
    'field_name' => $_GET['field_name'],
    'id' => $file_id,
    'doc_id_hash' => Files::docIdHash($file_id),
];

redirect(APP_PATH_WEBROOT . 'DataEntry/file_download.php?' . http_build_query($params));
