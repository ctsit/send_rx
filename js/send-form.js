document.addEventListener('DOMContentLoaded', function() {
    var settings = sendRx.sendForm;
    var helpTxt = '<div style="color: #666;font-size: 11px;"><b>ATTENTION:</b> The prescription will be sent by submitting this form.</div>';

    $(helpTxt).insertBefore($('button[name="submit-btn-cancel"]')[0]);

    // Removing operation buttons on PDF file.
    if ($('#send_rx_pdf-linknew')) {
        $('#send_rx_pdf-linknew').remove();
    }

    $('#submit-btn-saverecord').html('Send & Exit Form');
    $('#submit-btn-savecontinue').html('Send & Stay');
    $('#submit-btn-savenextform').html('Send & Go to Next Form');
    $('#submit-btn-saveexitrecord').html('Send & Exit Record');
    $('#submit-btn-savenextrecord').html('Send & Go To Next Record');
    $('#submit-btn-savecompresp').html('Send & Mark Survey as Complete');

    // Showing logs table.
    $('#send_rx_logs-tr .data').html(settings.table);

    // Changing color of submit buttons.
    var $submitButtons = $('button[id^="submit-btn-"]');
    $submitButtons.addClass('btn-success');

    // Removing save callbacks (to be moved to confirmation modal).
    var $saveElements = $('[id^="submit-btn-save"]');
    $saveElements.removeAttr('onclick');

    if (settings.currentUserIsPrescriber) {
        // Adding confirmation modal.
        $saveElements.click(function() {
            var currSubmitElement = this;

            $('#send-rx-confirmation-modal').dialog({
                resizable: false,
                height: 'auto',
                width: 400,
                modal: true,
                buttons: {
                    'Send Rx': function() {
                        dataEntrySubmit(currSubmitElement);
                        $(this).dialog('close');
                    },
                    Cancel: function() {
                        $(this).dialog('close');
                    }
                }
            });

            return false;
        });
    }
    else {
        // Disable submit buttons if prescriber is not set.
        $submitButtons.prop('disabled', true);
        $submitButtons.prop('title', 'Only the prescriber can send the prescription.');
    }

    $('#stayOnPageReminderDialog').on('dialogopen', function(event, ui) {
        // Overriding dialog message.
        $(this).html('Are you sure you wish to leave this page?');

        var buttons = $(this).dialog('option', 'buttons');
        $.each(buttons, function(i, button) {
            if (button.text === 'Save changes and leave') {
                // Since "saving" makes no sense on this page, let's remove
                // "Save changes and leave" button.
                buttons.splice(i, 1);
                return false;
            }
        });

        $(this).dialog('option', 'buttons', buttons);
    });
});
