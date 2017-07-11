<?php
	return function($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance){
		//require_once "RXSender.php";
		//require_once "send_rx_functions.php";
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
			Check if the current instrument is the last instrument of the event.
			1. Get all the data using getData developer method.
			2. Get data for the current event.
			3. Get list of all instruments from the data and store them in an array.
			4. The instrument at the last index of the array is the last instrument of the event.
			
			Check if the current instrument is complete.
		*/
		$instrument_array = array();
		foreach ($decoded_data as $item) {
			$unique_event_name = $item->redcap_event_name;
			if($unique_event_name == $redcap_event_name){
				foreach ($item as $key => $value) {
					if(stripos('_complete', $key) !== false){
						$instrument_array[] = $key;
					}
				}
				$array_keys = array_keys($instrument_array);
				$last_key = end($array_keys);
				if($last_key == $instrument_complete){
					$is_instrument_last = true;
				}
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
			Do not proceed if current instrument (i.e., last instrument) is not complete.
		*/
		if(!$is_instrument_complete){
			return;
		}

		$config = rx_send_get_project_config($project_id, "patient");

		if(empty($config)){
			return;
		}

		class Sender extends RXSender{

			function buildPrescriptionTable(){

			}
		}

		$sender = new Sender($project_id, $config, $redcap_event_name, $record, $username); 

		$isSecure = $sender->checkSecurityToken();
		if($isSecure == FALSE){
			return;
		}

		$sender->send();
	};
	
?>