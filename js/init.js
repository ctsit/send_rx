if (typeof sendRx === 'undefined') {
    var sendRx = {};
}

$(function() {
    if (typeof sendRx.statusMessage !== 'undefined' && sendRx.statusMessage !== '') {
        // Looking for the best spot for the status message.
        var $header = $('#dataEntryTopOptions > div:last-child');

        if ($header.length === 0) {
            $('#subheader').after(sendRx.statusMessage);
        }
        else {
            $header.append(sendRx.statusMessage);
        }
    }
});
