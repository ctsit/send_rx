<?php
    return function($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
        /*
            New PDF is generated based on pdf_is_updated flag.
            Reset flag once PDF is generated to avoid duplicate generation of the same PDF.
         */
        require_once 'send_rx_functions.php';
        require_once 'RxSender.php';

        global $Proj;

        if ($config = send_rx_get_project_config($project_id, 'site')) {
            if ($Proj->metadata['send_rx_dag_id']['form_name'] == $instrument) {
                // Hiding DAG ID field.
                echo '<script>$(document).ready(function() { $(\'#send_rx_dag_id-tr\').hide(); });</script>';
            }

            return;
        }
        
        // Checking if PDF file exists.
        if (!isset($Proj->metadata['send_rx_pdf'])) {
            return;
        }

        // Checking if we are on PDF form step.
        if ($Proj->metadata['send_rx_pdf']['form_name'] != $instrument) {
            return;
        }

        // Getting Rx sender to make sure we are in a patient project.
        if (!$sender = RxSender::getSender($project_id, $event_id, $record)) {
            return;
        }

        $table = '<div class="info">This prescription has not been sent yet.</div>';
        if ($logs = $sender->getLogs()) {
            // Message types.
            $types = array(
                'email' => 'Email',
                'hl7' => 'HL7',
            );

            // Rows header.
            $header = array('Type', 'Success', 'Time', 'Recipients', 'User ID', 'Subject', 'Body');

            // Creating logs table.
            $table = '<div class="table-responsive"><table class="table table-condensed"><thead><tr>';
            foreach (range(0, 2) as $i) {
                $table .= '<th>' . $header[$i] . '</th>';
            }
            $table .= '<th></th></thead></tr><tbody>';

            // Modals container for the logs details.
            $modals = '<div id="send-rx-logs-details">';

            // Populating tables and creating one modal for each entry.
            foreach (array_reverse($logs) as $key => $row) {
                $table .= '<tr>';

                $row[0] = $types[$row[0]];
                $row[1] = $row[1] ? 'Yes' : 'No';
                $row[2] = date('m/d/y - h:i a', $row[2]);
                $row[3] = str_replace(',', '<br>', $row[3]);

                foreach (range(0, 2) as $i) {
                    $table .= '<td>' . $row[$i] . '</td>';
                }

                // Setting up the button that triggers the details modal.
                $table .= '<td><button type="button" class="btn btn-info btn-xs" data-toggle="modal" data-target="#send-rx-logs-details-' . $key . '">See details</button></td>';
                $table .= '</tr>';

                $modals .= '
                    <div class="modal fade" id="send-rx-logs-details-' . $key . '" role="dialog">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                    <h4>Message Details</h4>
                                </div>
                                <div class="modal-body" style="overflow-wrap:break-word;word-wrap:break-word;"><form>';

                foreach (range(3, 6) as $i) {
                    $modals .= '
                        <div class="form-group row">
                            <label class="col-sm-2 col-form-label">' . $header[$i] . '</label>
                            <div class="col-sm-10">' . $row[$i] . '</div>
                        </div>';
                }

                $modals .= '</form></div></div></div></div>';
            }

            $table .= '</tbody></table></div>';
            $modals .= '</div>';

            // Placing modals.
            echo $modals;
        }

        // Checking for PDF is updated flag.
        $sql = '
            SELECT value FROM redcap_data
            WHERE
                field_name = "send_rx_pdf_is_updated" AND
                project_id = ' . db_escape($project_id) . ' AND
                event_id = ' . db_escape($event_id) . ' AND
                record = "' . db_escape($record) . '"
            LIMIT 1';

        $q = db_query($sql);

        $pdf_is_updated = false;
        if (db_num_rows($q)) {
            $result = db_fetch_assoc($q);
            $pdf_is_updated = $result['value'];
        }

        // Checking if event is complete.
        $event_is_complete = send_rx_event_is_complete($project_id, $record, $event_id, array($instrument));

        // Checking if PDF needs to be generated.
        if (!$pdf_is_updated) {
            if ($sender->getPrescriberData()) {
                /*
                    Generate PDF.
                */
                $sender->generatePDFFile();
                send_rx_save_record_field($project_id, $event_id, $record, 'send_rx_pdf_is_updated', '1', $repeat_instance);
                echo '<div class="darkgreen" style="margin-bottom:30px;"><img src="' . APP_PATH_IMAGES . 'tick.png"> A new prescription PDF preview has been created.</div>';
            }
            else {
                $event_is_complete = false;
            }
        }

        ?>
        <script type="text/javascript">
            /*
                All DOM modifications for the final instrument.
            */
            $(document).ready(function() {
                var event_is_complete = <?php echo $event_is_complete ? 'true' : 'false'; ?>;
                var instrument_name = '<?php echo $instrument; ?>';
                var helpTxt = '<div style="color: #666;font-size: 11px;"><b>ATTENTION:</b> The prescription will be sent by submitting this form.</div>';

                $(helpTxt).insertBefore($('button[name="submit-btn-cancel"]')[0]);

                // Removing operation buttons on PDF file.
                if ($('#send_rx_pdf-linknew')) {
                    $('#send_rx_pdf-linknew').remove();
                }

                $('#submit-btn-saverecord').html('Send & Exit Form');
                $('#submit-btn-savecontinue').html('Send & Stay');

                // Showing logs table.
                $('#send_rx_logs-tr .data').html('<?php echo $table; ?>');

                // Changing color of submit buttons.
                var $submit_buttons = $('#submit-btn-saverecord, #submit-btn-savecontinue');
                $submit_buttons.addClass('btn-success');

                // Disables submit buttons.
                var disableSubmit = function() {
                    $submit_buttons.prop('disabled', true);
                    $submit_buttons.prop('title', 'Your must complete all form steps before sending the prescription.');
                };

                // Enables submit buttons.
                var enableSubmit = function() {
                    $submit_buttons.prop('disabled', false);
                    $submit_buttons.prop('title', null);
                };

                if (event_is_complete) {
                    var $complete = $('select[name="' + instrument_name + '_complete"]');
                    if ($complete.val() !== '2') {
                        // Disables submit buttons if initial state not complete.
                        disableSubmit();
                    }

                    $complete.change(function() {
                        if ($(this).val() === '2') {
                            // Enables submit buttons if form becomes complete.
                            enableSubmit();
                        }
                        else {
                            // Disables submit buttons if form becomes not complete.
                            disableSubmit();
                        }
                    });
                }
                else {
                    // If form is not complete, submit buttons must remain disabled.
                    disableSubmit();
                }
            });
        </script>
        <?php
    };
?>
