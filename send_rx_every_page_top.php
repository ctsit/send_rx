<?php
    return function ($project_id){
        $url = $_SERVER['REQUEST_URI'];
        parse_str($_SERVER['QUERY_STRING'], $qs_params);
        $event_id = $qs_params['event_id'];
        $record = $qs_params['id'];
        
        /*
            Hook needs to be loaded on edit records page.
        */
        if(preg_match('/DataEntry\/record_home/', $url) != 1 && !empty($record)){
            return;
        }
        if(preg_match('/DataEntry\/record_home/', $url) == 1 && empty($record)){
            return;
        }

        global $Proj;

        if (!$config = send_rx_get_project_config($project_id, 'pharmacy')) {
            return;
        }
        
        $patient_project_id = $config->targetProjectId;
        $data = REDCap::getData($project_id, 'array', null, null);
        $dag_id = $data[$record][$event_id]['send_rx_dag_id'];

        // Create a list of users to be added to DAG.
        $users_in_dag = array();
        $users_formname = $Proj->metadata['send_rx_pharmacy_name']['form_name'];
        $proj_users = $data[$record]['repeat_instances'][$event_id][$form_name];
        foreach ($proj_users as $key => $item) {
            $users_in_dag[] = $item['send_rx_prescriber_id'];
        }
        $encoded_users_in_dag = json_encode($users_in_dag);

        //ToDo : Check if all forms are complete
        ?>

        <script type="text/javascript">
            $(document).ready(function() {
                // ToDo: The button functionality should only work after forms are complete
                var btns = '<div id="access_btns" style="margin:5px 0;">'+
                            '<button class="btn btn-primary" id="assign_access_btn" name="assign_access_btn" style="margin-bottom:2px;font-size:13px !important;padding:6px 8px;" tabindex="8">Assign User Access</button>&nbsp;'+
                            '<button class="btn btn-primary" id="revoke_access_btn" name="revoke_access_btn" style="margin-bottom:2px;font-size:13px !important;padding:6px 8px;" tabindex="8">Revoke User Access</button>'+
                            '</div>';
                $('#event_grid_table').after(btns);

                var app_path_webroot = '<?php echo APP_PATH_WEBROOT; ?>';
                var pid = '<?php echo $patient_project_id; ?>';
                var group_id = '<?php echo $dag_id; ?>';
                var usersInDag = '<?php echo $encoded_users_in_dag; ?>';

                // Assign users to DAG
                $('#assign_access_btn').on('click', function(){
                    // Add each user to DAG
                    for(var i=0;i<usersInDag.length;i++){
                        $.get(app_path_webroot+'DataAccessGroups/data_access_groups_ajax.php?pid='+pid+'&action=add_user&user='+usersInDag[i]['username']+'&group_id='+group_id,{ },function(data){
                            if(data){
                                return;
                            }
                        });
                    }
                });

                // Revoke access to users from DAG
                $('#revoke_access_btn').on('click', function(){
                    // Remove each user to DAG
                    for(var i=0;i<usersInDag.length;i++){
                        $.get(app_path_webroot+'DataAccessGroups/data_access_groups_ajax.php?pid='+pid+'&action=add_user&user='+usersInDag[i]['username']+'&group_id=""',{ },function(data){
                            if(data){
                                return;
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }
?>