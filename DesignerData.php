<?php

/*
* imports the redcap_connect.php file so that it can access the global variable $proj
*/
if (!file_exists('../../redcap_connect.php')) {
    $REDCAP_ROOT = "/var/www/redcap";
    require_once $REDCAP_ROOT . '/redcap_connect.php';
} else {
    require_once '../../redcap_connect.php';
}

/**
 * DesignerData is used to get the design details of the fields or instruments etc.. 
 * Currently it supports method to return instruments or instances list.
 */
class DesignerData {
	
	/**
     * Used to hold global project data object.
     *
     * @var mixed
     */
	var $project_data;

	/**
     * Creates the LockRecord object with given parameters.
     *
     * @return object
     *   Returns an instance of DesignerData.
     */
	function __construct() {
		global $Proj;
		$this->project_data = $Proj;
	}

	/**
	 * This method returns the array of instances, for the given event_id and form name.
	 * 
	 * @param $event_id
	 * Event Id
	 * 
	 * @param $form_name
	 * array of forms or instruments
	 * 
	 * @return $result
	 * returns array of instances cerated for current form.
	 */
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

	/**
	 * This method returns an array of foms, for the given event_id.
	 * 
	 * @param $event_id
	 * Event Id
	 * 
	 * @return array
	 */
	function getFormsInAnEvent($event_id) {
		$result = $this->project_data->eventsForms[$event_id];
		if (isSet($result)) {
			return $result;
		}
		return array();
	}

}

?>