<?php

global $isAjax;

if ($isAjax && !empty($_POST['errors']) && defined('PROJECT_ID') && ($config = send_rx_get_project_config(PROJECT_ID, 'site'))) {
    $errors = json_encode($_POST['errors']);

    foreach (array(PROJECT_ID => ' on patient project', $config['target_project_id'] => '') as $project_id => $suffix) {
        REDCap::logEvent('Send Rx role assignments failed' . $suffix, $errors, '', null, null, $project_id);
    }
}
