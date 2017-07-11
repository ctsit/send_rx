<?php

if (!file_exists('../../redcap_connect.php')) {
    $REDCAP_ROOT = "/var/www/redcap";
    require_once $REDCAP_ROOT . '/redcap_connect.php';
} else {
    require_once '../../redcap_connect.php';
}

class lock_record {
	var $username;
	var $project_id;
	var $record_id;
	var $conn;
	var $project_data;
	
	function test() {
		return true;
	}

	function __construct($username, $project_id, $record_id) {
		global $conn, $Proj;
		$this->project_data = $Proj;
    	$this->conn = $conn;
		$this->username = $username;
		$this->project_id = $project_id;
		$this->record_id = $record_id;
	}

	function lockInstance($event_id, $form_name, $instance=1) {
		if (isSet($event_id) && isSet($form_name)) {
			$this->insertData($event_id, $form_name, $instance);
			return true;
		}
		return false;
	}

	/**
	  * This method takes event_id(int), form_name("string"), and instances("array") as params
	  * and locks all the instances provided.
	  */
	function lockForm($event_id, $form_name, $instances=null) {
		if (!isset($event_id) || !isSet($form_name)) {
			return false;
		}
		if (!isSet($instances)) {
			$instances = $this->getInstancesInForm($event_id, $form_name);
		}
		echo '<pre>'. '<br>';
		echo var_dump($instances). '<br>';
		echo '</pre>'. '<br>';
		foreach($instances as $currInstance) {
			$this->lockInstance($event_id, $form_name, $currInstance);
		}
		return true;
	}

	function lockEvent($event_id, $forms = null) {
		if (!isset($event_id)) {
			return false;
		}
		if (!isSet($forms)) {
			$forms = $this->getFormsInAnEvent($event_id);
		}

		foreach($forms as $currForm) {
			$this->lockForm($event_id, $currForm);
		}
		return true;
	}
	
	function getInstancesInForm($event_id, $form_name) {
		$result = array();

		$data = REDCap::getData($this->project_id, 'json', $this->record_id, null, $event_id);
		$json_data = json_decode($data);
		$event_name = REDCap::getEventNames(true, true, $event_id);
		$result = array();
		foreach($json_data as $key) {
			if ($key->redcap_event_name == $event_name && $key->redcap_repeat_instrument == $form_name ) {
				$result[] = $key->redcap_repeat_instance;
			}
		}
		if (empty($result)) {
			$result[] = 1;
		}
		return $result;
	}

	function getFormsInAnEvent($event_id) {
		$result = $this->project_data->eventsForms[$event_id];
		if (isSet($result)) {
			return $result;
		}
		return array();
	}

	function insertData($event_id, $form_name, $instance) {
		$sql = "INSERT INTO redcap_locking_data (project_id, record, event_id, form_name, instance, username, timestamp ) VALUES (?, ?, ?, ?, ?, ?, now())";
		if ($stmt=$this->conn->prepare($sql)) {
			$stmt->bind_param("isisis", $this->project_id, $this->record_id, $event_id, $form_name, $instance, $this->username);
			if ($stmt->execute()) {
				return true;
			}
		}
		return false;
	}

}

?>
