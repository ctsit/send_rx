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
use RCView;
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
    function hook_data_entry_form_top($project_id, $record = null, $instrument, $event_id, $group_id = null, $repeat_instance = 1) {
        // New PDF is generated based on pdf_is_updated flag.
        // Reset flag once PDF is generated to avoid duplicate generation of the same PDF.
        global $Proj;

        if ($config = send_rx_get_project_config($project_id, 'site')) {
            if ($Proj->metadata['send_rx_dag_id']['form_name'] == $instrument) {
                // Hiding DAG ID field.
                $this->includeJs('js/dag-id-field.js');
            }
            elseif ($Proj->metadata['send_rx_user_id']['form_name'] == $instrument) {
                global $lang;

                $this->includeJs('js/create-staff.js');
                $this->setJsSetting('usernameValidateRegexElement', RCView::div(array(
                    'id' => 'valregex-username',
                    'datatype' => 'text',
                    'label' => $lang['control_center_45'],
                ), '/^([a-zA-Z0-9_\.\-\@])+$/'));
                $this->setJsSetting('getUserProfileInfoUrl', $this->getUrl('includes/get_user_profile_info_ajax.php'));

                // Creating artificial fields to make user able to create
                // to account + profile directly from this form.
                $base_field = $Proj->metadata['send_rx_user_id'];

                $select_field_name = 'send_rx_new_user_opt';
                $form_name = $base_field['form_name'];
                $Proj->forms[$form_name]['fields'][$select_field_name] = $label;

                $options = array(
                    'existing' => 'Select an existing user',
                    'new' => 'Create a new user account from scratch',
                );

                $enum = array();
                foreach ($options as $key => $label) {
                    $enum[] = $key . ', ' . $label;
                }

                // Adding select list field that asks user whether to select an
                // existing user or create a new account.
                $Proj->metadata[$select_field_name] = array(
                    'field_name' => $select_field_name,
                    'element_label' => '',
                    'element_type' => 'radio',
                    'element_enum' => implode(' \n', $enum),
                    'misc' => '@DEFAULT="existing"',
                ) + $base_field;

                $base_field['misc'] = '';
                $base_field['element_type'] = 'text';
                $base_field['element_enum'] = '';
                $base_field['element_preceding_header'] = '';
                $base_field['field_order']++;

                $fields = array(
                    'id' => 'Username',
                    'first_name' => 'First name',
                    'last_name' => 'Last name',
                    'email' => 'Email',
                );

                // Shifting position of existing fields to make room for the
                // fake fields.
                $count = count($fields) + 1;
                foreach (array_keys($Proj->forms[$form_name]['fields']) as $field_name) {
                    if ($Proj->metadata[$field_name]['field_order'] > $Proj->metadata[$select_field_name]['field_order']) {
                        $Proj->metadata[$field_name]['field_order'] += $count;
                    }
                }

                $Proj->metadata['send_rx_user_id']['field_order']++;
                $Proj->metadata['send_rx_user_id']['element_preceding_header'] = '';

                // Updating fields count.
                $Proj->numFields += $count;

                // Adding username, first name, last name and email fake fields.
                foreach ($fields as $suffix => $label) {
                    $field_name = 'send_rx_new_user_' . $suffix;
                    $label = 'New user -- ' . $label;

                    $base_field['field_name'] = $field_name;
                    $base_field['element_label'] = $label;
                    $base_field['field_order']++;

                    $Proj->metadata[$field_name] = $base_field;
                    $Proj->forms[$form_name]['fields'][$field_name] = $label;
                }

                // Adding email validation.
                $Proj->metadata[$field_name]['element_validation_type'] = 'email';

                // Re-sorting form fields.
                uksort($Proj->forms[$form_name]['fields'], array($this, '_formFieldCmp'));
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
            $rx_sent = false;
            foreach (array_reverse($logs) as $key => $row) {
                $table .= '<tr>';

                $row[0] = $types[$row[0]];
                $row[2] = date('m/d/y - h:i a', $row[2]);
                $row[3] = str_replace(',', '<br>', $row[3]);

                if ($row[1]) {
                    $rx_sent = true;
                    $row[1] = 'Yes';
                }
                else {
                    $row[1] = 'No';
                }

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
                send_rx_save_record_field($project_id, $event_id, $record, 'send_rx_pdf_is_updated', '1');
                echo '<div class="darkgreen" style="margin-bottom:30px;"><img src="' . APP_PATH_IMAGES . 'tick.png"> A new prescription PDF preview has been created.</div>';
            }
            else {
                $event_is_complete = false;
            }
        }

        $prescriber_field = 'send_rx_prescriber_id';
        $pdf_field = 'send_rx_pdf';
        $data = REDCap::getData($project_id, 'array', $record, array($prescriber_field, $pdf_field), $event_id);

        $settings = array(
            'currentUserIsPrescriber' => $data[$record][$event_id][$prescriber_field] == USERID,
            'instrument' => $instrument,
            'table' => $table,
            'pdfIsSet' => !empty($data[$record][$event_id][$pdf_field]),
            'completeReplacement' => RCView::hidden(array('value' => $rx_sent ? 2 : 0, 'name' => $instrument . '_complete')),
            'sendBtn' => RCView::button(array('name' => 'send-rx-btn', 'class' => 'btn btn-primary btn-success send-rx-btn'), 'Send Rx'),
        );

        $this->setJsSetting('sendForm', $settings);
        $this->includeJs('js/send-form.js');
        $this->includeCss('css/send-form.css');

        if ($settings['currentUserIsPrescriber']) {
            $msg = $rx_sent ? 'This prescription ' . RCView::b('has already been sent') . '. Are you sure you want to re-send it?' : 'Are you sure you want to send this prescription?';
            $msg = RCView::img(array('src' => APP_PATH_IMAGES . 'warning.png')) . ' ' . RCView::span(array(), $msg);

            echo RCView::div(array('id' => 'send-rx-confirmation-modal', 'title' => 'Send Rx?'), RCView::p(array(), $msg));
        }
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

        global $Proj;

        // Getting record ID.
        if (!empty($_GET['id'])) {
            $record = $_GET['id'];
        }
        elseif (!empty($_POST[$Proj->table_pk])) {
            $record = $_POST[$Proj->table_pk];
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

            // Losing the key part.
            array_shift($parts);

            $settings = array(
                'username' => $username,
                'fullname' => implode(',', $parts),
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

        if (strpos(PAGE, 'ExternalModules/manager/project.php') !== false) {
            $this->includeCss('css/config.css');
            $this->includeJs('js/config.js');
            $this->setJsSetting('modulePrefix', $this->PREFIX);

            return;
        }

        // Checking if we are on record status dashboard page.
        if (PAGE != 'DataEntry/record_status_dashboard.php' || !$project_id) {
            return;
        }

        global $Proj;

        if (count($Proj->eventInfo) != 1) {
            // Multiple events are not supported.
            return;
        }

        require_once 'includes/send_rx_functions.php';

        if (!$config = send_rx_get_project_config($project_id, 'site')) {
            return;
        }

        if (!$valid_role_names = array_keys(parseEnum($Proj->metadata['send_rx_user_role']['element_enum']))) {
            return;
        }

        $count = count($valid_role_names);
        if (!$valid_role_names = send_rx_get_user_role_ids($config['target_project_id'], $valid_role_names)) {
            // There are no remote roles.
            return;
        }

        if (count($valid_role_names) != $count) {
            // Thre are remote roles missing.
            return;
        }

        $valid_role_ids = array_flip($valid_role_names);
        $user_role_ids = send_rx_get_user_roles($config['target_project_id'], $valid_role_names);

        reset($Proj->eventInfo);
        $event_id = key($Proj->eventInfo);

        $bypass = array_combine($Proj->eventsForms[$event_id], $Proj->eventsForms[$event_id]);

        // Detecting mandatory instruments to check completeness.
        foreach ($Proj->metadata as $field_name => $field_info) {
            if (strpos($field_name, 'send_rx_') === 0) {
                unset($bypass[$field_info['form_name']]);
            }
        }

        $all_dags = array();
        foreach (REDCap::getData($project_id) as $id => $data) {
            $data = $data[$event_id];

            if (!isset($data['send_rx_dag_id']) || empty($data['send_rx_site_name'])) {
                return;
            }

            if (!$dag = $data['send_rx_dag_id']) {
                if (!$dag = send_rx_add_dag($config['target_project_id'], $data['send_rx_site_name'])) {
                    return;
                }

                send_rx_save_record_field($project_id, $event_id, $record, 'send_rx_dag_id', $dag);
            }

            if (!send_rx_event_is_complete($project_id, $id, $event_id, $bypass)) {
                return;
            }

            $all_dags[$id] = REDCap::escapeHtml($dag);
        }

        if (empty($all_dags)) {
            return;
        }

        $user_dags = send_rx_get_user_dags($config['target_project_id']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['operation'], array('rebuild', 'revoke'))) {
            $msg = 'The permissions have been revoked successfully.';

            // Revoking dags.
            foreach (array_keys($_POST['user_dags']) as $user_id) {
                if (isset($user_dags[$user_id])) {
                    $user_dags[$user_id] = array_diff($user_dags[$user_id], $all_dags);
                }
            }

            if ($_POST['operation'] == 'rebuild') {
                $msg = 'The permissions have been rebuilt successfully.';

                // Granting dags.
                foreach ($_POST['user_dags'] as $user_id => $dags) {
                    // In order to be assigned to a DAG, the user needs to have
                    // some role.
                    if (!isset($user_role_ids[$user_id])) {
                        continue;
                    }

                    $user_dags[$user_id] = isset($user_dags[$user_id]) ? array_merge($user_dags[$user_id], $dags) : $dags;
                    sort($user_dags[$user_id], SORT_NUMERIC);
                }
            }

            REDCap::logEvent('DAG Switcher', json_encode($user_dags), '', null, null, $config['target_project_id']);

            // Showing success message.
            $msg = RCView::div(array(
                'class' => 'darkgreen',
                'style' => 'margin: 8px 0 5px;',
            ), RCView::img(array('src' => APP_PATH_IMAGES . 'tick.png')) . ' ' . $msg);
        }

        $form = RCView::hidden(array('name' => 'operation'));
        $input_dags = array();
        $input_roles = array();
        $db = new RedCapDB();

        $rebuild_btn_enabled = false;
        $revoke_btn_enabled = !empty($user_role_ids);

        foreach ($all_dags as $id => $dag) {
            if (!$staff = send_rx_get_site_users($project_id, $id)) {
                continue;
            }

            foreach ($staff as $member) {
                $user_id = $member['send_rx_user_id'];
                if (!$db->usernameExists($user_id)) {
                    continue;
                }

                $role_id = $member['send_rx_user_role'];

                if (!$rebuild_btn_enabled && (!isset($user_dags[$user_id]) || array_search($dag, $user_dags[$user_id]) === false)) {
                    $rebuild_btn_enabled = true;
                }

                // If a user member of multiple sites, it's assumed that
                // its role is the same for all of them.
                $input_roles[$user_id] = $member['send_rx_user_role'];

                if (!isset($input_dags[$user_id])) {
                    $input_dags[$user_id] = array();
                }

                $input_dags[$user_id][] = $dag;
                $form .= RCView::hidden(array('name' => 'user_dags[' . REDCap::escapeHtml($user_id) . '][]', 'value' => $dag));
            }
        }

        // Calculating the roles that need to be granted/revoked.
        $rebuild_roles = array();
        foreach ($input_roles as $user_id => $role_id) {
            $role_id = $valid_role_ids[$role_id];

            if (!isset($user_role_ids[$user_id]) || $user_role_ids[$user_id] != $role_id) {
                $rebuild_roles[$user_id] = $role_id;
            }
        }

        foreach (array_keys($user_role_ids) as $user_id) {
            if (!isset($input_roles[$user_id])) {
                $rebuild_roles[$user_id] = 0;
            }
        }

        // Defining whether Rebuild and Revoke buttons should be enabled.
        if (!$rebuild_btn_enabled && !empty($rebuild_roles)) {
            $rebuild_btn_enabled = true;
        }

        if (!$rebuild_btn_enabled || !$revoke_btn_enabled) {
            foreach ($user_dags as $user_id => $dags) {
                if (!$dags = array_intersect($dags, $all_dags)) {
                    continue;
                }

                $revoke_btn_enabled = true;
                if ($rebuild_btn_enabled) {
                    break;
                }

                if (!isset($input_dags[$user_id]) || array_diff($dags, $input_dags[$user_id])) {
                    $rebuild_btn_enabled = true;
                    break;
                }
            }
        }

        $attrs = array(
            'type' => 'button',
            'style' => 'margin-right: 5px;',
            'class' => 'rebuild-perms-btn btn btn-success',
        );

        if (!$rebuild_btn_enabled) {
            $attrs['disabled'] = true;
        }

        // Adding Rebuild permissions button.
        $form .= RCView::button($attrs, 'Rebuild staff permissions');

        $attrs = array(
            'type' => 'button',
            'class' => 'revoke-perms-btn btn btn-danger',
        );

        if (!$revoke_btn_enabled) {
            // Disable button when there are no
            $attrs['disabled'] = true;
        }

        // Adding Revoke permissions button.
        $form .= RCView::button($attrs, 'Revoke staff permissions');

        $settings = array(
            'msg' => $msg,
            'url' => APP_PATH_WEBROOT . 'UserRights/assign_user.php?pid=' . REDCap::escapeHtml($config['target_project_id']),
            'form' => RCView::form(array('method' => 'post', 'id' => 'send_rx_perms', 'style' => 'margin-top: 20px;'), $form),
            'rebuildRoles' => $rebuild_roles,
            'revokeRoles' => array_combine(array_keys($user_role_ids), array_fill(0, count($user_role_ids), 0)),
        );

        $this->setJsSetting('permsRebuild', $settings);
        $this->includeJs('js/perm-buttons.js');
    }

    /**
     * @inheritdoc.
     */
    function hook_save_record($project_id, $record = null, $instrument, $event_id, $group_id = null, $survey_hash = null, $response_id = null, $repeat_instance = 1) {
        global $Proj;

        // Auto create a DAG in patients project when a new site is created.
        // Link this DAG Id to the site created.
        if ($config = send_rx_get_project_config($project_id, 'site')) {
            if ($Proj->metadata['send_rx_dag_id']['form_name'] == $instrument) {
                $data = send_rx_get_record_data($project_id, $record, $event_id);
                if ($data['send_rx_dag_id']) {
                    send_rx_rename_dag($config['target_project_id'], $data['send_rx_site_name'], $data['send_rx_dag_id']);
                }
                else {
                    $group_id = send_rx_add_dag($config['target_project_id'], $data['send_rx_site_name']);
                    send_rx_save_record_field($project_id, $event_id, $record, 'send_rx_dag_id', $group_id);
                }
            }
            elseif ($Proj->metadata['send_rx_user_id']['form_name'] == $instrument) {
                if (empty($_POST['send_rx_new_user_opt']) || $_POST['send_rx_new_user_opt'] != 'new') {
                    return;
                }

                $values = array();
                foreach (array('id', 'first_name', 'last_name', 'email') as $suffix) {
                    if (empty($_POST['send_rx_new_user_' . $suffix])) {
                        return;
                    }

                    $values['send_rx_user_' . $suffix] = trim(strip_tags(label_decode($_POST['send_rx_new_user_' . $suffix])));
                }

                $username = preg_replace('/[^a-z A-Z0-9_\.\-\@]/', '', $values['send_rx_user_id']);
                if ($username != $values['send_rx_user_id']) {
                    // Invalid username.
                    return;
                }

                if (!UserProfile::createProfile($values)) {
                    return;
                }

                send_rx_create_user($username, $values['send_rx_user_first_name'], $values['send_rx_user_last_name'], $values['send_rx_user_email']);
                send_rx_save_record_field($project_id, $event_id, $record, 'send_rx_user_id', $username, $repeat_instance);
            }

            return;
        }

        // Checking if we are at the prescriber field's step.
        $id_field_name = 'send_rx_prescriber_id';
        $target_field_name = 'send_rx_prescriber_email';

        if (isset($Proj->metadata[$id_field_name]) && $instrument == $Proj->metadata[$id_field_name]['form_name'] && isset($Proj->metadata[$target_field_name]) && !empty($_POST[$id_field_name])) {
            $user_profile = new UserProfile($_POST[$id_field_name]);
            $data = $user_profile->getProfileData();

            $source_field_name = 'send_rx_user_email';
            if (!empty($data[$source_field_name])) {
                send_rx_save_record_field($project_id, $event_id, $record, $target_field_name, $data[$source_field_name]);
            }
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
            $patient_data = $sender->getPatientData();
            if (empty($patient_data[$event_id]['send_rx_pdf'])) {
                return;
            }

            // Only the prescriber can send the prescription.
            $prescriber_data = $sender->getPrescriberData();
            if ($prescriber_data['send_rx_user_id'] != USERID) {
                return;
            }

            // Send prescription.
            if (!$sender->send(false)) {
                return;
            }

            $field_name = $instrument . '_complete';
            if ($patient_data[$event_id][$field_name] != 2) {
                send_rx_save_record_field($project_id, $event_id, $record, $field_name, '2');
            }

            return;
        }

        // New PDF needs to be generated as changes are made to form after PDF is created.
        // Reset pdf_is_updated flag to generate new PDF.
        $field_name = 'send_rx_pdf_is_updated';
        send_rx_save_record_field($project_id, $event_id, $record, $field_name, '0');
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

    /**
     * Auxiliary function to compare the order between two fields.
     *
     * @param string $a
     *   Name of the field to be compared.
     * @param string $b
     *   Name of the field to compare with.
     *
     * @return
     *   TRUE if $a comes first than $b, FALSE otherwise.
     */
    function _formFieldCmp($a, $b) {
        global $Proj;
        return $Proj->metadata[$a]['field_order'] >= $Proj->metadata[$b]['field_order'];
    }
}
