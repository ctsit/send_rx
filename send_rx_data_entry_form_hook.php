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
				/*
					Generate PDF.
				*/
				$sender = send_rx_get_sender($project_id, $event_id, $record, USERID);
				$sender->generatePDFFile();

				$is_pdf_generated = true;
				send_rx_save_record_field($project_id, $event_id, $record_id, $field_name, $is_pdf_generated, $repeat_instance);
				?>
				<script type="text/javascript">
					/*
						Success message on page load to confirm PDF generation.
					*/
					var app_path_images = '<?php echo APP_PATH_IMAGES ?>';
					var successMsg = '<div class="darkgreen" style="margin:8px 0 5px;"><img src="'+app_path_images+'tick.png"> New PDF has been generated</div>';
					$('#pdfExportDropdownDiv').parent().next().append(successMsg);
				</script>
				<?php
			}
			?>
			<script type="text/javascript">
				/*
					All DOM modifications for the final instrument.
				*/
				document.addEventListener('DOMContentLoaded', function(){
					var helpTxt = '<div style="color: #666;font-size: 11px;"><span></span></div>';
					$(helpTxt).insertBefore($('button[name="submit-btn-cancel"]')[0]);
					if($('#send_rx_pdf-linknew')){
						$('#send_rx_pdf-linknew').remove();
					}

					$('#submit-btn-saverecord').html('Send & Exit Form');
					$('#submit-btn-savecontinue').html('Send & Stay');
				});
			</script>
			<?php
		}
	};
?>