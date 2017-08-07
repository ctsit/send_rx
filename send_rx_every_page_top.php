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

        if (!isset($data['send_rx_dag_id']) || empty($data['send_rx_site_name'])) {
            return;
        }

        if (!$group_id = $data['send_rx_dag_id']) {
            if (!$group_id = send_rx_save_dag($config->targetProjectId, $data['send_rx_site_name'])) {
                return;
            }

            send_rx_save_record_field($project_id, $event_id, $record, 'send_rx_dag_id', $group_id);
        }

        if (
            $buttons_enabled = send_rx_event_is_complete($project_id, $record, $event_id) &&
            $staff = send_rx_get_site_users($project_id, $record)
        ) {
            if (isset($_GET['msg'])) {
                $msgs = array(
                    'rebuild_perms' => 'The permissions have been rebuilt successfully.',
                    'revoke_perms' => 'The permissions have been revoked successfully.',
                );

                if (isset($msgs[$_GET['msg']])) {
                    // Showing success message.
                    echo '<div class="darkgreen" style="margin:8px 0 5px;"><img src="' . APP_PATH_IMAGES . 'tick.png"> ' . $msgs[$_GET['msg']] . '</div>';
                }
            }

            $db = new RedCapDB();

            $input_members = array();
            foreach ($staff as $member) {
                if ($db->usernameExists($member['send_rx_user_id'])) {
                    $input_members[] = $member['send_rx_user_id'];
                }
            }

            $curr_members = send_rx_get_group_members($config->targetProjectId, $group_id);
            $curr_members = array_keys($curr_members);

            // Creating lists of users to be added and removed from the DAG.
            $members_to_add = array_diff($input_members, $curr_members);
            $members_to_del = array_diff($curr_members, $input_members);

            if (!($curr_value = send_rx_get_user_roles($config->targetProjectId, $group_id))) {
                return;
            }
            $role_names = array();
            $input_value = array();
            foreach ($staff as $member) {
                $curr_user = $member['send_rx_user_id'];
                $curr_role = $member['send_rx_user_role'];
                if ($db->usernameExists($curr_user)) {
                    $input_value[$curr_user] = $curr_role;
                    if (!in_array($curr_role, $role_names)) {
                        $role_names[] = $curr_role;
                    }
                }
            }
            if (!($roles_info = send_rx_get_user_role_ids($config->targetProjectId, $role_names))) {
                return;
            }

            foreach($input_value as &$val) {
                $val = $roles_info[$val];
            }
            
            $roles_to_add = array();
            $roles_to_del = array();
            
            foreach ($input_value as $key => $value) {
                if (!array_key_exists($key, $curr_value)) {
                    $roles_to_add[$key] = $value;
                } else if ($curr_value[$key] != $value) {
                    $roles_to_add[$key] = $value;
                }
            }

            foreach ($curr_value as $key => $value) {
                if (!array_key_exists($key, $input_value) && $value != 0) {
                    $roles_to_del[$key] = 0;
                }
            }
        }

        // Buttons markup.
        $buttons = '<button class="btn btn-success send-rx-access-btn" id="send-rx-rebuild-access-btn" style="margin-right:5px;">Rebuild staff permissions</button>';
        $buttons .= '<button class="btn btn-danger send-rx-access-btn" id="send-rx-revoke-access-btn">Revoke staff permissions</button>';
        $buttons = '<div id="access-btns">' . $buttons . '</div>';

        ?>
        <script type="text/javascript">
            $(document).ready(function() {
                $('#repeating_forms_table_parent').after('<?php echo $buttons; ?>');

                var buttons_enabled = <?php echo $buttons_enabled ? 'true' : 'false'; ?>;
                if (!buttons_enabled) {
                    $('.send-rx-access-btn').prop('disabled', true);
                    return;
                }

                var $rebuild_button = $('#send-rx-rebuild-access-btn');
                var $revoke_button = $('#send-rx-revoke-access-btn');
                var pid = '<?php echo $config->targetProjectId; ?>';
                var group_id = '<?php echo $group_id; ?>';
                var curr_members = <?php echo json_encode($curr_members); ?>;
                var members_to_add = <?php echo json_encode($members_to_add); ?>;
                var members_to_del = <?php echo json_encode($members_to_del); ?>;

                var roles_to_add = <?php echo json_encode($roles_to_add); ?>;
                var roles_to_del = <?php echo json_encode($roles_to_del); ?>;

                var grantGroupAccessToStaff = function(users, group_id = '') {
                    // Remove each user to DAG.
                    $.each(users, function(key, value) {
                        $.get(app_path_webroot + 'DataAccessGroups/data_access_groups_ajax.php?pid=' + pid + '&action=add_user&user=' + value + '&group_id=' + group_id);
                        // TODO: if errors, display an alert.
                    });
                }

                var assignRole = function(users) {
                    $.each(users, function(key, value) {
                        $.post(app_path_webroot+'UserRights/assign_user.php?pid='+pid, { username: key, role_id: value, notify_email_role:0 });
                    });                    
                }

                if ($.isEmptyObject(members_to_add) && $.isEmptyObject(members_to_del)
                    && $.isEmptyObject(roles_to_add) && $.isEmptyObject(roles_to_del)) {
                    $rebuild_button.prop('disabled', true);
                }
                else {
                    $rebuild_button.on('click', function() {

                        // Rebuilding, delete roles
                        assignRole(roles_to_del);
                        
                        // Rebuilding, add/modify roles
                        assignRole(roles_to_add);

                        // Rebuilding, part 1: Revoke access.
                        grantGroupAccessToStaff(members_to_del);

                        // Rebuilding, part 2: Grant access.
                        grantGroupAccessToStaff(members_to_add, group_id);

                        // Reloading page.
                        window.location.href = window.location.href + '&msg=' + 'rebuild_perms';
                    });
                }

                if ($.isEmptyObject(curr_members)) {
                    $revoke_button.prop('disabled', true);
                }
                else {
                    $revoke_button.on('click', function() {
                        // Revoke group access from users.
                        grantGroupAccessToStaff(curr_members);

                        // Reloading page.
                        window.location.href = window.location.href + '&msg=' + 'revoke_perms';
                    });
                }
            });
        </script>
        <?php
    }
?>
