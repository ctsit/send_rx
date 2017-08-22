<?php

/**
 * Imports the redcap_connect file so that it can access the global variable $conn
 */
require_once APP_PATH_DOCROOT . '../redcap_connect.php';

/**
 * LockUtil class is used for locking purpose, it locks events, forms and instances
 */
class LockUtil {

    /**
     * Redcaps username.
     *
     * @var string
     */
    var $username;
         
    /**
     * Project id.
     *
     * @var int
     */
    var $project_id;

    /**
     * Record id.
     *
     * @var string
     */
    var $record_id;

    /**
     * Used to hold gobal connection object.
     *
     * @var string
     */
    var $conn;
    
    /**
     * Creates the LockRecord object with given parameters.
     *
     * @param string $username
     *   The username.
     * @param int $project_id
     *   Data entry project ID.
     * @param int $record_id
     *   Data entry record ID.
     *
     * @return object|bool
     *   An object instance of LockRecord for the given project, if success.
     *   False otherwise.
     */
    function __construct($username, $project_id, $record_id) {
        if (!isSet($username) || !isSet($project_id) || !isSet($record_id)) {
            return false;
        }
        global $conn;
        $this->conn = $conn;
        $this->username = $username;
        $this->project_id = $project_id;
        $this->record_id = $record_id;
    }

    /**
     * Creates the LockRecord object with given parameters.
     *
     * @param string $username
     *   The username.
     * @param int $project_id
     *   Data entry project ID.
     * @param int $record_id
     *   Data entry record ID.
     *
     * @return object|bool
     *   An object instance of LockRecord for the given project, if success.
     *   False otherwise.
     */
    function lockInstance($event_id, $form_name, $instance=1) {
        if (isSet($event_id) && isSet($form_name)) {
            $this->insertData($event_id, $form_name, $instance);
            return true;
        }
        return false;
    }

    /**
     * This method locks the entire form, even if repeatable instances present
     * if instances array is passed as param then it only locks those intances in the form.
     * 
     * Event Id
     * @param $event_id
     * 
     * Form or instrument name
     * @param $form_name
     *
     * array of instances
     * @param $instances
     * 
     * @return boolean
     */
    function lockForm($event_id, $form_name, $instances=null) {
        if (!isset($event_id) || !isSet($form_name)) {
            return false;
        }
        if (!isSet($instances)) {
            $design_data = new DesignerData();
            $instances = $design_data->getInstancesInForm($event_id, $form_name);
            // $instances = $this->getInstancesInForm($event_id, $form_name);
        }

        foreach($instances as $currInstance) {
            $this->lockInstance($event_id, $form_name, $currInstance);
        }
        return true;
    }

    /**
     * This method locks the entire event, it takes care of locking all forms and instances in it.
     * If forms array param is given then it only locks those forms.
     * 
     * Event Id
     * @param $event_id
     * 
     * array of forms or instruments
     * @param $forms
     * 
     * @return boolean
     */
    function lockEvent($event_id, $forms = null) {
        if (!isset($event_id)) {
            return false;
        }
        if (!isSet($forms)) {
            $design_data = new DesignerData();
            $forms = $design_data->getFormsInAnEvent($event_id);
            // $forms = $this->getFormsInAnEvent($event_id);
        }

        foreach($forms as $currForm) {
            $this->lockForm($event_id, $currForm);
        }
        return true;
    }

    /**
     * This method takes event_id, form_name and instance as parameters and locks the instance, 
     * by inserting an entry into redcap_locking_data table.
     * 
     * Event Id
     * @param $event_id
     * 
     * Form or instrument name
     * @param $form_name
     *
     * Instance number
     * @param $instance
     * 
     * @return boolean
     */
    function insertData($event_id, $form_name, $instance) {
        $sql = "INSERT INTO redcap_locking_data (project_id, record, event_id, form_name, instance, username, timestamp ) VALUES (?, ?, ?, ?, ?, ?, now())";
        if ($stmt = $this->conn->prepare($sql)) {
            $stmt->bind_param("isisis", $this->project_id, $this->record_id, $event_id, $form_name, $instance, $this->username);
            if ($stmt->execute()) {
                return true;
            }
        }
        return false;
    }

}

?>
