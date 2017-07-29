<?php
    return function ($project_id) {
        /*
            Hook needs to be loaded on edit records page.
         */
        if (PAGE != 'DataEntry/record_home.php' || empty($_GET['id'])) {
            return;
        }

        require_once 'send_rx_functions.php';

        if (!$config = send_rx_get_project_config($project_id, 'site')) {
            return;
        }

        $record = $_GET['id'];
        $data = send_rx_get_record_data($project_id, $record);
        reset($data);
        $event_id = key($data);
        $data = $data[$event_id];

        if (empty($data['send_rx_dag_id'])) {
            return;
        }

        $buttons = '<button class="btn btn-success send-rx-access-btn" id="send-rx-rebuild-access-btn" style="margin-right:5px;">Rebuild staff permissions</button>';
        $buttons .= '<button class="btn btn-danger send-rx-access-btn" id="send-rx-revoke-access-btn">Revoke staff permissions</button>';
        $buttons = '<div id="access-btns">' . $buttons . '</div>';

        if ($buttons_enabled = send_rx_event_is_complete($project_id, $record, $event_id)) {
            global $Proj;

            $form_name = $Proj->metadata['send_rx_user_id']['form_name'];
            if ($staff = send_rx_get_repeat_instrument_instances($project_id, $record, $form_name, $event_id)) {
                $db = new RedCapDB();

                // Create a list of users to be added to DAG.
                $input_members = array();
                foreach ($staff as $member) {
                    if ($db->usernameExists($member['send_rx_user_id'])) {
                        $input_members[] = $member['send_rx_user_id'];
                    }
                }

                $group_id = $data['send_rx_dag_id'];

                $curr_members = send_rx_get_group_members($config->targetProjectId, $group_id);
                $curr_members = array_keys($curr_members);

                $members_to_add = array_diff($input_members, $curr_members);
                $members_to_del = array_diff($curr_members, $input_members);
            }
        }

        ?>
        <script type="text/javascript">
            $(document).ready(function() {
                $('#repeating_forms_table_parent').after('<?php echo $buttons; ?>');

                var buttons_enabled = <?php echo $buttons_enabled ? 'true' : 'false'; ?>;
                if (!buttons_enabled) {
                    $('send-rx-access-btn').attr('disabled', 'disabled');
                    return;
                }

                var $rebuild_button = $('#send-rx-rebuild-access-btn');
                var $revoke_button = $('#send-rx-revoke-access-btn');
                var pid = '<?php echo $config->targetProjectId; ?>';
                var group_id = '<?php echo $group_id; ?>';
                var curr_members = <?php echo json_encode($curr_members); ?>;
                var members_to_add = <?php echo json_encode($members_to_add); ?>;
                var members_to_del = <?php echo json_encode($members_to_del); ?>;

                var grantGroupAccessToStaff = function(users, group_id = '') {
                    // Remove each user to DAG.
                    $.each(users, function(key, value) {
                        $.get(app_path_webroot + 'DataAccessGroups/data_access_groups_ajax.php?pid=' + pid + '&action=add_user&user=' + value + '&group_id=' + group_id);
                        // TODO: if errors, display an alert.
                    });
                }

                if ($.isEmptyObject(members_to_add) && $.isEmptyObject(members_to_del)) {
                    $rebuild_button.attr('disabled', 'disabled');
                }
                else {
                    $rebuild_button.on('click', function() {
                        // Rebuilding, part 1: Revoke access.
                        grantGroupAccessToStaff(members_to_del);

                        // Rebuilding, part 2: Grant access..
                        grantGroupAccessToStaff(members_to_add, group_id);

                        // Reloading page.
                        location.reload();
                    });
                }

                if ($.isEmptyObject(curr_members)) {
                    $revoke_button.attr('disabled', 'disabled');
                }
                else {
                    $revoke_button.on('click', function() {
                        // Revoke group access from users.
                        grantGroupAccessToStaff(curr_members);

                        // Reloading page.
                        location.reload();
                    });
                }
            });
        </script>
        <?php
    }
?>
