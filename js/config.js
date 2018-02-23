$(document).ready(function() {
    var $modal = $('#external-modules-configure-modal');
    var subject = 'input[type="radio"][name="send-rx-type"]';

    function sendRxBranchingLogic() {
        var op = $(subject + ':checked').val() === 'site' ? 'removeClass' : 'addClass';
        $modal[op]('send-rx');
    }

    ExternalModules.Settings.prototype.configureSettingsOld = ExternalModules.Settings.prototype.configureSettings;
    ExternalModules.Settings.prototype.configureSettings = function() {
        ExternalModules.Settings.prototype.configureSettingsOld();

        if ($modal.data('module') !== sendRx.modulePrefix) {
            return;
        }

        $(subject).change(function() {
            sendRxBranchingLogic();
        });

        sendRxBranchingLogic();
    }
});
