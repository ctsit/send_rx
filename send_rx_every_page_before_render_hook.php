<?php
	return function($project_id){
        parse_str($_SERVER['QUERY_STRING'], $qs_params);
     
        if($qs_params['page'] == 'patient_screening_info'){
        	require_once 'send_rx_functions.php';
        	require_once 'RxSender.php';

			global $Proj;

			parse_str($_SERVER['QUERY_STRING'], $qs_params);
			$record = $qs_params['id'];
        	$parts = explode('_', $record);
    		$dag = $parts[0];
        	$pharmacy_id = send_rx_get_site_id_from_dag($project_id, $dag);
        	//$pharmacy_id = get_pharmacy_id_by_dag("1248_1");

			$users = send_rx_get_pharmacy_users($project_id, $pharmacy_id, 'prescriber', 'patient');
	        if(empty($qs_params['msg'])){
	        	/*
					Used to track the role of logged in user.
	        	*/
	        	$userPrescriberId = '';
	        	/*
					String to be added to $Proj metadata. Used to render list of prescribers on patient project.
	        	*/
	            $optionStr = '';
	            foreach ($users as $key => $value) {
	            	if($value['send_rx_prescriber_id'] == USERID){
	            		$userPrescriberId = USERID;
	            	}
	            	$optionStr .= $value['send_rx_prescriber_id'].', '.$value['prescriber_first_name'].' '.$value['prescriber_last_name'];
	            	if($value != end($users)){
	            		$optionStr.= '\\n';
	            	}
	            }
	            $Proj->metadata['send_rx_username']['element_enum'] = $optionStr;
	            ?>
	            <script type="text/javascript">
	                document.addEventListener('DOMContentLoaded', function(){
	                    var userPrescriberId = '<?php echo $userPrescriberId; ?>';
	                    if(userPrescriberId != ''){
	                    	$('[name="send_rx_username"] option[value="'+userPrescriberId+'"]').prop('selected', true);
	                        $('#send_rx_username-tr').hide();
	                    }
	                });
	            </script>
	            <?php
	        }
        }
	};
?>
