$(document).ready(function() {
    var $modal = $('#external-modules-configure-modal');
    var subject = 'input[type="radio"][name="send-rx-type"]';

    function sendRxBranchingLogic() {
        var op = $(subject + ':checked').val() === 'site' ? 'removeClass' : 'addClass';
        $modal[op]('send-rx');
    }

    $modal.on('show.bs.modal', function() {
        // Making sure we are overriding this modules's modal only.
        if ($modal.data('module') !== sendRx.modulePrefix) {
            return;
        }

        if (typeof ExternalModules.Settings.prototype.configureSettingsOld === 'undefined') {
            ExternalModules.Settings.prototype.configureSettingsOld = ExternalModules.Settings.prototype.configureSettings;
        }

        ExternalModules.Settings.prototype.configureSettings = function() {
            ExternalModules.Settings.prototype.configureSettingsOld();

            if ($modal.data('module') !== sendRx.modulePrefix) {
                return;
            }

            $(subject).change(function() {
                sendRxBranchingLogic();
            });

            sendRxBranchingLogic();
        };
    });
});
