/**
 * login.js — Handles the login form via jQuery AJAX.
 *
 * Sends a POST to php/login.php with login (username/email) + password.
 * On success, stores the session token in localStorage and redirects
 * to profile.html. On error, shows a Bootstrap alert.
 */

$(document).ready(function () {

    // If user already has a valid token, skip to profile
    if (localStorage.getItem('session_token')) {
        window.location.href = 'profile.html';
        return;
    }

    function showAlert(type, msg) {
        var $alert = $('<div class="alert alert-dismissible fade show" role="alert"></div>')
            .addClass('alert-' + type)
            .text(msg)
            .append('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
        $('#alert-container').empty().append($alert);
    }

    // -----------------------------------------------------------------------
    // Form submission via AJAX
    // -----------------------------------------------------------------------
    $('#login-form').on('submit', function (e) {
        e.preventDefault();

        var login    = $.trim($('#login').val());
        var password = $('#password').val();

        if (!login || !password) {
            showAlert('warning', 'Please fill in all fields.');
            return;
        }

        $('#btn-login').prop('disabled', true).text('Logging in...');

        $.ajax({
            url:  'php/login.php',
            type: 'POST',
            data: {
                login:    login,
                password: password
            },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    // Store token and basic user info in localStorage
                    localStorage.setItem('session_token', res.token);
                    localStorage.setItem('user_name', res.user.name);

                    showAlert('success', res.message + ' Redirecting...');
                    setTimeout(function () {
                        window.location.href = 'profile.html';
                    }, 1000);
                } else {
                    showAlert('danger', res.message);
                    $('#btn-login').prop('disabled', false).text('Login');
                }
            },
            error: function (xhr) {
                var msg = 'Something went wrong.';
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.message) msg = res.message;
                } catch (ignore) {}
                showAlert('danger', msg);
                $('#btn-login').prop('disabled', false).text('Login');
            }
        });
    });
});
