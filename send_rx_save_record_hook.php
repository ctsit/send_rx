<?php
    return function($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
        require_once 'send_rx_functions.php';
        require_once 'RxSender.php';

        global $Proj;

        /*
            Auto create a DAG in patients project when a new site is created.
            Link this DAG Id to the site created.
        */
        if ($config = send_rx_get_project_config($project_id, 'site')) {
            if (!isset($Proj->metadata['send_rx_dag_id']) || $Proj->metadata['send_rx_dag_id']['form_name'] != $instrument) {
                return;
            }

            global $redcap_version;

            $data = send_rx_get_record_data($project_id, $record, $event_id);
            $site_name = $data['send_rx_site_name'];

            $action = empty($data['send_rx_dag_id']) ? 'add' : 'rename';

            // Call endpoint responsible to create or rename the DAG.
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_URL => APP_PATH_WEBROOT_FULL . 'redcap_v' . $redcap_version . '/DataAccessGroups/data_access_groups_ajax.php?pid=' . $config->targetProjectId . '&action=' . $action . '&item=' . urlencode($site_name),
                CURLOPT_COOKIE => 'PHPSESSID=' . $_COOKIE['PHPSESSID'],
                CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
            ));

            // Get newly created DAG Id from database and save it to redcap data.
            if (curl_exec($curl) && curl_getinfo($curl, CURLINFO_HTTP_CODE) == 200 && $action == 'add') {
                $sql = '
                    SELECT group_id FROM redcap_data_access_groups
                    WHERE project_id = ' . $config->targetProjectId . ' AND group_name = "' . db_escape($site_name) . '"
                    ORDER BY group_id LIMIT 1';

                $q = db_query($sql);
                if (db_num_rows($q)) {
                    $result = db_fetch_assoc($q);
                    // Save DAG Id to redcap data explicitly when a new site is created.
                    send_rx_save_record_field($project_id, $event_id, $record, 'send_rx_dag_id', $result['group_id']);
                } 
            }

            return;
        }

        // Checking if PDF file exists.
        if (!isset($Proj->metadata['send_rx_pdf'])) {
            return;
        }

        // Getting Rx sender to make sure we are in a patient project.
        if (!$sender = RxSender::getSender($project_id, $event_id, $record)) {
            return;
        }

        // Checking if we are on PDF form step.
        if ($Proj->metadata['send_rx_pdf']['form_name'] == $instrument) {
            // Send prescription.
            $sender->send(false);
            return;
        }

        /*
            New PDF needs to be generated as changes are made to form after PDF is created.
            Reset pdf_is_updated flag to generate new PDF.
        */
        $field_name = 'send_rx_pdf_is_updated';
        send_rx_save_record_field($project_id, $event_id, $record, $field_name, '0', $repeat_instance);
    };
?>
