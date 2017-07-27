<?php
    return function($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance){
        require_once 'send_rx_functions.php';
        require_once 'RxSender.php';

        global $Proj;

        /*
            Auto create a DAG in patients project when a new site is created.
            Link this DAG Id to the site created.
        */
        if (!$config = send_rx_get_project_config($project_id, 'pharmacy')) {
            return;
        }
        if (isset($Proj->metadata['send_rx_pharmacy_name']) && $Proj->metadata['send_rx_pharmacy_name']['form_name'] == $instrument) {
            $patient_project_id = $config->targetProjectId;
            $app_path_webroot = APP_PATH_WEBROOT;
            $data = REDCap::getData($project_id, 'array', null, null);
            if (empty($data[$record][$event_id]['send_rx_dag_id'])) {
                $action = 'add';
            } else {
                $action = 'rename';
            }
            $site_name = $data[$record][$event_id]['send_rx_pharmacy_name'];

            /*
                Use curl to make an ajax call to save DAG to patients project.
            */
            $url = $app_path_webroot.'DataAccessGroups/data_access_groups_ajax.php?pid='.$patient_project_id.'&action='.$action.'&item='.$site_name;
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $url
            ));
            $response = curl_exec($curl);

            /*
                Get newly created DAG Id from database and save it to redcap data.
            */
            if ($response && $action == 'add') {
                $sql = 'SELECT Max(group_id) FROM redcap_data_access_groups WHERE project_id = $project_id AND group_name = "'.db_escape($site_name).'"';
                $q = db_query($sql);
                if (db_num_rows($q)) {
                    $dag_id = db_fetch_assoc($q);
                    // Save DAG Id to redcap data explicitly when a new site is created.
                    send_rx_save_record_field($project_id, $event_id, $record, 'send_rx_dag_id', $dag_id, null);
                } 
            }
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
        send_rx_save_record_field($project_id, $event_id, $record, $field_name, "0", $repeat_instance);
    };
?>
