<?php
/**
 * @file
 * Provides ExternalModule class for Linear Data Entry Workflow.
 */

namespace SendRx\ExternalModule;

require_once 'includes/RxSender.php';

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use Records;
use RedCapDB;
use REDCap;
use SendRx\RxSender;
use UserProfile\UserProfile;

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
                $this->includeJs('js/dag-id-field.js');
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
        if (!$sender = RxSender::getSender($project_id, $event_id, $record)) {
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

        $prescriber_field = 'send_rx_prescriber_id';
        $data = REDCap::getData($project_id, 'array', $record, $prescriber_field, $event_id);
        $settings = array(
            'prescriberIsSet' => !empty($data[$record][$event_id][$prescriber_field]),
            'instrument' => $instrument,
            'table' => $table,
        );

        $this->setJsSetting('sendForm', $settings);
        $this->includeJs('js/send-form.js');
    }

    /**
     * @inheritdoc.
     */
    function hook_every_page_before_render($project_id) {
        if (empty($project_id)) {
            return;
        }

        if (PAGE == 'ProjectSetup/export_project_odm.php' || PAGE == 'DataExport/data_export_ajax.php') {
            // Avoiding any interferences in exports.
            return;
        }

        if ($config = send_rx_get_project_config($project_id, 'site')) {
            global $Proj;

            $options = array();
            foreach (UserProfile::getProfiles() as $username => $user_profile) {
                $data = $user_profile->getProfileData();
                $options[] = $username . ',' . $data['send_rx_user_first_name'] . ' ' . $data['send_rx_user_last_name'];
            }

            // Setting dropdown options dynamically.
            $Proj->metadata['send_rx_user_id']['element_enum'] = implode('\\n', $options);

            return;
        }

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
        if (!$group_id = Records::getRecordGroupId($project_id, $record)) {
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

            $settings = array(
                'username' => $username,
                'fullname' => $parts[1],
            );

            $this->includeJs('js/init.js');
            $this->setJsSetting('prescriberField', $settings);
            $this->includeJs('js/prescriber-field.js');
        }

        // Adding prescriber options.
        $Proj->metadata['send_rx_prescriber_id']['element_enum'] = implode('\\n', $options);
    }

    /**
     * @inheritdoc.
     */
    function hook_every_page_top($project_id) {
        if ($project_id) {
            $this->includeJs('js/init.js');
        }

        if (
            strpos(PAGE, 'ExternalModules/manager/project.php') !== false ||
            strpos(PAGE, 'external_modules/manager/project.php') !== false
        ) {
            $this->includeCss('css/config.css');
            $this->includeJs('js/config.js');
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
            if (!$group_id = send_rx_add_dag($config['target_project_id'], $data['send_rx_site_name'])) {
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

            $db = new RedCapDB();

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

            foreach ($input_role_values as &$val) {
                $val = $roles_info[$val];
            }

            $roles_to_add = array();
            $roles_to_del = array();

            foreach ($input_role_values as $key => $value) {
                if (!array_key_exists($key, $curr_role_values)) {
                    $roles_to_add[$key] = $value;
                }
                else if ($curr_role_values[$key] != $value) {
                    $roles_to_add[$key] = $value;
                }
            }

            foreach ($curr_role_values as $key => $value) {
                if (!isset($input_role_values[$key]) && $value != 0) {
                    // TODO: adapt this list when users become able to join
                    // multiple DAGs.
                    $roles_to_del[$key] = 0;
                }
            }
        }

        // Buttons markup.
        $buttons = '<button class="btn btn-success send-rx-access-btn" id="send-rx-rebuild-access-btn" style="margin-right:5px;">Rebuild staff permissions</button>';
        $buttons .= '<button class="btn btn-danger send-rx-access-btn" id="send-rx-revoke-access-btn">Revoke staff permissions</button>';
        $buttons = '<div id="access-btns">' . $buttons . '</div>';

        $settings = array(
            'pid' => $config['target_project_id'],
            'groupId' => $group_id,
            'buttons' => $buttons,
            'buttonsEnabled' => $buttons_enabled,
            'membersToAdd' => $members_to_add,
            'membersToDel' => $members_to_del,
            'rolesToAdd' => $roles_to_add,
            'rolesToDel' => $roles_to_del,
            'currMembers' => $curr_members,
            'revokeRoles' => array_combine($curr_members, array_fill(0, count($curr_members), 0)) + $roles_to_del,
            'revokeGroups' => array_merge($curr_members, $members_to_del),
        );

        $this->setJsSetting('permButtons', $settings);
        $this->includeJs('js/perm-buttons.js');
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
            if ($data['send_rx_dag_id']) {
                send_rx_rename_dag($config['target_project_id'], $data['send_rx_site_name'], $data['send_rx_dag_id']);
            }
            else {
                $group_id = send_rx_add_dag($config['target_project_id'], $data['send_rx_site_name']);
                send_rx_save_record_field($project_id, $event_id, $record, 'send_rx_dag_id', $group_id);
            }

            return;
        }

        // Checking if PDF file exists.
        if (!isset($Proj->metadata['send_rx_pdf'])) {
            return;
        }

        // Getting Rx sender to make sure we are in a patient project.
        if (!$sender = RxSender::getSender($project_id, $event_id, $record)) {
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

    /**
     * Includes a local CSS file.
     *
     * @param string $path
     *   The relative path to the css file.
     */
    protected function includeCss($path) {
        echo '<link rel="stylesheet" href="' . $this->getUrl($path) . '">';
    }

    /**
     * Includes a local JS file.
     *
     * @param string $path
     *   The relative path to the js file.
     */
    protected function includeJs($path) {
        echo '<script src="' . $this->getUrl($path) . '"></script>';
    }

    /**
     * Sets a JS setting.
     *
     * @param string $key
     *   The setting key to be appended to the module settings object.
     * @param mixed $value
     *   The setting value.
     */
    protected function setJsSetting($key, $value) {
        echo '<script>sendRx.' . $key . ' = ' . json_encode($value) . ';</script>';
    }
}
