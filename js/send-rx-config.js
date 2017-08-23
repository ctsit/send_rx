$(document).ready(function() {
    var send_rx_modal = false;
    var subject = 'input[type="radio"][name="send-rx-type"]';

    function sendRxBranchingLogic(op) {
        if ($(subject).length === 0) {
            return false;
        }

        fields = [
            'send-rx-pdf-template-name',
            'send-rx-pdf-template-variable',
            'send-rx-pdf-template-variable-key',
            'send-rx-pdf-template-variable-value',
            'send-rx-message',
            'send-rx-message-subject',
            'send-rx-message-body'
        ];

        var op = $(subject + ':checked').val() === 'site' ? 'show' : 'hide';
        $('[field="' + fields.join('"],[field="') + '"]')[op]();
    }

    $('#external-modules-configure-modal').on('shown.bs.modal', function (e) {
        $(subject).change(function () {
            sendRxBranchingLogic();
        });

        sendRxBranchingLogic();
    });
});
