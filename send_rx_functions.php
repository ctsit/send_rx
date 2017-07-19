<?php
/**
 * @file
 * Helper Send RX functions.
 */

require_once '../../plugins/custom_project_settings/cps_lib.php';

/**
 * Gets Send RX config from project.
 *
 * @param int $project_id
 *   The project ID.
 * @param string $project_type
 *   The RX Send project type, which can be "patient" or "pharmacy".
 *
 * @return object
 *   A keyed JSON containing the Send RX project configuration.
 *   If patient project, the object should include the following keys:
 *     - targetProjectId: Target pharmacy project ID.
 *     - senderClass: The sender class name, that extends RXSender.
 *     - sendDefault: Flag that defines whether the prescription should be sent
 *       by default.
 *     - lockInstruments: The instruments to be locked after the message is sent.
 *   If pharmacy project:
 *     - pdfTemplate: The PDF markup (HTML & CSS) of the prescription.
 *     - messageSubject: The message subject.
 *     - message_body: The message body.
 *   Returns FALSE if the project is not configure properly.
 */
function send_rx_get_project_config($project_id, $project_type) {
    if (!in_array($project_type, array('patient', 'pharmacy'))) {
        return false;
    }

    // Loading Custom Project Settings object.
    $cps = new cps_lib();
    if (!$config = $cps->getAttributeData($project_id, 'send_rx_config')) {
        return false;
    }
    if (!$config = json_decode($config)) {
        return false;
    }
    if (empty($config->type) || $config->type != $project_type) {
        return false;
    }

    $req_fields = array(
        'patient' => array('pdfTemplate', 'messageSubject', 'messageBody'),
        'pharmacy' => array('targetProjectId'),
    );

    // Validating required config fields.
    foreach ($req_fields[$project_type] as $field) {
        if (empty($config->{$field})) {
            return false;
        }
    }

    // Custom validation for patient project.
    if ($project_type == 'patient') {
        if (empty($config->senderClass)) {
            $config->senderClass = 'RxSender';
        }
        elseif (!class_exists($config->senderClass)) {
            return false;
        }

        if (!empty($config->lockInstruments)) {
            $config->lockInstruments = explode(',', $config->lockInstruments);
        }
    }

    return $config;
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
    // Checking for wildcards.
    if (!$brackets = getBracketedFields($subject, true, true, false)) {
        return $subject;
    }

    foreach (array_keys($brackets) as $wildcard) {
        $parts = explode('.', $wildcard);
        $count = count($parts);

        if ($count == 1) {
            // This wildcard has no children.
            if (!isset($data[$wildcard])) {
                continue;
            }

            $value = $data[$wildcard];
        }
        else {
            $child = array_shift($parts);
            if (!isset($data[$child]) || !is_array($data[$child])) {
                continue;
            }

            // Wildcard with children. Call function recursively.
            $value = send_rx_piping('[' . implode('][', $parts) . ']', $data[$child]);
        }

        // Search and replace.
        $subject = str_replace('[' . str_replace('.', '][', $wildcard) . ']', $value, $subject);
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
 *
 * @return bool
 *   TRUE if success, FALSE otherwise.
 */
function send_rx_generate_pdf_file($contents, $file_path) {

  try {
    $pdf = new FPDF_HTML();
    $pdf->SetFont('Arial', '', 12);
    $pdf->AddPage();
    $pdf->WriteHTML($contents);
    $pdf->Output($file_path, 'F');
  } catch (Exception $e) {
    return false;
  }

  return true;

}

/**
 * Gets file contents from the given edocs file.
 *
 * @param int $file_id
 *   The edocs file id.
 *
 * @return string
 *   The file contents.
 */
function send_rx_get_file_contents($file_id) {
    $sql = 'SELECT * FROM redcap_edocs_metadata WHERE doc_id = ' . db_escape($file_id);
    $q = db_query($sql);

    if (!db_num_rows($q)) {
        return false;
    }

    $file = db_fetch_assoc($q);
    $file_path = EDOC_PATH . $file['stored_name'];

    if (!file_exists($file_path) || !is_file($file_path)) {
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
 *   The edocs file ID if success, 0 otherwise.
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
 * TODO.
 */
function send_rx_get_edocs_file($file_id, $username = USERID) {
    $sql = '
        SELECT * FROM redcap_edocs_metadata f
            INNER JOIN redcap_user_rights u ON u.project_id = f.project_id and u.username = "' . db_escape($username) . '"
            WHERE f.doc_id = ' . $file_id . ' LIMIT 1';

    $q = db_query($sql);
    if (!db_num_rows($q)) {
        // The given file ID does not exist.
        return false;
    }

    return db_fetch_assoc($q);
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

    return reset($data[$record_id]);
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
 * Gets the pharmacies that the user belongs to.
 *
 * @param int $project_id
 *   The patient or pharmacy project ID.
 * @param string $username
 *   The username. Defaults to the current one.
 * @param string $project_type
 *   Specifies the incoming project type: "patient" or "pharmacy".
 *   Defaults to "patient".
 *
 * @return array|bool
 *   Array of pharmacies names, keyed by pharmacy ID. False if error.
 */
function send_rx_get_user_pharmacies($project_id, $username = USERID, $project_type = 'patient') {
    if ($project_type == 'patient') {
        if (!$config = send_rx_get_project_config($project_id, $project_type)) {
            return false;
        }

        // Gets pharmacy project from the patient project.
        $project_id = $config->targetProjectId;
        $project_type = 'pharmacy';
    }

    // Checking if pharmacy project is ok.
    if (!send_rx_get_project_config($project_id, $project_type)) {
        return false;
    }

    $pharmacies = array();

    $data = REDCap::getData($project_id, 'array', null, array('send_rx_pharmacy_name', 'send_rx_prescriber_id'));
    foreach ($data as $pharmacy_id => $pharmacy_info) {
        if (empty($pharmacy_info['repeat_instances'])) {
            continue;
        }

        $event_id = key($pharmacy_info['repeat_instances']);
        if (empty($pharmacy_info['repeat_instances'][$event_id]['prescribers'])) {
            continue;
        }

        foreach ($pharmacy_info['repeat_instances'][$event_id]['prescribers'] as $prescriber_info) {
            if ($username == $prescriber_info['send_rx_prescriber_id']) {
                // The user belongs to this pharmacy.
                $pharmacies[$pharmacy_id] = $pharmacy_info[$event_id]['send_rx_pharmacy_name'];
                break;
            }
        }
    }

    return $pharmacies;
}
