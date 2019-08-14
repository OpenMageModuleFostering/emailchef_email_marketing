/**
 * EMailChef admin javascript
 *
 * Javascript to be run on admin pages
 */

/**
 * Setup Ajax in system config for loading groups
 */
function initListObserver(url) {
    $('emailchef_newsletter_emailchef_list').observe('change', function (event) {
        var currentGroupSelected = $('emailchef_newsletter_emailchef_default_group').value;
        var updater = new Ajax.Updater('emailchef_newsletter_emailchef_default_group', url, {
            method: 'get',
            onSuccess: function () {
                $('emailchef_newsletter_emailchef_default_group').value = currentGroupSelected;
            },
            parameters: {list: $('emailchef_newsletter_emailchef_list').value}
        });
    }); // End of emailchef list change
}

function initSelfTestObserver(url) {
    $('emailchef_selftest_button').observe('click', function (event) {
        var request = new Ajax.Request(url, {
            method: 'get',
            onFailure: function(transport) {$('messages').update('<ul class="messages"><li class="error-msg"><ul><li>Error checking connection details</li></ul></li></ul>')},
            onComplete: function(transport) {
                $('messages').update(transport.responseText);
                Element.hide('loading-mask');
            },
            parameters: {
                username_ws: $('emailchef_newsletter_emailchef_username_ws').value,
                password_ws: $('emailchef_newsletter_emailchef_password_ws').value
            }
        });
    }); // End of emailchef selftest button click change
}
