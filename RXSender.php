<?php
/**
 * @file
 * Provides RXSender class.
 */

include_once 'send_rx_functions.php';

abstract class RXSender {

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
     * Username.
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
     * Configuration data.
     *
     * @var array
     */
    protected $config;

    /**
     * Constructor.
     *
     * Sets up properties and processes data to be easily read.
     */
    function __construct($project_id, $event_id, $patient_id, $username) {
        $this->patientProjectId = $project_id;
        $this->patientId = $patient_id;
        $this->patientEventId = $event_id;
        $this->username = $username;

        if (!$config = send_rx_get_project_config($project_id, 'patient')) {
            return;
        }

        $this->pharmacyProjectId = $config['pharmacy_project_id'];
        if (!$config = send_rx_get_project_config($this->pharmacyProjectId, 'pharmacy')) {
            return;
        }

        $this->setConfig($config);

        if (!$data = send_rx_get_record_data($project_id, $record_id, $event_id)) {
            return;
        }

        $this->setPatientData($data);
        $this->pharmacyId = $this->patientData['send_rx_pharmacy_id'];

        if (!$data = send_rx_get_record_data($config['pharmacy_project_id'], $this->pharmacyId)) {
            return;
        }

        $data['send_rx_logs'] = json_decode($data['send_rx_logs']);
        $this->setPharmacyData($data);

        if (!$data = send_rx_get_repeat_instance_data($config['pharmacy_project_id'], $this->pharmacyId, 'send_rx_users')) {
            return;
        }

        foreach ($data as $value) {
            if ($value['send_rx_username'] == $username) {
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
     * Gets the pharmacy data.
     */
    function getPharmacyData() {
        return $this->pharmacyData;
    }

    /**
     * Gets the configuration array.
     *
     * Contains important guidelines to build the message.
     */
    function getPrescriberData() {
        return $this->prescriberData;
    }

    /**
     * Gets the configuration array.
     *
     * Contains important settings to build the message and PDF contents.
     */
    function getConfig() {
        return $this->config;
    }

    /**
     * Gets the delivery method.
     */
    function getDeliveryMethod() {
        return $this->pharmacyData['send_rx_delivery_method'];
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
     * Sets config data.
     */
    protected function setConfig($config) {
        $this->config = $config;
    }

    /**
     * Sends the message to the pharmacy and returns whether the operation was successful.
     */
    function send($pdf_file_url = NULL, $log = TRUE) {
        if (!$pdf_file_url && !($pdf_file_url = $this->generatePDFFile())) {
            return FALSE;
        }

        $data = $this->getPipingData($pdf_file_url);
        $subject = send_rx_piping($this->config['message_subject'], $data);
        $body = send_rx_piping($this->config['message_body'], $data);

        switch ($this->getDeliveryMethod()) {
            case 'email':
                $success = REDCap::email($this->pharmacyData['send_rx_emails'], $subject, $body);
                $this->log($success, $this->pharmacyData['send_rx_emails'], $subject, $body);

                return $success;

            case 'hl7':
                // TODO: handle HL7 messages.
                break;
        }

        return FALSE;
    }

    /**
     * Generates the prescription PDF file.
     */
    function generatePDFFile() {
        $data = $this->getPipingData();
        $contents = send_rx_piping($this->config['pdf_template'], $data);
        $file_path = $this->generateFilePath();

        if (send_rx_generate_pdf_file($contents, $file_path)) {
            return $file_path;
        }

        return FALSE;
    }

    /**
     * Generates a default PDF file path.
     */
    protected function generateFilePath() {
        $components = array($this->patientProjectId, $this->patientEventId, $this->patientId, time());
        return 'send_rx_' . implode('_', $components) . '.pdf';
    }

    /**
     * Logs whether the message send operation was successful
     */
    protected function log($success, $emails, $subject, $body) {
        $this->patientData['send_rx_logs'][] = array(
            $success,
            time(),
            $emails,
            $this->username,
            $this->getDeliveryMethod(),
            $subject,
            $body,
        );
        
        send_rx_update_record_field(
            $this->patientProjectId,
            $this->patientEventId,
            $this->patientId,
            'send_rx_logs',
            json_encode($this->patientData['send_rx_logs'])
        );
    }

    /**
     * Gets data to be used as source for Piping on templates and messages.
     */
    protected function getPipingData($pdf_file_url = '') {
        return array(
            'pdf_file_url' => $pdf_file_url,
            'patient' => $this->getPatientData(),
            'pharmacy' => $this->getPharmacyData(),
            'prescriber' => $this->getPrescriberData(),
        );
    }
}
