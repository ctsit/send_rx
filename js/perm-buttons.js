$(function() {
    var settings = sendRx.permsRebuild;

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
            var roles = settings.rebuildRoles;
        }
        else if ($(this).hasClass('revoke-perms-btn')) {
            // Revoking roles.
            var op = 'revoke';
            var roles = settings.revokeRoles;
        }
        else {
            return false;
        }

        // Disabling the clicked button to avoid double clicks.
        $(this).prop('disabled', true);

        // Setting operation field to be submitted.
        $form.find('[name="operation"]').val(op);

        // Assigning/revoking roles.
        sendRx.permsRebuild.assignRoles(Object.keys(roles), roles, $form, {});
    });
});

sendRx.permsRebuild.assignRoles = function(usersQueue, roles, $form, errors) {
    if (!usersQueue.length) {
        // Submit the form when the last role is granted/revoked.
        if (!$.isEmptyObject(errors)) {
            // Log errors.
            $.post(sendRx.permsRebuild.errorLogEndpointUrl, { errors: errors });
            $form.find('[name="error_count"]').val(Object.keys(errors).length);
        }

        $form.submit();
        return;
    }

    // Getting next user from queue.
    var userId = usersQueue.shift();

    // Calling user role assign endpoint.
    $.post(sendRx.permsRebuild.url, { username: userId, role_id: roles[userId], notify_email_role: 0 }, function(data) {
        var $response = $('<div>' + data + '</div>');

        if ($response.find('.userSaveMsg.darkgreen').length === 0) {
            errors[userId] = roles[userId];
        }
    }).fail(function() {
        errors[userId] = roles[userId];
    }).always(function() {
        sendRx.permsRebuild.assignRoles(usersQueue, roles, $form, errors);
    });
}
