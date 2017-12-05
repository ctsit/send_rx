$(document).ready(function() {
    $modal = $('#external-modules-configure-modal');
    var subject = 'input[type="radio"][name="send-rx-type"]';

    function sendRxBranchingLogic() {
        var op = $(subject + ':checked').val() === 'site' ? 'removeClass' : 'addClass';
        $modal[op]('send-rx');
    }

    $modal.on('show.bs.modal', function() {
        if ($modal.data('module') === 'send_rx') {
            $modal.addClass('send-rx');
        }
    });

    $modal.on('shown.bs.modal', function() {
        if ($modal.data('module') === 'send_rx') {
            $(subject).change(function() {
                sendRxBranchingLogic();
            });

            sendRxBranchingLogic();
        }
    });
});
