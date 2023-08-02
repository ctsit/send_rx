/**
 * Branching logic according to user setup mode: existing or new.
 */
sendRx.doStaffBranching = function(value) {
    var $newUserFields = $('input[name^="send_rx_new_user_"][type="text"]').parent().parent().parent();
    var $existingUserFields = $('select[name="send_rx_user_id"]').parent().parent().parent().parent();

    if (value == 'new') {
        $newUserFields.show();
        $existingUserFields.hide();
    }
    else if (value == 'existing') {
        $existingUserFields.show();
        $newUserFields.hide();
    }
    else {
        $existingUserFields.hide();
        $newUserFields.hide();
    }
};

/**
 * Refreshes "edit user profile" link according to the current selected user.
 */
sendRx.updateEditUserProfileLink = function(username = '') {
    var $target = $('#edit-user-profile');

    if (!username) {
        $target.html('');
        return;
    }

    $.get(sendRx.getUserProfileInfoUrl + '&username=' + username, function(data) {
        if (!data.success) {
            $target.html('');
            return;
        }

        data = data.data;

        var ret = [];
        for (let d in data) {
            ret.push(encodeURIComponent(d) + '=' + encodeURIComponent(data[d]));
        }

        var url = app_path_webroot + 'DataEntry/index.php?' + ret.join('&');
        $('#edit-user-profile').html('<a class="smalllink" target="_blank" href="' + url + '">edit user profile</a>');
    }, 'json');
};

$(document).ready(function() {
    // Enforce username field validation.
    $('#valregex_divs').append(sendRx.usernameValidateRegexElement);
    $('input[name="send_rx_new_user_id"]').blur(function() {
        redcap_validate(this, '', '', 'hard', 'username', 1);
    });

    $('tr[sq_id^="send_rx_new_user_"]').each(function() {
        // Remove update history link, which does not work for fake fields.
        $(this).find('label tr > td:last').remove();
    });

    // Do initial branching logic.
    sendRx.doStaffBranching($('[name="send_rx_new_user_opt___radio"]:checked').val());

    $('[name="send_rx_new_user_opt___radio"]').change(function() {
        // Run branching logic when add user option is changed.
        sendRx.doStaffBranching(this.value);
    });

    // Adding container for user profile edit link.
    $('tr[sq_id="send_rx_user_id"] td:last').append('<div id="edit-user-profile" class="resetLinkParent"></div>');

    var $userIdField = $('select[name="send_rx_user_id"]');
    var username = $userIdField.val();

    if (username) {
        // If a profile exists, set "Select an existing user" option as
        // default.
        $('[name="send_rx_new_user_opt___radio"][value="existing"]').click();

        // If a profile exists, create "edit user profile" link.
        sendRx.updateEditUserProfileLink(username);
    }

    $userIdField.change(function() {
        // Update user profile link when user field is changed.
        sendRx.updateEditUserProfileLink($(this).val());
    });
});
