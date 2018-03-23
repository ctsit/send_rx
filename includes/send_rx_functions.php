<?php
/**
 * @file
 * Helper Send RX functions.
 */

include_once dirname(__DIR__) . '/vendor/autoload.php';
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
 * Gets hl7 settings for that specific
 *
 * @param int $project_id.
 *   The project id.
 *
 * @return associative array $hl7_settings.
 *   An associative array that carries the hl7 settings with the following keys:
 *    - send-rx-hl7-end-point: the begining of the URL of the mirth connect server.
 *    - send-rx-hl7-json: json message to be sent to end-point.
 */
 function getHL7Settings($project_id) {
   $q = ExternalModules::getSettings('send_rx', $project_id, ['send-rx-hl7-end-point', 'send-rx-hl7-json']);
   if (!db_num_rows($q)) {
       return false;
   }

   $hl7_settings = [];

   while($result = db_fetch_assoc($q)) {
     $hl7_settings[$result['key']] = $result['value'];
   }

   return $hl7_settings;
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
            project_id = ' . db_escape($project_id) . ' AND
            value = ' . db_escape($group_id) . '
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
 * Generates a mirth client bound to the given endpoint.
 *
 * @param string $endpoint.
 *   Base url of the REST API being connected to.
 *
 * @return REDCapMithClient obj
 *   TRUE if success, FALSE otherwise.
 */
function send_rx_generate_mirth_client($endpoint) {
  $client_module = ExternalModules::getModuleInstance('redcap_mirth_client', 'v1.0');
  return $client_module->getClient($endpoint);
}

/**
 * Generates a PDF file.
 *
 * @param string $contents
 *   Markup (HTML + CSS) of PDF contents.
 * @param string $file_path
 *   The file path to save the file.
 *
 * @return bool
 *   TRUE if success, FALSE otherwise.
 */
function send_rx_generate_pdf_file($contents, $file_path) {

  try {
      $mpdf = new \Mpdf\Mpdf(['tempDir' => APP_PATH_TEMP]);
      $mpdf->WriteHTML($contents);
      $mpdf->Output($file_path, 'F');
  }
  catch (Exception $e) {
      return false;
  }

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
    $sql = 'SELECT stored_name FROM redcap_edocs_metadata WHERE doc_id = ' . db_escape($file_id);
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
    if (!$file_path = send_rx_get_edoc_file_path($file_id)) {
        return false;
    }

    if (!db_query('DELETE FROM redcap_edocs_metadata WHERE doc_id = ' . db_escape($file_id))) {
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
    $readsql = "SELECT 1 from redcap_data where project_id = $project_id and event_id = $event_id and record = '".db_escape($record_id)."' and field_name = '".db_escape($field_name)."' " . ($instance == null ? "AND instance is null" : "AND instance = '".db_escape($instance)."'");

    $q = db_query($readsql);
    if (!$q) return false;

    $record_count = db_result($q, 0);
    if ($record_count == 0) {
        if (isSet($instance)) {
            $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) " . "VALUES ($project_id, $event_id, '".db_escape($record_id)."', '".db_escape($field_name)."', '".db_escape($value)."' , $instance)";
        } else {
            $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) " . "VALUES ($project_id, $event_id, '".db_escape($record_id)."', '".db_escape($field_name)."', '".db_escape($value)."')";
        }
        $q = db_query($sql);
        if (!$q) return false;
        return true;
    } else {
        $sql = "UPDATE redcap_data set value = '".db_escape($value)."' where project_id = $project_id and event_id = $event_id and record = '".db_escape($record_id)."' and field_name = '".db_escape($field_name)."' " . ($instance == null ? "AND instance is null" : "AND instance = '".db_escape($instance)."'");
        $q = db_query($sql);
        if (!$q) return false;
        return true;
    }
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
        $sql .= ' INNER JOIN redcap_user_roles r ON u.role_id = r.role_id AND r.role_name = "' . db_escape($user_role) . '"';
    }

    $sql .= ' WHERE u.project_id = ' . db_escape($project_id) . ' AND u.group_id = ' . db_escape($group_id);

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
 *
 * @return array
 *   Array of users info, keyed by role. Returns FALSE if failure.
 */
function send_rx_get_user_roles($project_id) {
    $user_roles = array();
    $sql = 'SELECT rit.username, rol.role_name, rol.role_id FROM redcap_user_rights rit left join redcap_user_roles rol on rol.project_id = rit.project_id and rit.role_id = rol.role_id';
    $sql .= ' WHERE rit.project_id = ' . db_escape($project_id);

    $q = db_query($sql);
    if (db_num_rows($q)) {
        while ($result = db_fetch_assoc($q)) {
            $curr_role = $result['role_id'];
            $curr_user = $result['username'];
            $user_roles[$curr_user] = $curr_role;
        }
    } else {
        return false;
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
function send_rx_get_user_role_ids($pid, $role_names) {
    $roles = array();
    $sql = 'SELECT role_id, role_name from redcap_user_roles where project_id = ' . ($pid) . ' and role_name in ';
    $sql .= '("' . implode('","', $role_names) . '")';

    $roles_info = array();
    $q = db_query($sql);
    if (db_num_rows($q)) {
        while ($result = db_fetch_assoc($q)) {
            $rid = $result['role_id'];
            $rname = $result['role_name'];
            $roles_info[$rname] = $rid;
        }
    } else {
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
 * @param int $group_name
 *   The group name.
 *
 * @return int
 *   The group ID.
 */
function send_rx_add_dag($project_id, $group_name, $group_id = null) {
    $project_id = db_escape($project_id);
    $group_name = db_escape($group_name);

    db_query('INSERT INTO redcap_data_access_groups (project_id, group_name) VALUES (' . $project_id . ', "' . $group_name . '")');
    return db_insert_id();
}

/**
 * Renames a Data Access Group.
 *
 * @param int $project_id
 *   The project ID.
 * @param int $group_name
 *   The group name.
 * @param int $group_id
 *   The group ID.
 */
function send_rx_rename_dag($project_id, $group_name, $group_id) {
    $project_id = db_escape($project_id);
    $group_name = db_escape($group_name);
    $group_id = db_escape($group_id);

    db_query('UPDATE redcap_data_access_groups SET group_name = "' . $group_name . '" WHERE project_id = ' . $project_id . ' AND group_id = ' . $group_id);
}
