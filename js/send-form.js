document.addEventListener('DOMContentLoaded', function() {
    var settings = sendRx.sendForm;

    // Removing operation buttons on PDF file.
    if ($('#send_rx_pdf-linknew')) {
        $('#send_rx_pdf-linknew').remove();
    }

    // Showing logs table.
    $('#send_rx_logs-tr .data').html(settings.table);

    // Removing "Form Status" delimiter.
    $('#' + settings.instrument + '_complete-sh-tr').remove();

    // Hiding "Complete?" field and setting it as complete only if a
    // prescription has been succesfully sent already.
    $('#' + settings.instrument + '_complete-tr').replaceWith(settings.completeReplacement);

    // Adding class to submit buttons container for styling purposes.
    $('#__SUBMITBUTTONS__-div').addClass('send-rx-btns-container');

    // Removing all submit buttons.
    $('button[id^="submit-btn-"]').remove();
    $('.send-rx-btns-container .btn-group').remove();

    // Adding Send Rx  button.
    $('.send-rx-btns-container').append(settings.sendBtn);
    $('#formSaveTip .btn-group').html(settings.sendBtn);

    // Updating Cancel button text.
    $('button[name="submit-btn-cancel"]').text('-- Leave & Continue later --');

    var $sendBtns = $('button[name="send-rx-btn"]');
    if (settings.currentUserIsPrescriber && settings.pdfIsSet) {
        $sendBtns.click(function() {
            $('#send-rx-confirmation-modal').dialog({
                resizable: false,
                height: 'auto',
                width: 400,
                modal: true,
                buttons: {
                    'Send Rx': function() {
                        // Saving form and staying on the same page.
                        dataEntrySubmit('submit-btn-savecontinue');
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
        // Disable submit buttons if prescriber is not set or if PDF is not set.
        $sendBtns.prop('disabled', true);
        var helper = settings.currentUserIsPrescriber ? 'Prescription file is not set.' : 'Only the prescriber can send the prescription.';
        $sendBtns.prop('title', helper);
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
