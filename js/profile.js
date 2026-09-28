/**
 * profile.js — Profile page logic via jQuery AJAX.
 *
 * On load:
 *   1. Reads the session token from localStorage.
 *   2. Sends GET to php/profile.php with X-Session-Token header.
 *   3. Populates the form with MySQL (read-only) + MongoDB (editable) data.
 *
 * On update:
 *   Sends POST with the editable fields + token to php/profile.php.
 *
 * Logout:
 *   Sends POST to php/logout.php, clears localStorage, redirects to login.
 */

$(document).ready(function () {

    var token = localStorage.getItem('session_token');

    // No token → redirect to login immediately
    if (!token) {
        window.location.href = 'login.html';
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
    // Fetch profile data on page load
    // -----------------------------------------------------------------------
    $.ajax({
        url:  'php/profile.php',
        type: 'GET',
        headers: { 'X-Session-Token': token },
        dataType: 'json',
        success: function (res) {
            if (res.success) {
                // Populate read-only MySQL fields
                $('#name').val(res.user.name);
                $('#email').val(res.user.email);
                $('#username').val(res.user.username);
                $('#nav-username').text('Hi, ' + res.user.name);

                // Populate editable MongoDB fields
                $('#age').val(res.profile.age);
                $('#dob').val(res.profile.dob);
                $('#contact').val(res.profile.contact);
                $('#address').val(res.profile.address);

                // Show form, hide spinner
                $('#loading').addClass('d-none');
                $('#profile-form').removeClass('d-none');
            } else {
                handleAuthError(res.message);
            }
        },
        error: function (xhr) {
            if (xhr.status === 401) {
                handleAuthError('Session expired. Please log in again.');
            } else {
                $('#loading').addClass('d-none');
                showAlert('danger', 'Failed to load profile.');
            }
        }
    });

    // -----------------------------------------------------------------------
    // Update profile (AJAX POST)
    // -----------------------------------------------------------------------
    $('#profile-form').on('submit', function (e) {
        e.preventDefault();

        $('#btn-update').prop('disabled', true).text('Saving...');

        $.ajax({
            url:  'php/profile.php',
            type: 'POST',
            headers: { 'X-Session-Token': token },
            data: {
                age:     $.trim($('#age').val()),
                dob:     $.trim($('#dob').val()),
                contact: $.trim($('#contact').val()),
                address: $.trim($('#address').val())
            },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showAlert('success', res.message);
                } else {
                    showAlert('danger', res.message);
                }
                $('#btn-update').prop('disabled', false).text('Update Profile');
            },
            error: function (xhr) {
                var msg = 'Update failed.';
                if (xhr.status === 401) {
                    handleAuthError('Session expired. Please log in again.');
                    return;
                }
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.message) msg = res.message;
                } catch (ignore) {}
                showAlert('danger', msg);
                $('#btn-update').prop('disabled', false).text('Update Profile');
            }
        });
    });

    // -----------------------------------------------------------------------
    // Logout
    // -----------------------------------------------------------------------
    $('#btn-logout').on('click', function (e) {
        e.preventDefault();

        $.ajax({
            url:  'php/logout.php',
            type: 'POST',
            headers: { 'X-Session-Token': token },
            dataType: 'json',
            complete: function () {
                // Always clear local storage and redirect, even if the call fails
                localStorage.removeItem('session_token');
                localStorage.removeItem('user_name');
                window.location.href = 'login.html';
            }
        });
    });

    // -----------------------------------------------------------------------
    // Helper: handle authentication failures
    // -----------------------------------------------------------------------
    function handleAuthError(msg) {
        localStorage.removeItem('session_token');
        localStorage.removeItem('user_name');
        alert(msg);
        window.location.href = 'login.html';
    }
});
