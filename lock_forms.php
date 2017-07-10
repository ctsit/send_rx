<?php

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
		}
		return false;
	}

	/**
	  * This method takes event_id(int), form_name("string"), and instances("array") as params
	  * and locks all the instances provided.
	  */
	function lockForm($event_id, $form_name, $instances=array()) {
		if (!isset($event_id) || !isSet($form_name)) {
			return false;
		}
		foreach($instances as $currInstance) {
			lockInstance($event_id, $form_name, $currInstance);
		}
		return true;
	}

	function lockEvent($event_id) {
		if (!isset($event_id)) {
			return false;
		}
		// forms is of type arrray.
		$forms = getFormsInAnEvent($event_id);

		foreach($forms as $currForm) {
			$instances = getInstancesInForm();
			lockForm($event_id, $form_name, $instances);
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
		//select * from redcap_events_forms where event_id = 40 ;
		$sql = "SELECT form_name from redcap_events_forms where event_id = ?";
		$result = array();
		if ($stmt=$this->conn->prepare($sql)) {
			/* bind variables to prepared statement */
			$stmt->bind_param("i", $this->event_id);
			
			$stmt->execute();
			$stmt->bind_result($col1);
			
      		while ($stmt->fetch()) {
      			$result[] = $col1;
      		}
			return $result;
		}
	}

	function insertData($event_id, $form_name, $instance) {
		$sql = "INSERT INTO redcap_locking_data (project_id, record, event_id, form_name, instance, username, timestamp ) VALUES (?, ?, ?, ?, ?, ?, now())";
		if ($stmt=$this->conn->prepare($sql)) {
			$stmt->bind_param("isisis", $this->project_id, $this->record_id, $event_id, $form_name, $instance, $this->username);
			if ($stmt->execute()) {
			// echo "New record created successfully." . '<br>';
				return true;
			}
		}
		return false;
	}

}

?>
