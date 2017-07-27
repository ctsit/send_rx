<?php
    return function($project_id) {
        if (PAGE != 'DataEntry/index.php') {
            return;
        }

        if (!empty($_GET['id'])) {
            $record = $_GET['id'];
        }
        elseif (!empty($_POST['patient_id'])) {
            $record = $_POST['patient_id'];
        }
        else {
            return;
        }

        require_once 'send_rx_functions.php';

        if (!$config = send_rx_get_project_config($project_id, 'patient')) {
            return;
        }

        if (!$group_id = Records::getRecordGroupId($project_id, $record)) {
            $parts = explode('-', $record);
            if (count($parts) != 2) {
                return;
            }

            $group_id = $parts[0];
        }

        $prescribers = send_rx_get_group_members($project_id, $group_id, 'prescriber');
        if (isset($prescribers[USERID])) {
            global $super_user;

            if (!$super_user) {
                $data = send_rx_get_record_data($project_id, $record, $_GET['event_id']);
                if (!empty($data) && !empty($data['send_rx_prescriber_id']) && $data['send_rx_prescriber_id'] != USERID) {
                    // Prescribers cannot access prescriptions that do not belong to them.
                    send_rx_access_denied();
                }
            }
        }

        global $Proj;
        if (!isset($Proj->metadata['send_rx_prescriber_id']) || $_GET['page'] != $Proj->metadata['send_rx_prescriber_id']['form_name']) {
            return;
        }

        $options = array();
        foreach ($prescribers as $username => $prescriber) {
            $options[$username] = $username . ',' . $prescriber['user_firstname'] . ' ' . $prescriber['user_lastname'];
        }

        if (isset($options[USERID])) {
            $options = array($options[USERID]);
            $parts = explode(',', reset($options));

            ?>
            <script type="text/javascript">
                document.addEventListener('DOMContentLoaded', function() {
                    $('select[name="send_rx_prescriber_id"] option[value="<?php echo USERID; ?>"]').prop('selected', true);
                    $('#send_rx_prescriber_id-tr').hide();
                });
            </script>
            <?php
        }

        // Adding prescriber options.
        $Proj->metadata['send_rx_prescriber_id']['element_enum'] = implode('\\n', $options);
    };
?>
