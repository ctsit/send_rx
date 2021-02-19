<?php
/**
 * @file
 * Helper Send RX functions.
 */

include_once dirname(APP_PATH_DOCROOT) . '/vendor/autoload.php';

use ExternalModules\ExternalModules;
use UserProfile\UserProfile;

/**
 * Gets Send RX config from project.
 *
 * @param int $project_id
 *   The project ID.
 * @param string $project_type
 *   The RX Send project type, which can be "patient" or "site".
 *
 * @return object
 *   A keyed JSON containing the Send RX project configuration.
 *   If patient project, the object should include the following keys:
 *     - targetProjectId: Target site project ID.
 *     - senderClass: The sender class name, that extends RXSender.
 *     - sendDefault: Flag that defines whether the prescription should be sent
 *       by default.
 *     - lockInstruments: The instruments to be locked after the message is sent.
 *   If site project:
 *     - pdfTemplate: The PDF markup (HTML & CSS) of the prescription.
 *     - messageSubject: The message subject.
 *     - message_body: The message body.
 *   Returns FALSE if the project is not configure properly.
 */
function send_rx_get_project_config($project_id, $project_type) {
    if (!in_array($project_type, array('patient', 'site'))) {
        return false;
    }

    $q = ExternalModules::getSettings('send_rx', $project_id);
    if (!db_num_rows($q)) {
        return false;
    }

    $config = array();
    while ($result = db_fetch_assoc($q)) {
        if ($result['type'] == 'json' || $result['type'] == 'json-array') {
            $result['value'] = json_decode($result['value']);

            if (strpos($result['key'], 'send-rx-pdf-template-variable-') === false) {
                $result['value'] = reset($result['value']);
            }
        }
        elseif ($result['type'] == 'file') {
            $result['value'] = send_rx_get_edoc_file_contents($result['value']);
        }

        $config[str_replace('-', '_', str_replace('send-rx-', '', $result['key']))] = $result['value'];
    }

    if ($config['type'] != $project_type || empty($config['target_project_id'])) {
        return false;
    }

    if (!empty($config['pdf_template_variable_key'])) {
        $config['pdf_template_variables'] = array_combine($config['pdf_template_variable_key'], $config['pdf_template_variable_value']);
    }

    $to_remove = array(
        'enabled',
        'pdf_template_variable',
        'pdf_template_variable_key',
        'pdf_template_variable_value',
        'message',
    );

    foreach ($to_remove as $field) {
        unset($config[$field]);
    }

    return $config;
}

/**
 * Gets site ID from DAG.
 *
 * @param int $project_id.
 *   The project id.
 * @param int $group_id
 *   The DAG number.
 *
 * @return int
 *   The site record ID.
 */
function send_rx_get_site_id_from_dag($project_id, $group_id) {
    $sql = '
        SELECT record FROM redcap_data
        WHERE
            field_name = "send_rx_dag_id" AND
            project_id = "' . intval($project_id) . '" AND
            value = "' . intval($group_id) . '"
        LIMIT 1';

    $q = db_query($sql);
    if (!db_num_rows($q)) {
        return false;
    }

    $result = db_fetch_assoc($q);
    return $result['record'];
}

/**
 * Applies Piping on the given subject string.
 *
 * Example: "Hello, [first_name]!" turns into "Hello, Joe Doe!".
 *
 * @param string $subject
 *   The string be processed.
 * @param array $data
 *   An array of source data. It supports nesting values, which are mapped on the
 *   subject string as nesting square brackets (e.g. [user][first_name]).
 *
 * @return string
 *   The processed string, with the replaced values from source data.
 */
function send_rx_piping($subject, $data) {
    preg_match_all('/(\[[^\[]*\])+/', $subject, $matches);

    foreach ($matches[0] as $wildcard) {
        $parts = substr($wildcard, 1, -1);
        $parts = explode('][', $parts);

        $value = '';
        if (count($parts) == 1) {
            // This wildcard has no children.
            if (isset($data[$parts[0]])) {
                $value = $data[$parts[0]];
            }
        }
        else {
            $child = array_shift($parts);
            if (isset($data[$child]) && is_array($data[$child])) {
                // Wildcard with children. Call function recursively.
                $value = send_rx_piping('[' . implode('][', $parts) . ']', $data[$child]);
            }
        }

        // Search and replace.
        $subject = str_replace($wildcard, $value, $subject);
    }

    return $subject;
}

/**
 * Generates a PDF file.
 *
 * @param string $contents
 *   Markup (HTML + CSS) of PDF contents.
 * @param string $file_path
 *   The file path to save the file.
 * @param int $record_id
 *   Data entry record ID.
 * @param int $project_id
 *   Data entry project ID.
 * @param int $event_id
 *   Data entry event ID.
 *
 * @return bool
 *   TRUE if success, FALSE otherwise.
 */
function send_rx_generate_pdf_file($contents, $file_path, $record_id, $event_id, $project_id) {
    try {
        $mpdf = new \Mpdf\Mpdf(['tempDir' => APP_PATH_TEMP]);
        $mpdf->WriteHTML($contents);
        $mpdf->Output($file_path, 'F');
    }
    catch (Exception $e) {
        REDCap::logEvent('Rx file generation failed', $e->getMessage(), '', $record_id, $event_id, $project_id);
        return false;
    }

    REDCap::logEvent('Rx file generated', $file_path, '', $record_id, $event_id, $project_id);
    return true;
}

/**
 * Gets path of the given edoc file.
 *
 * @param int $file_id
 *   The edoc file id.
 *
 * @return string
 *   The file path.
 */
function send_rx_get_edoc_file_path($file_id) {
    $sql = 'SELECT stored_name FROM redcap_edocs_metadata WHERE doc_id = "' . intval($file_id) . '"';
    $q = db_query($sql);

    if (!db_num_rows($q)) {
        return false;
    }

    $file = db_fetch_assoc($q);
    $file_path = EDOC_PATH . $file['stored_name'];

    if (!file_exists($file_path) || !is_file($file_path)) {
        return false;
    }

    return $file_path;
}

/**
 * Gets file contents from the given edoc file.
 *
 * @param int $file_id
 *   The edoc file id.
 *
 * @return string
 *   The file contents.
 */
function send_rx_get_edoc_file_contents($file_id) {
    if (!$file_path = send_rx_get_edoc_file_path($file_id)) {
        return false;
    }

    return file_get_contents($file_path);
}

/**
 * Uploads an existing field to the edocs table.
 *
 * @param string $file_path
 *   The location of the file to be uploaded.
 *
 * @return int
 *   The edoc file ID if success, 0 otherwise.
 */
function send_rx_upload_file($file_path) {
    if (!file_exists($file_path) || !is_file($file_path)) {
        return false;
    }

    $file = array(
        'name'=> basename($file_path),
        'type'=> mime_content_type($file_path),
        'size'=> filesize($file_path),
        'tmp_name'=> $file_path,
    );

    return Files::uploadFile($file);
}

/**
 * Deletes EDOC file.
 *
 * @param int $file_id
 *   The edoc file id.
 *
 * @return bool
 *   TRUE if success, FALSE otherwise.
 */
function send_rx_edoc_file_delete($file_id) {
    $file_id = intval($file_id);
    if (!$file_path = send_rx_get_edoc_file_path($file_id)) {
        return false;
    }

    if (!db_query('DELETE FROM redcap_edocs_metadata WHERE doc_id = "' . $file_id . '"')) {
        return false;
    }

    // Deleting file.
    return unlink($file_path);
}

/**
 * Creates or updates a data entry field value.
 *
 * @param int $project_id
 *   Data entry project ID.
 * @param int $event_id
 *   Data entry event ID.
 * @param int $record_id
 *   Data entry record ID.
 * @param string $field_name
 *   Machine name of the field to be updated.
 * @param mixed $value
 *   The value to be saved.
 * @param int $instance
 *   (optional) Data entry instance ID (for repeat instrument cases).
 *
 * @return bool
 *   TRUE if success, FALSE otherwise.
 */
function send_rx_save_record_field($project_id, $event_id, $record_id, $field_name, $value, $instance = null) {
    $project_id = intval($project_id);
    $event_id = intval($event_id);
    $record_id = db_real_escape_string($record_id);
    $field_name = db_real_escape_string($field_name);
    $value = db_real_escape_string($value);
    $instance = intval($instance);

    $sql = 'SELECT 1 FROM redcap_data
            WHERE project_id = "' . $project_id . '" AND
                  event_id = "' . $event_id . '" AND
                  record = "' . $record_id . '" AND
                  field_name = "' . $field_name . '" AND
                  instance ' . ($instance ? '= "' . $instance . '"' : 'IS NULL');

    if (!$q = db_query($sql)) {
        return false;
    }

    if (db_num_rows($q)) {
        $sql = 'UPDATE redcap_data SET value = "' . $value . '"
                WHERE project_id = "' . $project_id . '" AND
                      event_id = "' . $event_id . '" AND
                      record = "' . $record_id . '" AND
                      field_name = "' . $field_name . '" AND
                      instance ' . ($instance ? '= "' . $instance . '"' : 'IS NULL');
    }
    else {
        $instance = $instance ? '"' . $instance . '"' : 'NULL';
        $sql = 'INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance)
                VALUES ("' . $project_id . '", "' . $event_id . '", "' . $record_id . '", "' . $field_name . '", "' . $value . '", ' . $instance . ')';
    }

    if (!$q = db_query($sql)) {
        return false;
    }

    return true;
}

/**
 * Gets data entry record information.
 *
 * If no event is specified, return all events.
 *
 * @param int $project_id
 *   Data entry project ID.
 * @param int $record_id
 *   Data entry record ID.
 * @param int $event_id
 *   (optional) Data entry event ID.
 *
 * @return array|bool
 *   Data entry record information array. FALSE if failure.
 */
function send_rx_get_record_data($project_id, $record_id, $event_id = null) {
    $data = REDCap::getData($project_id, 'array', $record_id, null, $event_id);
    if (empty($data[$record_id])) {
        return false;
    }

    if ($event_id) {
        return $data[$record_id][$event_id];
    }

    return $data[$record_id];
}

/**
 * Gets repeat instrument instances information from a given data entry record.
 *
 * If more than one event is fetched, it returns the first one.
 *
 * @param int $project_id
 *   Data entry project ID.
 * @param int $record_id
 *   Data entry record ID.
 * @param int $event_id
 *   (optional) Data entry event ID.
 *
 * @return array|bool
 *   Array containing repeat instrument instances information. FALSE if failure.
 */
function send_rx_get_repeat_instrument_instances($project_id, $record_id, $instrument_name, $event_id = null) {
    $data = REDCap::getData($project_id, 'array', $record_id, null, $event_id);
    if (empty($data[$record_id]['repeat_instances'])) {
        return false;
    }

    $data = $event_id ? $data[$record_id]['repeat_instances'][$event_id] : reset($data[$record_id]['repeat_instances']);
    if (empty($data[$instrument_name])) {
        return false;
    }

    return $data[$instrument_name];
}

/**
 * Gets a list of users of a given site.
 *
 * @param int $project_id
 *   The project ID.
 * @param int $site_id
 *   The site ID.
 * @param string $user_role
 *   The user role (e.g. 'prescriber', 'study_coordinator').
 *
 * @return array|bool
 *   Array of user names, keyed by user ID. FALSE, if error.
 */
function send_rx_get_site_users($project_id, $site_id, $user_role = null) {
    $data = REDCap::getData($project_id, 'array', $site_id);

    if (empty($data[$site_id]['repeat_instances'])) {
        return false;
    }

    $data = $data[$site_id]['repeat_instances'];
    reset($data);

    $proj = new Project($project_id);
    $form = $proj->metadata['send_rx_user_id']['form_name'];

    $event_id = key($data);
    if (empty($data[$event_id][$form])) {
        return false;
    }

    $users = array();
    foreach ($data[$event_id][$form] as $user_info) {
        if (empty($user_role) || $user_info['send_rx_user_role'] == $user_role) {
            $user_profile = new UserProfile($user_info['send_rx_user_id']);
            $user_profile = $user_profile->getProfileData();
            $user_profile['send_rx_user_id'] = $user_info['send_rx_user_id'];
            $user_profile['send_rx_user_role'] = $user_info['send_rx_user_role'];

            $users[] = $user_profile;
        }
    }

    return $users;
}

/**
 * Get group members.
 *
 * @param int $project_id
 *   The project ID.
 * @param int $group_id
 *   The group ID.
 * @param string $user_role
 *   The user role name.
 *
 * @return array
 *   Array of users info, keyed by username. Returns FALSE if failure.
 */
function send_rx_get_group_members($project_id, $group_id, $user_role = null) {
    $users = array();

    $sql = 'SELECT u.username FROM redcap_user_rights u';
    if ($user_role) {
        $sql .= ' INNER JOIN redcap_user_roles r ON u.role_id = r.role_id AND r.role_name = "' . db_real_escape_string($user_role) . '"';
    }

    $sql .= ' WHERE u.project_id = "' . intval($project_id) . '" AND u.group_id = "' . intval($group_id) . '"';

    $q = db_query($sql);
    if (db_num_rows($q)) {
        while ($result = db_fetch_assoc($q)) {
            $users[$result['username']] = User::getUserInfo($result['username']);
        }
    }

    return $users;
}

/**
 * Get user roles array with username and role_id as key value pair.
 *
 * @param int $project_id
 *   The project ID.
 * @param array $role_names
 *   Array of role_names.
 *
 * @return array
 *   Array of users info, keyed by role. Returns FALSE if failure.
 */
function send_rx_get_user_roles($project_id, $role_names = array()) {
    $user_roles = array();
    $sql = 'SELECT rit.username, rol.role_name, rol.role_id
            FROM redcap_user_rights rit
            INNER JOIN redcap_user_roles rol on rol.project_id = rit.project_id and rit.role_id = rol.role_id';

    if ($role_names) {
        $sql .= ' AND rol.role_name IN ("' . implode('", "', array_map('db_escape', $role_names)) . '")';
    }

    $sql .= ' WHERE rit.project_id = "' . intval($project_id) . '"';

    $q = db_query($sql);
    if (db_num_rows($q)) {
        while ($result = db_fetch_assoc($q)) {
            $user_roles[$result['username']] = $result['role_id'];
        }
    }
    else {
        return array();
    }

    return $user_roles;
}

/**
 * Get role ids and role names as a key, value pair in a array.
 *
 * @param int $project_id
 *   The project ID.
 * @param array $role_names
 *   Array of role_names.
 *
 * @return array
 *   Array of roles, keyed by role_name. Returns FALSE if failure.
 */
function send_rx_get_user_role_ids($pid, $role_names = array()) {
    $roles_info = array();

    $sql = 'SELECT role_id, role_name FROM redcap_user_roles WHERE project_id = "' . intval($pid) . '"';
    if ($role_names) {
        $sql .= ' AND role_name IN ("' . implode('", "', array_map('db_escape', $role_names)) . '")';
    }

    $q = db_query($sql);
    if (db_num_rows($q)) {
        while ($result = db_fetch_assoc($q)) {
            $roles_info[$result['role_id']] = $result['role_name'];
        }
    }
    else {
        return false;
    }

    return $roles_info;
}

/**
 * Checks whether the event forms are complete.
 *
 * @param int $project_id
 *   The project ID.
 * @param int $record
 *   The record ID.
 * @param int $event_id
 *   The event ID.
 * @param array $exclude
 *   (optional) An array of instrument names to bypass the check.
 *
 * @return bool
 *   TRUE if the event is complete, FALSE otherwise.
 */
function send_rx_event_is_complete($project_id, $record, $event_id, $exclude = array()) {
    $proj = new Project($project_id);

    // Getting the list of instruments of the given event.
    $fields = array();
    foreach ($proj->eventsForms[$event_id] as $form_name) {
        $fields[$form_name] = $form_name . '_complete';
    }

    foreach ($exclude as $form_name) {
        // Removing instruments from the list.
        unset($fields[$form_name]);
    }

    // Calculating whether the event is complete or not.
    $data = REDCap::getData($project_id, 'array', $record, $fields, $event_id);
    foreach ($fields as $form_name => $field) {
        if (isset($data[$record]['repeat_instances'][$event_id][$form_name])) {
            foreach ($data[$record]['repeat_instances'][$event_id][$form_name] as $form_data) {
                if ($form_data[$field] != 2) {
                    return false;
                }
            }
        }
        elseif ($data[$record][$event_id][$field] != 2) {
            return false;
        }
    }


    return true;
}

/**
 * Creates a Data Access Group.
 *
 * @param int $project_id
 *   The project ID.
 * @param string $group_name
 *   The group name.
 *
 * @return int
 *   The group ID.
 */
function send_rx_add_dag($project_id, $group_name) {
    $project_id = intval($project_id);
    $group_name = db_escape($group_name);
    $sql = 'INSERT INTO redcap_data_access_groups (project_id, group_name) VALUES (' . $project_id . ', "' . $group_name . '")';

    db_query($sql);
    $group_id = db_insert_id();

    _send_rx_log_dag_event('create', $sql, $group_id, $group_name, $project_id);

    return $group_id;
}

/**
 * Renames a Data Access Group.
 *
 * @param int $project_id
 *   The project ID.
 * @param string $group_name
 *   The group name.
 * @param int $group_id
 *   The group ID.
 */
function send_rx_rename_dag($project_id, $group_name, $group_id) {
    $project_id = intval($project_id);
    $group_id = intval($group_id);
    $group_name = db_escape($group_name);

    $sql = 'UPDATE redcap_data_access_groups SET group_name = "' . $group_name . '" WHERE project_id = ' . $project_id . ' AND group_id = ' . $group_id;
    db_query($sql);

    _send_rx_log_dag_event('rename', $sql, $group_id, $group_name, $project_id);
}

/**
 * Gets DAG name.
 *
 * @param int $project_id
 *   The project ID.
 * @param int $group_id
 *   The group ID.
 *
 * @return string|bool
 *   The DAG name if exists, FALSE otherwise.
 */
function send_rx_get_dag_name($project_id, $group_id) {
    if (intval($group_id) != $group_id) {
        return false;
    }

    $q = db_query('SELECT group_name FROM redcap_data_access_groups WHERE project_id = ' . intval($project_id) . ' AND group_id = ' . $group_id);
    if (!$q || !db_num_rows($q)) {
        return false;
    }

    $dag = db_fetch_assoc($q);
    return $dag['group_name'];
}

/**
 * Creates a basic user.
 *
 * @param string $username
 *   The new username.
 * @param string $firstname
 *   The new user first name.
 * @param string $lastname
 *   The new user last name.
 * @param string $email
 *   The new user email address.
 * @param bool $send_notification
 *   Defines whether to send a notification to the user. Defaults to TRUE.
 *
 * @return bool
 *   TRUE if success, FALSE otherwise.
 */
function send_rx_create_user($username, $firstname, $lastname, $email, $send_notification = true, $add_to_whitelist = true) {
    if (User::getUserInfo($username)) {
        // User already exists.
        return false;
    }

    global $default_datetime_format, $default_number_format_decimal, $default_number_format_thousands_sep, $auth_meth;

    $db = new RedCapDB();
    $sql = $db->saveUser(null, $username, $firstname, $lastname, $email,
                         null, null, null, null, null, null, 0, generateRandomHash(8),
                         $default_datetime_format, $default_number_format_decimal, $default_number_format_thousands_sep,
                         1, null, null, '4_HOURS', '0', 0, $auth_meth == 'table' ? 0 : 1, 0);

    if (empty($sql)) {
        REDCap::logEvent('User creation failed', 'User data could not be saved.');
        return false;
    }

    // Log the new user
    Logging::logEvent(implode(";\n", $sql), 'redcap_auth', 'MANAGE', $username, 'user = ' . $username, 'Create username');

    if ($add_to_whitelist) {
        // Adding user to whitelist, if whitelist is enabled.
        $q = db_query('SELECT 1 FROM redcap_config WHERE field_name = "enable_user_whitelist" AND value = 1');
        if (db_num_rows($q)) {
            $sql = 'INSERT INTO redcap_user_whitelist VALUES ("' . db_escape($username) . '")';
            if (!db_query($sql)) {
                return false;
            }

            Logging::logEvent($sql, 'redcap_user_whitelist', 'MANAGE', '', '', 'Add user to whitelist');
        }
    }

    if (!$send_notification) {
        return true;
    }

    switch ($auth_meth) {
        case 'table':
            global $lang, $project_contact_email;

            $msg = new Message();
            $msg->setTo($email);
            if (method_exists($msg, 'setToName')) {
                $msg->setToName($firstname . ' ' . $lastname);
            }
            $msg->setFrom($project_contact_email);

            // Set up the email subject.
            $msg->setSubject('REDCap ' . $lang['control_center_101']);

            // Get reset password link
            $resetpasslink = Authentication::getPasswordResetLink($username);

            $br = RCView::br();
            $body = $lang['control_center_4488'] . ' "' . RCView::b($username) . '"' . $lang['period'] . ' ' .
                    $lang['control_center_4486'] . $br . $br .
                    RCView::a(array('href' => $resetpasslink), $lang['control_center_4487']);

            // Set up email body.
            $msg->setBody($body, true);

            // Send the email.
            return $msg->send();

        case 'shibboleth':
            $user_info = User::getUserInfo($username);
            if ($code = User::setUserVerificationCode($user_info['ui_id'], 1)) {
                // Send the email.
                return User::sendUserVerificationCode($email, $code, 1, $username);
            }

            break;
    }

    return false;
}

/**
 * Read the current configuration of users and enabled DAGs from the
 * most recent DAG Switcher record in redcap_log_event
 * @return array
 *  keys: Usernames
 *  values: Array of DAGids user may switch to
 * [
 *   "user1": [],
 *   "user2: [0,123,124],
 *   "user3": [123,124]
 * ]
 */
function send_rx_get_user_dags($project_id) {
    $sql = '
        SELECT data_values FROM redcap_log_event
        WHERE project_id = "' . db_escape($project_id) . '" AND
              description = "DAG Switcher"
        ORDER BY log_event_id DESC LIMIT 1';

    if (!($q = db_query($sql)) || !db_num_rows($q)) {
        return array();
    }

    $row = $q->fetch_assoc();
    return json_decode($row['data_values'], true);
}

/**
 * Builds markup for status messages.
 *
 * @param string $msg
 *   The message to be displayed.
 * @param bool $error
 *   A boolean that determines whether the message is an error one.
 *
 * @return string
 *   The status message markup.
 */
function send_rx_build_status_message($msg, $error = false) {
    $class = 'darkgreen';
    $icon = 'tick';

    if ($error) {
        $class = 'red';
        $icon = 'exclamation';
    }

    return RCView::div(array(
        'class' => $class,
        'style' => 'margin: 8px 0 5px;',
    ), RCView::img(array('src' => APP_PATH_IMAGES . $icon . '.png')) . ' ' . REDCap::escapeHtml($msg));
}

/**
 * Aux function to log DAG event on both patient and site projects.
 *
 * @oaram $event
 *   The event to be logged: "create" or "rename".
 * @oaram $sql
 *   The executed SQL code.
 * @param int $group_id
 *   The group ID.
 * @param int $group_name
 *   The group name.
 * @param int $project_id
 *   The project ID.
 */
function _send_rx_log_dag_event($event, $sql, $group_id, $group_name, $project_id) {
    $label = ucfirst($event);
    REDCap::logEvent($label . 'd external DAG', 'Name: ' . $group_name . '<br>External ID: ' . $group_id);

    // Removing event ID context, since we are referencing other project.
    $event_id = isset($_GET['event_id']) ? $_GET['event_id'] : false;
    unset($_GET['event_id']);

    Logging::logEvent($sql, 'redcap_data_access_groups', 'MANAGE', $group_id, 'group_id = '. $group_id, $label . ' data access group', '', '', $project_id);

    if ($event_id !== false) {
        // Restoring event ID context.
        $_GET['event_id'] = $event_id;
    }
}
