<?php

global $isAjax;

if ($isAjax && !empty($_POST['errors']) && defined('PROJECT_ID') && ($config = send_rx_get_project_config(PROJECT_ID, 'site'))) {
    REDCap::logEvent('Roles assigning failed', json_encode($_POST['errors']), '', null, null, $config['target_project_id']);
}
