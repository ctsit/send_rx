<?php
/**
 * @file
 * Provides RXSender class.
 */

require_once 'send_rx_functions.php';
require_once 'LockUtil.php';

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
     * Site project ID.
     *
     * @var int
     */
    protected $siteProjectId;

    /**
     * Site event ID.
     *
     * @var int
     */
    protected $siteEventId;

    /**
     * Site record ID.
     *
     * @var int
     */
    protected $siteId;

    /**
     * Site record data.
     *
     * @var array
     */
    protected $siteData;

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
     * Site configuration data.
     *
     * @var object
     */
    protected $siteConfig;

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
     * Site project metadata.
     *
     * @var Project.
     */
    protected $siteProj;

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
    static function getSender($project_id, $event_id, $patient_id) {
        if (!$config = send_rx_get_project_config($project_id, 'patient')) {
            return false;
        }

        $class = empty($config['sender_class']) ? 'RxSender' : $config['sender_class'];
        if (!class_exists($class)) {
            return false;
        }

        return new $class($project_id, $event_id, $patient_id);
    }

    /**
     * Constructor.
     *
     * Sets up properties and processes data to be easily read.
     *
     * TODO: refactor constructor.
     */
    function __construct($project_id, $event_id, $patient_id) {
        $this->patientProjectId = $project_id;
        $this->patientProj = new Project($this->patientProjectId);
        $this->patientId = $patient_id;
        $this->patientEventId = $event_id;

        $this->locker = new LockUtil(USERID, $this->patientProjectId, $this->patientId);

        // Getting patient project config.
        if (!$config = send_rx_get_project_config($project_id, 'patient')) {
            return;
        }

        $this->patientConfig = $config;
        $this->siteProjectId = $config['target_project_id'];
        $this->siteProj = new Project($this->siteProjectId);

        // Getting site project config.
        if (!$config = send_rx_get_project_config($this->siteProjectId, 'site')) {
            return;
        }

        $this->siteConfig = $config;

        // Getting patient data.
        if (!$data = send_rx_get_record_data($this->patientProjectId, $this->patientId, $this->patientEventId)) {
            return;
        }

        $this->setPatientData($data);
        $this->username = $this->patientData['send_rx_prescriber_id'];

        // Getting logs.
        if (!empty($this->patientData['send_rx_logs']) && ($logs = send_rx_get_edoc_file_contents($this->patientData['send_rx_logs']))) {
            $this->logs = json_decode($logs, true);
        }

        // Getting DAG.
        if (!$group_id = Records::getRecordGroupId($this->patientProjectId, $this->patientId)) {
            $parts = explode('_', $this->patientId);

            if (count($parts) != 2) {
                return false;
            }

            $group_id = $parts[0];
        }

        // Getting site ID.
        if (!$this->siteId = send_rx_get_site_id_from_dag($this->siteProjectId, $group_id)) {
            return;
        }

        // Getting site data.
        if (!$data = send_rx_get_record_data($this->siteProjectId, $this->siteId)) {
            return;
        }

        reset($data);
        $this->siteEventId = key($data);
        $this->setSiteData($data[$this->siteEventId]);

        $instrument = $this->siteProj->metadata['send_rx_user_id']['form_name'];
        if (!$data = send_rx_get_repeat_instrument_instances($this->siteProjectId, $this->siteId, $instrument)) {
            return;
        }

        // Setting up prescriber data.
        foreach ($data as $value) {
            if ($value['send_rx_user_id'] == $this->username) {
                $this->setPrescriberData($value);
                break;
            }
        }
    }

    /**
     * Gets the patient data.
     */
    function getPatientData() {
        return $this->patientData;
    }

    /**
     * Gets the site data.
     */
    function getSiteData() {
        return $this->siteData;
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
     * Gets the site configuration array.
     *
     * Contains important settings to build the message and PDF contents.
     */
    function getSiteConfig() {
        return $this->siteConfig;
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
     * Sets site data.
     */
    protected function setSiteData($data) {
        $this->siteData = $data;
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
        $pdf_template = $this->siteConfig['pdf_template_name'];
        if (!empty($this->siteData['send_rx_pdf_template'])) {
            $pdf_template = $this->siteData['send_rx_pdf_template'];
        }

        $sql = '
            SELECT e.doc_id FROM redcap_docs_to_edocs e
            INNER JOIN redcap_docs d ON
                d.docs_id = e.docs_id AND
                d.project_id = "' . db_escape($this->siteProjectId) . '" AND
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
        $project_type = isset($proj->metadata['patient_id']) ? 'patient' : 'site';
        foreach ($data as $field_name => $value) {
            if (!isset($proj->metadata[$field_name]) || empty($value)) {
                continue;
            }

            if (in_array($proj->metadata[$field_name]['element_type'], array('select', 'radio'))) {
                if (!$options = explode('\\n', $proj->metadata[$field_name]['element_enum'])) {
                    continue;
                }

                foreach ($options as $option) {
                    list($key, $label) = explode(',', $option);

                    if (trim($key) == $value) {
                        $data[$field_name] = trim($label);
                        break;
                    }
                }
            }
            elseif ($proj->metadata[$field_name]['element_type'] == 'file') {
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
        }

        return $data;
    }

    /**
     * Sends the message to the site and returns whether the operation was successful.
     */
    function send($generate_pdf = true, $log = true) {
        $success = false;

        if ($generate_pdf) {
            $this->generatePDFFile();
        }

        // Getting data to apply Piping.
        $data = $this->getPipingData();
        foreach ($this->siteData['send_rx_delivery_methods'] as $type => $flag) {
            if (!$flag) {
                continue;
            }

            $message = array();
            foreach (array('subject', 'body') as $section) {
                $field = 'send_rx_' . $type . '_' . $section;
                $message[$section] = send_rx_piping(empty($this->siteData[$field]) ? $this->siteConfig['message_' . $section] : $this->siteData[$field], $data);
            }

            switch ($type) {
                case 'email':
                    // TODO: Discuss and define properly the "from" address.
                    $success = REDCap::email($this->siteData['send_rx_email_recipients'], $this->prescriberData['send_rx_user_email'], $message['subject'], $message['body']);
                    $this->log($type, $success, $this->siteData['send_rx_email_recipients'], $message['subject'], $message['body']);

                    break;

                case 'hl7':
                    // TODO: handle HL7 messages.
                    break;
            }
        }

        if (!empty($this->patientConfig['lock_instruments'])) {
            $this->locker->lockEvent($this->patientEventId, $this->patientConfig['lock_instruments']);
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
            'site_url' => $base_path . 'record_home.php?pid=' . $this->siteProjectId . '&id=' . $this->siteId,
            'project' => isset($this->siteConfig['pdf_template_variables']) ? $this->siteConfig['pdf_template_variables'] : array(),
            'patient' => $this->preprocessData($this->patientData, $this->patientProj),
            'site' => $this->preprocessData($this->siteData, $this->siteProj),
            'prescriber' => $this->preprocessData($this->prescriberData, $this->siteProj),
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
