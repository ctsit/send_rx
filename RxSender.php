<?php
/**
 * @file
 * Provides RXSender class.
 */

require_once 'send_rx_functions.php';
require_once 'LockRecord.php';

class RxSender {

    /**
     * Patient project ID.
     *
     * @var int
     */
    protected $patientProjectId;

    /**
     * Patient event ID.
     *
     * @var int
     */
    protected $patientEventId;

    /**
     * Patient record ID.
     *
     * @var int
     */
    protected $patientId;

    /**
     * Patient record data.
     *
     * @var array
     */
    protected $patientData;

    /**
     * Pharmacy project ID.
     *
     * @var int
     */
    protected $pharmacyProjectId;

    /**
     * Pharmacy event ID.
     *
     * @var int
     */
    protected $pharmacyEventId;

    /**
     * Pharmacy record ID.
     *
     * @var int
     */
    protected $pharmacyId;

    /**
     * Pharmacy record data.
     *
     * @var array
     */
    protected $pharmacyData;

    /**
     * Prescriber username.
     *
     * @var string
     */
    protected $username;

    /**
     * Prescriber data.
     *
     * @var array
     */
    protected $prescriberData;

    /**
     * The delivery methods.
     *
     * @var array
     */
    protected $deliveryMethods = array();

    /**
     * Patient configuration data.
     *
     * @var object
     */
    protected $patientConfig;

    /**
     * Pharmacy configuration data.
     *
     * @var object
     */
    protected $pharmacyConfig;

    /**
     * Sent messages log.
     *
     * @var array
     */
    protected $logs = array();

    /**
     * Record locker object.
     *
     * @var LockUtil
     */
    protected $locker;

    /**
     * Patient project metadata.
     *
     * @var Project.
     */
    protected $patientProj;

    /**
     * Pharmacy project metadata.
     *
     * @var Project.
     */
    protected $pharmacyProj;

    /**
     * Creates the sender object for the given Send RX project.
     *
     * @param int $project_id
     *   Data entry project ID.
     * @param int $event_id
     *   Data entry event ID.
     * @param int $patient_id
     *   Data entry record ID.
     * @param string $username
     *   The username. Defaults to the current one.
     *
     * @return object|bool
     *   An object instance of RXSender extension for the given project, if success.
     *   False otherwise.
     */
    static function getSender($project_id, $event_id, $patient_id, $username = USERID) {
        if (!$config = send_rx_get_project_config($project_id, 'patient')) {
            return false;
        }

        $class = empty($config->senderClass) ? 'RxSender' : $config->senderClass;
        return new $class($project_id, $event_id, $patient_id, $username);
    }

    /**
     * Constructor.
     *
     * Sets up properties and processes data to be easily read.
     */
    function __construct($project_id, $event_id, $patient_id, $username = USERID) {
        $this->patientProjectId = $project_id;
        $this->patientProj = new Project($this->patientProjectId);
        $this->patientId = $patient_id;
        $this->patientEventId = $event_id;

        $this->username = $username;
        $this->locker = new LockRecord($this->username, $this->patientProjectId, $this->patientId);

        // Getting patient project config.
        if (!$config = send_rx_get_project_config($project_id, 'patient')) {
            return;
        }

        $this->patientConfig = $config;
        $this->pharmacyProjectId = $config->targetProjectId;
        $this->pharmacyProj = new Project($this->pharmacyProjectId);

        // Getting pharmacy project config.
        if (!$config = send_rx_get_project_config($this->pharmacyProjectId, 'pharmacy')) {
            return;
        }

        $this->pharmacyConfig = $config;

        // Getting patient data.
        if (!$data = send_rx_get_record_data($this->patientProjectId, $this->patientId, $this->patientEventId)) {
            return;
        }

        $this->setPatientData($data);

        // Getting logs.
        if (!empty($data['send_rx_logs']) && ($logs = send_rx_get_edoc_file_contents($data['send_rx_logs']))) {
            $this->logs = json_decode($logs, true);
        }

        $this->pharmacyId = $this->patientData['send_rx_pharmacy_id'];

        // Getting pharmacy data.
        if (!$data = send_rx_get_record_data($this->pharmacyProjectId, $this->pharmacyId)) {
            return;
        }

        $this->pharmacyEventId = key($data);
        $this->setPharmacyData($data[$this->pharmacyEventId]);

        $instrument = $this->pharmacyProj->metadata['send_rx_prescriber_id']['form_name'];
        if (!$data = send_rx_get_repeat_instrument_instances($this->pharmacyProjectId, $this->pharmacyId, $instrument)) {
            return;
        }

        // Setting up prescriber data.
        foreach ($data as $value) {
            if ($value['send_rx_prescriber_id'] == $username) {
                $this->setPrescriberData($value);
                break;
            }
        }

        $instrument = $this->pharmacyProj->metadata['send_rx_message_type']['form_name'];
        if (!$data = send_rx_get_repeat_instrument_instances($this->pharmacyProjectId, $this->pharmacyId, $instrument)) {
            return;
        }

        // Setting up delivery methods.
        foreach ($data as $value) {
            $this->deliveryMethods[$value['send_rx_message_type']] = $value;
        }
    }

    /**
     * Gets the patient data.
     */
    function getPatientData() {
        return $this->patientData;
    }

    /**
     * Gets the pharmacy data.
     */
    function getPharmacyData() {
        return $this->pharmacyData;
    }

    /**
     * Gets the prescriber data.
     */
    function getPrescriberData() {
        return $this->prescriberData;
    }

    /**
     * Gets the patient configuration array.
     */
    function getPatientConfig() {
        return $this->patientConfig;
    }

    /**
     * Gets the pharmacy configuration array.
     *
     * Contains important settings to build the message and PDF contents.
     */
    function getPharmacyConfig() {
        return $this->pharmacyConfig;
    }

    /**
     * Gets a list of delivery methods.
     */
    function getDeliveryMethods() {
        return $this->deliveryMethods;
    }

    /**
     * Sets list of logs of the current data entry record.
     */
    function getLogs() {
        return $this->logs;
    }

    /**
     * Sets patient data.
     */
    protected function setPatientData($data) {
        $this->patientData = $data;
    }

    /**
     * Sets pharmacy data.
     */
    protected function setPharmacyData($data) {
        $this->pharmacyData = $data;
    }

    /**
     * Sets prescriber data.
     */
    protected function setPrescriberData($data) {
        $this->prescriberData = $data;
    }

    /**
     * Gets PDF template contents.
     */
    protected function getPDFTemplate() {
        $pdf_template = $this->pharmacyConfig->pdfTemplate;
        if (!empty($this->pharmacyData['send_rx_pdf_template'])) {
            $pdf_template = $this->pharmacyData['send_rx_pdf_template'];
        }

        $sql = '
            SELECT e.doc_id FROM redcap_docs_to_edocs e
            INNER JOIN redcap_docs d ON
                d.docs_id = e.docs_id AND
                d.project_id = "' . db_escape($this->pharmacyProjectId) . '" AND
                d.docs_comment = "' . db_escape($pdf_template) . '"
            ORDER BY d.docs_id DESC
            LIMIT 1';

        $q = db_query($sql);
        if (!db_num_rows($q)) {
            return false;
        }

        $result = db_fetch_assoc($q);
        return send_rx_get_edoc_file_contents($result['doc_id']);
    }

    /**
     * Auxiliar function that preprocesses files fields before setting them up.
     */
    protected function preprocessData($data, $proj) {
        $project_type = isset($proj->metadata['patient_id']) ? 'patient' : 'pharmacy';
        foreach ($data as $field_name => $value) {
            if (!isset($proj->metadata[$field_name]) || $proj->metadata[$field_name]['element_type'] != 'file' || empty($value)) {
                continue;
            }

            if (!$file_path = send_rx_get_edoc_file_path($value)) {
                $data[$field_name] = '';
                continue;
            }

            $mimetype = mime_content_type($file_path);
            if (strpos($mimetype, 'image/') === 0) {
                // Building image tag.
                $data[$field_name] = '<img src="data:' . $mimetype . ';base64,' . base64_encode(file_get_contents($file_path)) . '">';
            }
            else {
                // Building download link.
                $data[$field_name] = $this->buildFileUrl($value, $field_name, $project_type);
            }
        }

        return $data;
    }

    /**
     * Sends the message to the pharmacy and returns whether the operation was successful.
     */
    function send($generate_pdf = true, $log = true) {
        $success = false;

        if ($generate_pdf) {
            $this->generatePDFFile();
        }

        // Getting data to apply Piping.
        $data = $this->getPipingData();

        foreach ($this->getDeliveryMethods() as $msg_type => $config) {
            // Getting templates.
            $subject = empty($config['send_rx_message_subject']) ? $this->pharmacyConfig->messageSubject : $config['send_rx_message_subject'];
            $body = empty($config['send_rx_message_body']) ? $this->pharmacyConfig->messageBody : $config['send_rx_message_body'];

            // Replacing wildcards.
            $subject = send_rx_piping($subject, $data);
            $body = send_rx_piping($body, $data);

            switch ($msg_type) {
                case 'email':
                    $success = REDCap::email($config['send_rx_recipients'], $this->prescriberData['send_rx_prescriber_email'], $subject, $body);
                    $this->log($msg_type, $success, $config['send_rx_recipients'], $subject, $body);

                    break;

                case 'hl7':
                    // TODO: handle HL7 messages.
                    break;
            }
        }

        if (!empty($this->patientConfig->lockInstruments)) {
            $this->locker->lockEvent($this->patientEventId, $this->patientConfig->lockInstruments);
        }

        return $success;
    }

    /**
     * Generates the prescription PDF file.
     */
    function generatePDFFile() {
        if (!$contents = $this->getPDFTemplate()) {
            return false;
        }

        $data = $this->getPipingData();
        $contents = send_rx_piping($contents, $data);
        $file_path = $this->generateTmpFilePath('pdf');

        if (!send_rx_generate_pdf_file($contents, $file_path)) {
            return false;
        }
        if (!$file_id = send_rx_upload_file($file_path)) {
            return false;
        }

        if (!empty($this->patientData['send_rx_pdf'])) {
            $last_log = end($this->logs);

            if (!$last_log || $this->patientData['send_rx_pdf'] != $last_log[7]) {
                // Removing non logged PDF file.
                send_rx_edoc_file_delete($this->patientData['send_rx_pdf']);
            }
        }

        send_rx_save_record_field($this->patientProjectId, $this->patientEventId, $this->patientId, 'send_rx_pdf', $file_id);
        $this->patientData['send_rx_pdf'] = $file_id;

        return $file_id;
    }

    /**
     * Generates a prescription PDF file path.
     */
    protected function generateTmpFilePath($extension) {
        $components = array($this->patientProjectId, $this->patientEventId, $this->patientId, time());
        return APP_PATH_TEMP . 'send_rx_' . implode('_', $components) . '.' . $extension;
    }

    /**
     * Logs message send operation.
     */
    protected function log($msg_type, $success, $recipients, $subject, $body) {
        // Appending a new entry to the log list.
        $this->logs[] = array($msg_type, $success, time(), $recipients, $this->username, $subject, $body, $this->patientData['send_rx_pdf']);
        $contents = json_encode($this->logs);

        $file_path = $this->generateTmpFilePath('json');
        if (!file_put_contents($file_path, $contents)) {
            return false;
        }
        if (!$file_id = send_rx_upload_file($file_path)) {
            return false;
        }

        if (!empty($this->patientData['send_rx_logs'])) {
            // Removing old log file.
            send_rx_edoc_file_delete($this->patientData['send_rx_logs']);
        }

        send_rx_save_record_field($this->patientProjectId, $this->patientEventId, $this->patientId, 'send_rx_logs', $file_id);
    }

    /**
     * Gets data to be used as source for Piping on templates and messages.
     */
    protected function getPipingData() {
        global $redcap_version;

        $base_path = APP_PATH_WEBROOT_FULL . 'redcap_v' . $redcap_version . '/DataEntry/';
        $data = array(
            'current_date' => date('m/d/Y'),
            'current_time' => date('h:i a'),
            'patient_url' => $base_path . 'record_home.php?pid=' . $this->patientProjectId . '&id=' . $this->patientId,
            'pharmacy_url' => $base_path . 'record_home.php?pid=' . $this->pharmacyProjectId . '&id=' . $this->pharmacyId,
            'project' => isset($this->pharmacyConfig->variables) ? $this->pharmacyConfig->variables : array(),
            'patient' => $this->preprocessData($this->patientData, $this->patientProj),
            'pharmacy' => $this->preprocessData($this->pharmacyData, $this->pharmacyProj),
            'prescriber' => $this->preprocessData($this->prescriberData, $this->pharmacyProj),
        );

        return $data;
    }

    /**
     * Builds file URL for download.
     */
    protected function buildFileUrl($file_id, $field_name, $project_type = 'patient') {
        global $redcap_version;

        $url = APP_PATH_WEBROOT_FULL . 'redcap_v' . $redcap_version . '/DataEntry/';
        $url .= 'file_download.php?pid=' . $this->{$project_type . 'ProjectId'};

        $query_params = array(
            'record' => $this->{$project_type . 'Id'},
            'event_id' => $this->{$project_type . 'EventId'},
            'instance' => 1,
            'field_name' => $field_name,
            'id' => $file_id,
            'doc_id_hash' => Files::docIdHash($file_id),
        );

        foreach ($query_params as $key => $value) {
            $url .= '&' . $key . '=' . $value;
        }

        return $url;
    }
}
