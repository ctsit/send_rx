<?php
    return function($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
        /*
            New PDF is generated based on pdf_is_updated flag.
            Reset flag once PDF is generated to avoid duplicate generation of the same PDF.
         */
        require_once 'send_rx_functions.php';
        require_once 'RxSender.php';

        global $Proj;

        /*
            Delete DAG from patients project when site is deleted from site project.
        */
        if (!$config = send_rx_get_project_config($project_id, 'pharmacy')) {
            return;
        }
        if (isset($Proj->metadata['send_rx_pharmacy_name']) && $Proj->metadata['send_rx_pharmacy_name']['form_name'] == $instrument) {
            ?>
            <script type="text/javascript">
                $(document).ready(function() {
                    var app_path_webroot = '<?php echo APP_PATH_WEBROOT; ?>';
                    var pid = '<?php echo $project_id; ?>';
                    var group_id = $('[name="send_rx_dag_id"]').val();
                    $('button[name="submit-btn-deleteform"]')[0].onclick = null;
                    $('button[name="submit-btn-deleteform"]').on('click', function(){
                        simpleDialog('<div style=\'margin:10px 0;font-size:13px;\'>Are you sure you wish to PERMANENTLY delete this record\'s data on THIS INSTRUMENT ONLY?<div style="margin-top:15px;color:#C00000;font-weight:bold;">This process is permanent and CANNOT BE REVERSED.</div> </div>','DELETE ALL DATA ON THIS FORM FOR RECORD "2"?',null,600,null,'Cancel',function(){ dataEntrySubmit( document.getElementsByName('submit-btn-deleteform')[0] );return false; },'Delete data for THIS FORM only');
                        $.get(app_path_webroot+"DataAccessGroups/data_access_groups_ajax.php?pid="+pid+"&action=delete&item="+group_id,{ }, function(data){
                            if(data){
                                return;
                            }
                        });
                        return false;
                    });
                });
            </script>
            <?php
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

        // Getting the list of instruments of the given event.
        $fields = array();
        foreach (array_keys($Proj->forms) as $form_name) {
            $fields[$form_name] = $form_name . '_complete';
        }

        // Removing current instrument from the list.
        unset($fields[$instrument]);

        // Calculating whether the event is complete or not.
        $event_is_complete = true;
        $data = REDCap::getData($project_id, 'array', $record, $fields, $event_id);
        foreach ($fields as $field) {
            if ($data[$record][$event_id][$field] != 2) {
                $event_is_complete = false;
                break;
            }
        }

        $sql = '
            SELECT value FROM redcap_data
            WHERE
                field_name = "send_rx_pdf_is_updated" AND
                project_id = ' . db_escape($project_id) . ' AND
                event_id = ' . db_escape($event_id) . ' AND
                record = ' . db_escape($record) . '
            LIMIT 1';

        $q = db_query($sql);

        $pdf_is_updated = false;
        if (db_num_rows($q)) {
            $result = db_fetch_assoc($q);
            $pdf_is_updated = $result['value'];
        }

        // Checking if PDF needs to be generated.
        if (!$pdf_is_updated && $sender->getPrescriberData()) {
            /*
                Generate PDF.
            */
            $sender->generatePDFFile();
            send_rx_save_record_field($project_id, $event_id, $record, "send_rx_pdf_is_updated", '1', $repeat_instance);

            ?>
            <script type="text/javascript">
                /*
                    Success message on page load to confirm PDF generation.
                */
                $(document).ready(function() {
                    var app_path_images = '<?php echo APP_PATH_IMAGES ?>';
                    var successMsg = '<div class="darkgreen" style="margin:8px 0 5px;"><img src="' + app_path_images + 'tick.png"> A new prescription PDF preview has been created.';
                    $('#pdfExportDropdownDiv').parent().next().append(successMsg);
                });
            </script>
            <?php
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
                    $submit_buttons.attr('disabled', 'disabled');
                    $submit_buttons.attr('title', 'Your must complete all form steps before sending the prescription.');
                };

                // Enables submit buttons.
                var enableSubmit = function() {
                    $submit_buttons.attr('disabled', null);
                    $submit_buttons.attr('title', null);
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
