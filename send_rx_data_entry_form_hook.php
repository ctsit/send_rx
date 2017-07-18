<?php
    return function($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
        require_once 'send_rx_functions.php';
        require_once 'RxSender.php';

        /*
            New PDF is generated based on is_pdf_generated flag.
            Reset flag once PDF is generated to avoid duplicate generation of the same PDF.
        */
        $last_instrument = 'send_rx_review';
        if ($instrument != $last_instrument) {
            return;
        }

        if (!$sender = RxSender::getSender($project_id, $event_id, $record)) {
            return;
        }

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

        $data = $sender->getPatientData();
        $field_name = 'send_rx_is_pdf_updated';
        $is_pdf_updated = $data[$field_name];

        if (!$is_pdf_updated) {
            /*
                Generate PDF.
            */
            //$sender->generatePDFFile();

            $is_pdf_generated = true;
            send_rx_save_record_field($project_id, $event_id, $record, $field_name, $is_pdf_generated, $repeat_instance);

            ?>
            <script type="text/javascript">
                /*
                    Success message on page load to confirm PDF generation.
                */
                $(document).ready(function() {
                    var app_path_images = '<?php echo APP_PATH_IMAGES ?>';
                    var successMsg = '<div class="darkgreen" style="margin:8px 0 5px;"><img src="' + app_path_images + 'tick.png"> New PDF has been generated</div>';
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
                var helpTxt = '<div style="color: #666;font-size: 11px;"><span></span></div>';
                $(helpTxt).insertBefore($('button[name="submit-btn-cancel"]')[0]);

                // Removing operation buttons on PDF file.
                if ($('#send_rx_pdf-linknew')) {
                    $('#send_rx_pdf-linknew').remove();
                }

                $('#submit-btn-saverecord').html('Send & Exit Form');
                $('#submit-btn-savecontinue').html('Send & Stay');

                // Showing logs table.
                $('#send_rx_logs-tr .data').html('<?php echo $table; ?>');
            });
        </script>
        <?php
    };
?>
