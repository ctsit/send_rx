<?php
	return function($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance){
		/*
			New PDF is generated based on is_pdf_generated flag.
			Reset flag once PDF is generated to avoid duplicate generation of the same PDF.
		*/
		$last_instrument = 'send_rx_notification';
		if($instrument == $last_instrument){
			$data = send_rx_get_record_data($project_id, $record, $event_id);
   			$field_name = 'send_rx_is_pdf_updated';
   			$is_pdf_generated = $data[$field_name];
			if(is_bool($is_pdf_generated) == false){
				// Generate PDF
				$sender = send_rx_get_sender($project_id, $event_id, $record, USERID);
				$sender->generatePDFFile();
				// Flag reset to avoid duplicate regeneration
				$is_pdf_generated = true;
				send_rx_save_record_field($project_id, $event_id, $record_id, $field_name, $is_pdf_generated, $repeat_instance);
			}
			?>
			<script type="text/javascript">
				/*
					All DOM modifications for the final instrument.
				*/
				document.addEventListener('DOMContentLoaded', function(){
					var txt = '<div style="color: #666;font-size: 11px;"><span>NOTE: </span>New PDF will be generated and sent once the form is saved.</div>';
					$(txt).insertBefore($('button[name="submit-btn-cancel"]')[0]);
				});
			</script>
			<?php
		}
	};
?>