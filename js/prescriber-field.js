document.addEventListener('DOMContentLoaded', function() {
    var settings = sendRx.prescriberField;
    var $select = $('select[name="send_rx_prescriber_id"]');
    var $row = $('#send_rx_prescriber_id-tr');

    $select.hide().find('option[value="' + settings.username + '"]').prop('selected', true);
    $row.css('opacity', '0.6').find('.data').append(settings.fullname);
});
