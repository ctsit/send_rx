<?php
	return function($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance){
   		require_once "send_rx_get_project_config.php";

   		$last_instrument = 'send_rx_notification';
   		if($instrument == $last_instrument){
   			// Send email
   			$sender = send_rx_get_sender($project_id, $event_id, $record, USERID);
   			$sender->send();
   		} else{
   			$config = send_rx_get_project_config($project_id, 'patient');
   			/*
				Check if send_rx is configured for the project.
   			*/
   			if(!empty($config)){
	   			$data = send_rx_get_record_data($project_id, $record, $event_id);
	   			$field_name = 'send_rx_is_pdf_updated';
	   			$is_pdf_generated = $data[$field_name];
	   			/*
					New PDF needs to be generated as changes are made to form after PDF is created.
					Reset is_pdf_generated flag to generate new PDF.
   				*/
	   			if(empty($is_pdf_generated) || (!empty($is_pdf_generated) && is_bool($is_pdf_generated) == true)){
	   				$is_pdf_generated = false
	   				send_rx_save_record_field($project_id, $event_id, $record_id, $field_name, false, $repeat_instance);
	   			}
	   		}
   		}
	};
?>