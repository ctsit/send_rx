<?php
	return function($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance){
		require_once "send_rx_functions.php";

		$username = USERID;
		parse_str($_SERVER['QUERY_STRING'], $qs_params);
		$instrument = $qs_params['page'];
		$redcap_event_name = REDCap::getEventNames(true, false, $event_id);

		$get_data = REDCap::getData('JSON');
		$decoded_data = json_decode($get_data);
		$instrument_complete = $instrument.'_complete';

		/*
			To check if current instrument is the last instrument of the event.
		*/
		$is_instrument_last = false;

		/*
			To check if the instrument is already saved or a new one.
			Will be used to show PDF link and Send PDF button.
		*/
		$is_saved_instrument = false;

		/*
			To check if current instance is the last instance of current instrument.
		*/
		$is_instance_last = false;

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
			Check if current instrument is already completed.
		*/
		foreach ($decoded_data as $item) {
			$unique_event_name = $item->redcap_event_name;
			if($unique_event_name == $redcap_event_name){
				//ToDo: This check fails for repeated instruments. Additional check needed for repeated instruments.
				if(!empty($instrument_complete)){
					$is_saved_instrument = true;
				}
			}
		}

		/*
			Check if current instance is last instance of instrument.
		*/
		$custom_data = REDCap::getData('array');
		if(empty($custom_data[$record]['repeat_instances'])){
			$last_instance = 1;
		} else{
			$instance_array = $custom_data[$record]['repeat_instances'][$event_id][$instrument];
			$last_instance = count($instance_array);
		}
		if($repeat_instance == $last_instance){
			$is_instance_last = true;
		}

		/*
			Do not proceed if current instrument is not the last instrument.
		*/
		if(!$is_instrument_last){
			return;
		}

		$sender = send_rx_get_sender($project_id, $redcap_event_name, $record, $username); 
		if(empty($sender)){
			return;
		}

		if($is_instrument_last && $is_instance_last){
			?>
			<script type="text/javascript">

				document.addEventListener('DOMContentLoaded', function(){
					var instrument_complete = '<?php echo $instrument_complete; ?>';
					var completeStatusEl = $('select[name="'+instrument_complete+'"]');
					var completeVal = $('select[name="'+instrument_complete+'"] option:selected').val();

					completeStatusEl.on('change', function(){
						if(completeVal != '2'){
							$('#send_pdf').attr('disabled', true);
							//ToDo: Disable/Blur entire div element (??)
						}
					});

					$('#submit-btn-saverecord, #submit-btn-savecontinue').on('click', function(){
						completeVal = $('select[name="'+instrument_complete+'"] option:selected').val();
						if(completeVal == '2'){
							if($('#send_pdf').is(':checked')){
								//ToDo: Trigger to send email.
								<?php
									$sender->send();
								?>
							}
						}
					});
					
					
					/* ToDo : 
						1. Render send button if form is marked completed.
						2. Render only after the instrument is locked (??).
						3. Append button element to DOM.
						4. Bind button click to send pdf.
					*/
					var sendBtn = '';
					if($is_saved_instrument && completeVal == '2'){
						$('#send_pdf_btn').show();
					}

					/*
						Show PDF preview option only for instruments that are already saved.
					*/
					if($is_saved_instrument){
						$('#pdf_preview').show();
					}
				});

				
			</script>
			<?php

		}
	};
?>