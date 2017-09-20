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

    // Showing logs table.
    $('#send_rx_logs-tr .data').html(settings.table);

    // Changing color of submit buttons.
    var $submit_buttons = $('button[id^="submit-btn-"]');
    $submit_buttons.addClass('btn-success');

    // Disables submit buttons.
    var disableSubmit = function() {
        $submit_buttons.prop('disabled', true);
        $submit_buttons.prop('title', 'Your must complete all form steps before sending the prescription.');
    };

    // Enables submit buttons.
    var enableSubmit = function() {
        $submit_buttons.removeProp('disabled');
        $submit_buttons.removeProp('title');
    };

    if (settings.eventIsComplete) {
        var $complete = $('select[name="' + settings.instrument + '_complete"]');
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
