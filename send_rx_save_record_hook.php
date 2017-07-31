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

            $data = send_rx_get_record_data($project_id, $record, $event_id);
            if (($group_id = send_rx_save_dag($config->targetProjectId, $data['send_rx_site_name'], $data['send_rx_dag_id'])) && !$data['send_rx_dag_id']) {
                send_rx_save_record_field($project_id, $event_id, $record, 'send_rx_dag_id', $group_id);
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
