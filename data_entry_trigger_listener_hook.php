<?php
	return function($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance){
		require_once "send_rx_functions.php";

		$username = USERID;
		parse_str($_SERVER['QUERY_STRING'], $qs_params);
		$instrument = $qs_params['page'];
		$redcap_event_name = REDCap::getEventNames(true, false, $event_id);

		$get_data = REDCap::getData('JSON');
		$decoded_data = json_decode($get_data);
		$instrument_complete = $instrument.'_complete';

		$is_instrument_complete = false;
		$is_instrument_last = false;

		/*
			Check if current instrument is the last instrument of the event.
		*/
		global $Proj;
		$arr = $Proj->eventsForms;
		$last_key_index = end(array_keys($arr[$event_id]));
		if($arr[$event_id][$last_key_index] == $instrument){
			$is_instrument_last = true;
		}

		/*
			Check if current instrument is complete.
		*/
		foreach ($decoded_data as $item) {
			$unique_event_name = $item->redcap_event_name;
			if($unique_event_name == $redcap_event_name){
				if($instrument_complete == '2'){
					$is_instrument_complete = true;
				}
			}
		}

		/*
			Do not proceed if current instrument is not the last instrument.
		*/
		if(!$is_instrument_last){
			return;
		}

		/*
			Do not proceed if current instrument (i.e., last instrument) is incomplete.
		*/
		if(!$is_instrument_complete){
			return;
		}

		$config = rx_send_get_project_config($project_id, "patient");

		if(empty($config)){
			return;
		}


		$sender = send_rx_get_sender($project_id, $config, $redcap_event_name, $record, $username); 

		//ToDo : Additional check to call this function based on the button clicked.
		$sender->send();
	};
	
?>