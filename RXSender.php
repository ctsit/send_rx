<?php
/**
 * @file
 * Provides RXSender class.
 */

include_once 'send_rx_functions.php';

class RxSender {

    /**
     * Patient project ID.
     *
     * @var int
     */
    protected $patientProjectId;

    /**
     * Patient event name.
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
     * Constructor.
     *
     * Sets up properties and processes data to be easily read.
     */
    function __construct($project_id, $event_id, $patient_id, $username = USERID) {
        $this->patientProjectId = $project_id;
        $this->patientId = $patient_id;
        $this->patientEventId = $event_id;
        $this->username = $username;

        if (!$config = send_rx_get_project_config($project_id, 'patient')) {
            return;
        }

        $this->patientConfig = $config;
        $this->pharmacyProjectId = $config->targetProjectId;

        if (!$config = send_rx_get_project_config($this->pharmacyProjectId, 'pharmacy')) {
            return;
        }

        $this->pharmacyConfig = $config;

        if (!$data = send_rx_get_record_data($project_id, $record_id, $event_id)) {
            return;
        }

        if (!empty($data['send_rx_logs']) && ($logs = send_rx_get_file_contents($data['send_rx_logs']))) {
            $this->logs = json_decode($logs, true);
        }

        $this->setPatientData($data);
        $this->pharmacyId = $this->patientData['send_rx_pharmacy_id'];

        if (!$data = send_rx_get_record_data($this->pharmacyProjectId, $this->pharmacyId)) {
            return;
        }

        $this->setPharmacyData($data);

        if (!$data = send_rx_get_repeat_instance_data($this->pharmacyProjectId, $this->pharmacyId, 'prescribers')) {
            return;
        }

        foreach ($data as $value) {
            if ($value['send_rx_username'] == $username) {
                $this->setPrescriberData($value);
                break;
            }
        }

        if (!$data = send_rx_get_repeat_instance_data($this->pharmacyProjectId, $this->pharmacyId, 'delivery_methods')) {
            return;
        }

        foreach ($data as $value) {
            $this->deliveryMethods[$value['send_rx_delivery_type']] = $value;
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
        return $this->patientData['send_rx_logs'];
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
     * Sends the message to the pharmacy and returns whether the operation was successful.
     */
    function send($file_id = null, $log = true) {
        if (!$file_id) {
            $file_id = $this->generatePDFFile();
        }

        // Getting data to apply Piping.
        $data = $this->getPipingData($file_id);

        foreach ($this->getDeliveryMethods() as $msg_type => $config) {
            // Getting templates.
            $subject = empty($config['send_rx_message_subject']) ? $this->pharmacyConfig->messageSubject : $config['send_rx_message_subject'];
            $body = empty($config['send_rx_message_body']) ? $this->pharmacyConfig->messageBody : $config['send_rx_message_body'];

            // Replacing wildcards.
            $subject = send_rx_piping($subject, $data);
            $body = send_rx_piping($body, $data);

            switch ($msg_type) {
                case 'email':
                    $success = REDCap::email($config['send_rx_pharmacy_emails'], $subject, $body);
                    $this->log($msg_type, $success, $this->pharmacyData['send_rx_pharmacy_emails'], $subject, $body);

                    return $success;

                case 'hl7':
                    // TODO: handle HL7 messages.
                    break;
            }
        }

        if (!empty($this->patientConfig['lockInstruments'])) {
            $locker = new LockRecord($this->username, $this->patientProjectId, $this->patientId);
            foreach ($this->patientConfig['lockInstruments'] as $instrument) {
                $locker->lockInstance($this->patientEventId, $instrument);
            }
        }

        return false;
    }

    /**
     * Generates the prescription PDF file.
     */
    function generatePDFFile() {
        if (!empty($this->pharmacyData['send_rx_pdf_template'])) {
            $pdf_template = $this->pharmacyData['send_rx_pdf_template'];
        }
        elseif (!empty($this->pharmacyConfig->pdfTemplate)) {
            $pdf_template = $this->pharmacyConfig->pdfTemplate;
        }
        else {
            return false;
        }

        $data = $this->getPipingData();

        // TODO: For now, $pdf_template contains the template contents.
        // Later it will contain a PDF file name, located at the files repo.
        // We will need to get this PDF contents, and apply Piping on it.
        $contents = send_rx_piping($pdf_template, $data);
        $file_path = $this->generateTmpFilePath('pdf');

        if (!send_rx_generate_pdf_file($contents, $file_path)) {
            return false;
        }
        if (!$file_id = send_rx_upload_file($file_path)) {
            return false;
        }

        send_rx_save_record_field($this->patientProjectId, $this->patientEventId, $this->patientId, 'send_rx_pdf', $file_id);
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
    protected function log($msg_type, $success, $emails, $subject, $body) {
        // Appending a new entry to the log list.
        $this->logs[] = array($msg_type, $success, time(), $emails, $this->username, $this->getDeliveryMethod(), $subject, $body);
        $contents = json_encode($this->logs);

        $file_path = $this->generateTmpFilePath('json');
        if (!file_put_contents($file_path, $contents)) {
            return false;
        }
        if (!$file_id = send_rx_upload_file($file_path)) {
            return false;
        }

        send_rx_save_record_field($this->patientProjectId, $this->patientEventId, $this->patientId, 'send_rx_logs', $file_id);
    }

    /**
     * Gets data to be used as source for Piping on templates and messages.
     */
    protected function getPipingData($file_id = null) {
        $base_path = APP_PATH_WEBROOT . 'DataEntry/';
        $data = array(
            'patient' => $this->getPatientData(),
            'pharmacy' => $this->getPharmacyData(),
            'prescriber' => $this->getPrescriberData(),
            'patient_url' => $base_path . 'index.php?pid=' . $this->patientProjectId . '&id=' . $this->patientId . '&event_id=' . $this->patientEventId,
        );

        if ($file_id) {
            $data['pdf_file_url'] = $base_path . 'file_download.php?pid=' . $this->patientProjectId;
            $query_params = array(
                'record' => $this->patientId,
                'event_id' => $this->patientEventId,
                'instance' => 1,
                'field_name' => 'send_rx_pdf',
                'id' => $file_id,
                'doc_id_hash' => Files::docIdHash($file_id),
            );

            foreach ($query_params as $key => $value) {
                $data['pdf_file_url'] .= '&' . $key . '=' . $value;
            }
        }


        return $data;
    }
}
