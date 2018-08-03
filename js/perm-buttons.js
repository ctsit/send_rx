$(function() {
    var settings = sendRx.permsRebuild;
    var url = app_path_webroot + 'UserRights/assign_user.php?pid=' + settings.pid;

    if (settings.msg !== '') {
        // Showing success message.
        $('#subheader').after(settings.msg);
    }

    // Placing form into markup.
    $('#record_status_table').after(settings.form);
    var $form = $('#send_rx_perms');

    $form.find('button').click(function() {
        if ($(this).hasClass('rebuild-perms-btn')) {
            // Rebuilding roles.
            var op = 'rebuild';
            var users = settings.rebuildRoles;
        }
        else if ($(this).hasClass('revoke-perms-btn')) {
            // Revoking roles.
            var op = 'revoke';
            var users = settings.revokeRoles;
        }
        else {
            return false;
        }

        $form.find('[name="operation"]').val(op);

        var count = Object.keys(users).length;

        if (!count) {
            $form.submit();
            return;
        }

        $.each(users, function(key, value) {
            $.post(settings.url, { username: key, role_id: value, notify_email_role: 0 }, function() {
                if (!--count) {
                    // Submit the form when the last role has been
                    // granted/revoked.
                    $form.submit();
                }
            });
        });
    });
});
