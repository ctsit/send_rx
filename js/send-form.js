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
    var $submitButtons = $('button[id^="submit-btn-"]');
    $submitButtons.addClass('btn-success');

    if (!settings.prescriberIsSet) {
        // Disable submit buttons if prescriber is not set.
        $submitButtons.prop('disabled', true);
        $submitButtons.prop('title', 'Your must setup a prescriber before sending your prescription.');
    }
});
