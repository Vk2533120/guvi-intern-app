/**
 * register.js — Handles the registration form via jQuery AJAX.
 *
 * Sends a POST to php/register.php with form data.
 * Displays Bootstrap alerts for success/error feedback.
 * On success, redirects to login.html after a short delay.
 */

$(document).ready(function () {

    /**
     * Show a Bootstrap alert inside #alert-container.
     * @param {string} type  - "success", "danger", "warning", etc.
     * @param {string} msg   - Message text to display.
     */
    function showAlert(type, msg) {
        var $alert = $('<div class="alert alert-dismissible fade show" role="alert"></div>')
            .addClass('alert-' + type)
            .text(msg)
            .append('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
        $('#alert-container').empty().append($alert);
    }

    // -----------------------------------------------------------------------
    // Form submission via AJAX (no native form post)
    // -----------------------------------------------------------------------
    $('#register-form').on('submit', function (e) {
        e.preventDefault(); // prevent default HTML form submission

        var name            = $.trim($('#name').val());
        var email           = $.trim($('#email').val());
        var username        = $.trim($('#username').val());
        var password        = $('#password').val();
        var confirmPassword = $('#confirm-password').val();

        // Basic client-side checks (server validates too)
        if (!name || !email || !username || !password || !confirmPassword) {
            showAlert('warning', 'Please fill in all fields.');
            return;
        }
        if (password.length < 6) {
            showAlert('warning', 'Password must be at least 6 characters.');
            return;
        }
        if (password !== confirmPassword) {
            showAlert('warning', 'Passwords do not match.');
            return;
        }

        // Disable button to prevent double-submit
        $('#btn-register').prop('disabled', true).text('Registering...');

        $.ajax({
            url:  'php/register.php',
            type: 'POST',
            data: {
                name:             name,
                email:            email,
                username:         username,
                password:         password,
                confirm_password: confirmPassword
            },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showAlert('success', res.message + ' Redirecting to login...');
                    setTimeout(function () {
                        window.location.href = 'login.html';
                    }, 2000);
                } else {
                    showAlert('danger', res.message);
                    $('#btn-register').prop('disabled', false).text('Register');
                }
            },
            error: function (xhr) {
                var msg = 'Something went wrong.';
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.message) msg = res.message;
                } catch (ignore) {}
                showAlert('danger', msg);
                $('#btn-register').prop('disabled', false).text('Register');
            }
        });
    });
});
