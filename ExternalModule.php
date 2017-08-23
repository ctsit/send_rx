<?php
/**
 * @file
 * Provides ExternalModule class for Linear Data Entry Workflow.
 */

namespace SendRx\ExternalModule;

require_once 'includes/RxSender.php';

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

/**
 * ExternalModule class for Linear Data Entry Workflow.
 */
class ExternalModule extends AbstractExternalModule {

    /**
     * @inheritdoc.
     */
    function hook_data_entry_form_top($project_id, $record, $instrument, $event_id, $group_id) {
        // New PDF is generated based on pdf_is_updated flag.
        // Reset flag once PDF is generated to avoid duplicate generation of the same PDF.
        global $Proj;

        if ($config = send_rx_get_project_config($project_id, 'site')) {
            if ($Proj->metadata['send_rx_dag_id']['form_name'] == $instrument) {
                // Hiding DAG ID field.
                echo '<script>$(document).ready(function() { $(\'#send_rx_dag_id-tr\').hide(); });</script>';
            }

            return;
        }

        // Checking if PDF file exists.
        if (!isset($Proj->metadata['send_rx_pdf'])) {
            return;
        }

        // Checking if we are on PDF form step.
        if ($Proj->metadata['send_rx_pdf']['form_name'] != $instrument) {
            return;
        }

        // Getting Rx sender to make sure we are in a patient project.
        if (!$sender = \RxSender::getSender($project_id, $event_id, $record)) {
            return;
        }

        $table = '<div class="info">This prescription has not been sent yet.</div>';
        if ($logs = $sender->getLogs()) {
            // Message types.
            $types = array(
                'email' => 'Email',
                'hl7' => 'HL7',
            );

            // Rows header.
            $header = array('Type', 'Success', 'Time', 'Recipients', 'User ID', 'Subject', 'Body');

            // Creating logs table.
            $table = '<div class="table-responsive"><table class="table table-condensed"><thead><tr>';
            foreach (range(0, 2) as $i) {
                $table .= '<th>' . $header[$i] . '</th>';
            }
            $table .= '<th></th></thead></tr><tbody>';

            // Modals container for the logs details.
            $modals = '<div id="send-rx-logs-details">';

            // Populating tables and creating one modal for each entry.
            foreach (array_reverse($logs) as $key => $row) {
                $table .= '<tr>';

                $row[0] = $types[$row[0]];
                $row[1] = $row[1] ? 'Yes' : 'No';
                $row[2] = date('m/d/y - h:i a', $row[2]);
                $row[3] = str_replace(',', '<br>', $row[3]);

                foreach (range(0, 2) as $i) {
                    $table .= '<td>' . $row[$i] . '</td>';
                }

                // Setting up the button that triggers the details modal.
                $table .= '<td><button type="button" class="btn btn-info btn-xs" data-toggle="modal" data-target="#send-rx-logs-details-' . $key . '">See details</button></td>';
                $table .= '</tr>';

                $modals .= '
                    <div class="modal fade" id="send-rx-logs-details-' . $key . '" role="dialog">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                    <h4>Message Details</h4>
                                </div>
                                <div class="modal-body" style="overflow-wrap:break-word;word-wrap:break-word;"><form>';

                foreach (range(3, 6) as $i) {
                    $modals .= '
                        <div class="form-group row">
                            <label class="col-sm-2 col-form-label">' . $header[$i] . '</label>
                            <div class="col-sm-10">' . $row[$i] . '</div>
                        </div>';
                }

                $modals .= '</form></div></div></div></div>';
            }

            $table .= '</tbody></table></div>';
            $modals .= '</div>';

            // Placing modals.
            echo $modals;
        }

        // Checking for PDF "is updated" flag.
        // We are not using REDCap::getData() because this field is not registered
        // at any instrument.
        $sql = '
            SELECT value FROM redcap_data
            WHERE
                field_name = "send_rx_pdf_is_updated" AND
                project_id = ' . db_escape($project_id) . ' AND
                event_id = ' . db_escape($event_id) . ' AND
                record = "' . db_escape($record) . '"
            LIMIT 1';

        $q = db_query($sql);

        $pdf_is_updated = false;
        if (db_num_rows($q)) {
            $result = db_fetch_assoc($q);
            $pdf_is_updated = $result['value'];
        }

        // Checking if event is complete.
        $event_is_complete = send_rx_event_is_complete($project_id, $record, $event_id, array($instrument));

        // Checking if PDF needs to be generated.
        if (!$pdf_is_updated) {
            if ($sender->getPrescriberData()) {
                // Generate PDF.
                $sender->generatePDFFile();
                send_rx_save_record_field($project_id, $event_id, $record, 'send_rx_pdf_is_updated', '1', $repeat_instance);
                echo '<div class="darkgreen" style="margin-bottom:30px;"><img src="' . APP_PATH_IMAGES . 'tick.png"> A new prescription PDF preview has been created.</div>';
            }
            else {
                $event_is_complete = false;
            }
        }

?>
<script type="text/javascript">
    // All DOM modifications for the final instrument.
    document.addEventListener('DOMContentLoaded', function() {
        var event_is_complete = <?php echo $event_is_complete ? 'true' : 'false'; ?>;
        var instrument_name = '<?php echo $instrument; ?>';
        var helpTxt = '<div style="color: #666;font-size: 11px;"><b>ATTENTION:</b> The prescription will be sent by submitting this form.</div>';

        $(helpTxt).insertBefore($('button[name="submit-btn-cancel"]')[0]);

        // Removing operation buttons on PDF file.
        if ($('#send_rx_pdf-linknew')) {
            $('#send_rx_pdf-linknew').remove();
        }

        $('#submit-btn-saverecord').html('Send & Exit Form');
        $('#submit-btn-savecontinue').html('Send & Stay');
        $('#submit-btn-savenextform').html('Send & Go to Next Form');
        $('#submit-btn-saveexitrecord').html('Send & Exit Record');
        $('#submit-btn-savenextrecord').html('Send & Go To Next Record');

        // Showing logs table.
        $('#send_rx_logs-tr .data').html('<?php echo $table; ?>');

        // Changing color of submit buttons.
        var $submit_buttons = $('button[id^="submit-btn-"]');
        $submit_buttons.addClass('btn-success');

        // Disables submit buttons.
        var disableSubmit = function() {
            $submit_buttons.prop('disabled', true);
            $submit_buttons.prop('title', 'Your must complete all form steps before sending the prescription.');
        };

        // Enables submit buttons.
        var enableSubmit = function() {
            $submit_buttons.removeProp('disabled');
            $submit_buttons.removeProp('title');
        };

        if (event_is_complete) {
            var $complete = $('select[name="' + instrument_name + '_complete"]');
            if ($complete.val() !== '2') {
                // Disables submit buttons if initial state not complete.
                disableSubmit();
            }

            $complete.change(function() {
                if ($(this).val() === '2') {
                    // Enables submit buttons if form becomes complete.
                    enableSubmit();
                }
                else {
                    // Disables submit buttons if form becomes not complete.
                    disableSubmit();
                }
            });
        }
        else {
            // If form is not complete, submit buttons must remain disabled.
            disableSubmit();
        }
    });
</script>
<?php
    }

    /**
     * @inheritdoc.
     */
    function hook_every_page_before_render($project_id) {
        // Checking if we are on data entry form.
        if (PAGE != 'DataEntry/index.php') {
            return;
        }

        // Getting record ID.
        if (!empty($_GET['id'])) {
            $record = $_GET['id'];
        }
        elseif (!empty($_POST['patient_id'])) {
            $record = $_POST['patient_id'];
        }
        else {
            return;
        }

        require_once 'includes/send_rx_functions.php';

        // Checking if this project has a Send Rx config.
        if (!$config = send_rx_get_project_config($project_id, 'patient')) {
            return;
        }

        // Checking if we are at the prescriber field's step.
        global $Proj;
        if (!isset($Proj->metadata['send_rx_prescriber_id']) || $_GET['page'] != $Proj->metadata['send_rx_prescriber_id']['form_name']) {
            return;
        }

        // Getting record group ID.
        if (!$group_id = \Records::getRecordGroupId($project_id, $record)) {
            $parts = explode('-', $record);
            if (count($parts) != 2) {
                return;
            }

            $group_id = $parts[0];
        }

        // Getting list of prescribers.
        if (!$prescribers = send_rx_get_group_members($project_id, $group_id, 'prescriber')) {
            return;
        }

        // Creating prescribers list to be used on dropdown.
        $options = array();
        foreach ($prescribers as $username => $prescriber) {
            $options[$username] = $username . ',' . $prescriber['user_firstname'] . ' ' . $prescriber['user_lastname'];
        }

        // Checking if current user is a prescriber and non-admin.
        if (isset($prescribers[USERID]) && !SUPER_USER && !ACCOUNT_MANAGER) {
            // If prescriber, we need to turn prescriber into readonly.
            $username = USERID;

            $data = send_rx_get_record_data($project_id, $record, $_GET['event_id']);
            if (!empty($data) && !empty($data['send_rx_prescriber_id']) && $data['send_rx_prescriber_id'] != USERID && isset($options[$data['send_rx_prescriber_id']])) {
                $username = $data['send_rx_prescriber_id'];
            }

            $options = array($options[$username]);
            $parts = explode(',', reset($options));

?>
<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var $select = $('select[name="send_rx_prescriber_id"]');
        var $row = $('#send_rx_prescriber_id-tr');

        $select.hide().find('option[value="<?php echo $username; ?>"]').prop('selected', true);
        $row.css('opacity', '0.6').find('.data').append('<?php echo $parts[1]; ?>');
    });
</script>
<?php
        }

        // Adding prescriber options.
        $Proj->metadata['send_rx_prescriber_id']['element_enum'] = implode('\\n', $options);
    }

    /**
     * @inheritdoc.
     */
    function hook_every_page_top($project_id) {
        if (strpos(PAGE, 'external_modules/manager/project.php') !== false) {
            echo '<script src="' . $this->getUrl('js/send-rx-config.js') . '"></script>';
            return;
        }

        // Checking if we are on record home page.
        if (PAGE != 'DataEntry/record_home.php' || empty($_GET['id'])) {
            return;
        }

        require_once 'includes/send_rx_functions.php';

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
            if (!$group_id = send_rx_save_dag($config['target_project_id'], $data['send_rx_site_name'])) {
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

            if (!($curr_role_values = send_rx_get_user_roles($config['target_project_id'], $group_id))) {
                return;
            }

            $db = new \RedCapDB();

            $input_members = array();
            foreach ($staff as $member) {
                if ($db->usernameExists($member['send_rx_user_id'])) {
                    $input_members[] = $member['send_rx_user_id'];
                }
            }

            $curr_members = send_rx_get_group_members($config['target_project_id'], $group_id);
            $curr_members = array_keys($curr_members);

            // Creating lists of users to be added and removed from the DAG.
            $members_to_add = array_diff($input_members, $curr_members);
            $members_to_del = array_diff($curr_members, $input_members);

            $role_names = array();
            $input_role_values = array();
            foreach ($staff as $member) {
                $curr_user = $member['send_rx_user_id'];
                $curr_role = $member['send_rx_user_role'];
                if ($db->usernameExists($curr_user)) {
                    $input_role_values[$curr_user] = $curr_role;
                    if (!in_array($curr_role, $role_names)) {
                        $role_names[] = $curr_role;
                    }
                }
            }
            if (!($roles_info = send_rx_get_user_role_ids($config['target_project_id'], $role_names))) {
                return;
            }

            foreach($input_role_values as &$val) {
                $val = $roles_info[$val];
            }

            $roles_to_add = array();
            $roles_to_del = array();

            foreach ($input_role_values as $key => $value) {
                if (!array_key_exists($key, $curr_role_values)) {
                    $roles_to_add[$key] = $value;
                } else if ($curr_role_values[$key] != $value) {
                    $roles_to_add[$key] = $value;
                }
            }

            foreach ($curr_role_values as $key => $value) {
                if (!array_key_exists($key, $input_role_values) && $value != 0) {
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
        var pid = '<?php echo $config['target_project_id']; ?>';
        var group_id = '<?php echo $group_id; ?>';
        var curr_members = <?php echo json_encode($curr_members); ?>;
        var members_to_add = <?php echo json_encode($members_to_add); ?>;
        var members_to_del = <?php echo json_encode($members_to_del); ?>;

        var roles_to_add = <?php echo json_encode($roles_to_add); ?>;
        var roles_to_del = <?php echo json_encode($roles_to_del); ?>;
        var count = 0;

        var refreshButtons = function() {
            $rebuild_button.prop('disabled', $.isEmptyObject(members_to_add) && $.isEmptyObject(members_to_del) && $.isEmptyObject(roles_to_add) && $.isEmptyObject(roles_to_del));
            $revoke_button.prop('disabled', $.isEmptyObject(curr_members));
        }

        refreshButtons();

        var grantGroupAccessToStaff = function(users, group_id = '') {
            // Remove each user to DAG.
            $.each(users, function(key, value) {
                $.ajax({
                    url: app_path_webroot + 'DataAccessGroups/data_access_groups_ajax.php',
                    data: { pid: pid, action: 'add_user', user: value, group_id: group_id },
                    async: false
                });
            });
        }

        var assignRole = function(users) {
            $.each(users, function(key, value) {
                $.ajax({
                    type: 'POST',
                    url: app_path_webroot + 'UserRights/assign_user.php?pid=' + pid,
                    data: { username: key, role_id: value, notify_email_role: 0 },
                    async: false
                });
            });
        }

        var reloadPage = function(msg) {
            window.location.href = window.location.href.replace('&msg=rebuild_perms', '').replace('&msg=revoke_perms', '') + '&msg=' + msg;
        }

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
            reloadPage('rebuild_perms');
        });


        $revoke_button.on('click', function() {
            // Revoke group access from users.
            grantGroupAccessToStaff(curr_members);

            // Reloading page.
            reloadPage('revoke_perms');
        });
    });
</script>
<?php
    }

    /**
     * @inheritdoc.
     */
    function hook_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id) {
        global $Proj;

        // Auto create a DAG in patients project when a new site is created.
        // Link this DAG Id to the site created.
        if ($config = send_rx_get_project_config($project_id, 'site')) {
            if (!isset($Proj->metadata['send_rx_dag_id']) || $Proj->metadata['send_rx_dag_id']['form_name'] != $instrument) {
                return;
            }

            $data = send_rx_get_record_data($project_id, $record, $event_id);
            if (($group_id = send_rx_save_dag($config['target_project_id'], $data['send_rx_site_name'], $data['send_rx_dag_id'])) && !$data['send_rx_dag_id']) {
                send_rx_save_record_field($project_id, $event_id, $record, 'send_rx_dag_id', $group_id);
            }

            return;
        }

        // Checking if PDF file exists.
        if (!isset($Proj->metadata['send_rx_pdf'])) {
            return;
        }

        // Getting Rx sender to make sure we are in a patient project.
        if (!$sender = \RxSender::getSender($project_id, $event_id, $record)) {
            return;
        }

        // Checking if we are on PDF form step.
        if ($Proj->metadata['send_rx_pdf']['form_name'] == $instrument) {
            // Send prescription.
            $sender->send(false);
            return;
        }

        // New PDF needs to be generated as changes are made to form after PDF is created.
        // Reset pdf_is_updated flag to generate new PDF.
        $field_name = 'send_rx_pdf_is_updated';
        send_rx_save_record_field($project_id, $event_id, $record, $field_name, '0', $repeat_instance);
    }
}
