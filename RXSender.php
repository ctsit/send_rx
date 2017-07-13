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

        $this->setPatientData($data);
        $this->pharmacyId = $this->patientData['send_rx_pharmacy_id'];

        if (!$data = send_rx_get_record_data($this->pharmacyProjectId, $this->pharmacyId)) {
            return;
        }

        $this->setPharmacyData($data);

        if (!empty($data['send_rx_logs']) && ($logs = send_rx_get_file_contents($data['send_rx_logs']))) {
            $this->logs = json_decode($logs, true);
        }

        if (!$data = send_rx_get_repeat_instance_data($this->pharmacyProjectId, $this->pharmacyId, 'send_rx_users')) {
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
     * Sends the message to the pharmacy and returns whether the operation was successful.
     */
    function send($file_id = null, $log = true) {
        if ($file_id && !($file_id = $this->generatePDFFile())) {
            return false;
        }

        // Checking whether file is already available for download bia Send It UI.
        if (!$document_id = send_rx_sendit_document_exists($file_id)) {
            $expire_time = date('Y-m-d H:i:s', '+' . $this->patientConfig->expireTime . ' days');
            if (!$document_id = send_rx_create_sendit_docs($file_id, $expire_time, $this->username)) {
                return false;
            }
        }

        // Getting data to apply Piping.
        $data = $this->getPipingData();
        $subject = send_rx_piping($this->pharmacyConfig->messageSubject, $data);
        $body = send_rx_piping($this->pharmacyConfig->messageBody, $data);

        switch ($this->getDeliveryMethod()) {
            case 'email':
                // Validate all the email addresses.
                $recipients = str_replace(array("\r\n","\n","\r",";"," "), array(',',',',',',',',''), $this->pharmacyData['send_rx_pharmacy_emails']);
                $recipients = explode(',', $recipients);

                $all_success = TRUE;
                foreach ($recipients as $recipient) {
                    $recipient = trim($recipient);
                    if ($recipient != '' && !isEmail($recipient)) {
                        continue;
                    }

                    $key = strtoupper(substr(uniqid(sha1(mt_rand())), 0, 25));
                    $pwd = generateRandomHash(8, false, true);

                    // Allowing recipient to download the PDF file.
                    send_rx_create_sendit_recipient($recipient, $document_id, $send_confirmation, $key, $pwd);

                    // Download URL.
                    $url = APP_PATH_WEBROOT_FULL . 'redcap_v' . $redcap_version . '/SendIt/download.php?' . $key;
                    $body = send_rx_piping($body, array('pdf_file_url' => $url, 'pdf_file_pwd' => $pwd));

                    if (!$success = REDCap::email($this->pharmacyData['send_rx_pharmacy_emails'], $subject, $body)) {
                        $all_success = FALSE;
                    }
                }

                return $all_success;

            case 'hl7':
                // TODO: handle HL7 messages.
                break;
        }

        return false;
    }

    /**
     * Generates the prescription PDF file.
     */
    function generatePDFFile() {
        $data = $this->getPipingData();
        $contents = send_rx_piping($this->pharmacyConfig->pdfTemplate, $data);
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
     * Logs whether the message send operation was successful
     */
    protected function log($success, $emails, $subject, $body) {
        // Appending a new entry to the log list.
        $this->logs[] = array($success, time(), $emails, $this->username, $this->getDeliveryMethod(), $subject, $body);
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
    protected function getPipingData() {
        return array(
            'patient' => $this->getPatientData(),
            'pharmacy' => $this->getPharmacyData(),
            'prescriber' => $this->getPrescriberData(),
        );
    }
}
