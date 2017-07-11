<?php
/**
 * @file
 * Helper Send RX functions.
 */

require_once 'LockRecord.php';

/**
 * Gets Send RX config from project.
 *
 * @param int $project_id
 *   The project ID.
 * @param string $project_type
 *   The RX Send project type, which can be "patient" or "pharmacy".
 *
 * @return array|bool
 *   A keyed array containing the Send RX project configuration.
 *   If patient project, the array should include at least the following info:
 *     - pharmacy_pid: Destination pharmacy project ID.
 *     - class: The class name that extends RXSender.
 *     - lock_instruments: The instruments to be locked after the message is sent.
 *   If pharmacy project, the array should include at least the following info:
 *     - pdf_template: The PDF markup (HTML & CSS) of the prescription.
 *     - message_subject: The message subject.
 *     - message_body: The message body.
 *   Returns FALSE if the project is not configure properly.
 */
function send_rx_get_project_config($project_id, $project_type) {
    // TODO.
    // Obs.: the config values come from the database with the "send_rx" prefix
    // (e.g. send_rx_pharmacy_pid). Let's cut this prefix off before returning the
    // config arrays in order to avoid reduncancy. RXSender class already expects this.
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
    if (!$brackets = getBracketedFields($subject, TRUE, TRUE, FALSE)) {
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
    // TODO.
}

/**
 * Checks if there is an already an entry in the table and accordingly use update or insert methods.
 *
 * @param int $project_id
 *   Data entry project ID.
 * @param int $event_id
 *   Data entry event ID.
 * @param int $record_id
 *   Data entry record ID.
 * @param string $field_name
 *   Machine name of the field to be updated.
 * @param string $value
 *   The value to be saved.
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
function send_rx_get_record_data($project_id, $record_id, $event_id = NULL) {
    $data = REDCap::getData($project_id, 'array', $record_id, NULL, $event_id);
    if (empty($data[$record_id])) {
        return FALSE;
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
function send_rx_get_repeat_instrument_instances($project_id, $record_id, $instrument_name, $event_id = NULL) {
    $data = REDCap::getData($project_id, 'array', $record_id, NULL, $event_id);
    if (empty($data[$record_id]['repeat_instances'])) {
        return FALSE;
    }

    $data = $event_id ? $data[$record_id]['repeat_instances'][$event_id] : reset($data['repeat_instances'][$record_id]);
    if (empty($data[$instrument_name])) {
        return FALSE;
    }

    return $data[$instrument_name];
}

/**
 * Locks for updates the given instruments of the given data entry record.
 *
 * @param int $project_id
 *   Data entry project ID.
 * @param int $record_id
 *   Data entry record ID.
 * @param int $instruments
 *   (optional) Array of instruments names. Leave blank to block all instruments.
 * @param int $event_id
 *   (optional) Data entry event ID. Leave blank to block all events.
 *
 * @return bool
 *   TRUE if success, FALSE otherwise.
 */
function send_rx_lock_instruments($project_id, $record_id, $instruments = NULL, $event_id = NULL) {
    // TODO. yet to handle locking all events functionality
    if (!isSet($event_id)) {
        return false;
    }
    $lockObj = new LockRecord($username, $project_id, $record_id);
    return $lockObj->lockEvent($event_id, $instruments);
}
