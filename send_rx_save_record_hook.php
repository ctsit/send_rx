<?php
    return function($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance){
        require_once 'send_rx_functions.php';
        require_once 'RxSender.php';

        if (!$sender = RxSender::getSender($project_id, $event_id, $record)) {
            return;
        }

        $last_instrument = 'send_rx_review';
        if ($instrument == $last_instrument) {
            // Send prescription.
            $sender->send();
            return;
        }

        /*
            New PDF needs to be generated as changes are made to form after PDF is created.
            Reset pdf_is_updated flag to generate new PDF.
        */
        $field_name = 'send_rx_pdf_is_updated';
        send_rx_save_record_field($project_id, $event_id, $record, $field_name, false, $repeat_instance);
    };
?>
