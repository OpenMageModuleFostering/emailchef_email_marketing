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
              setTimeout(function(){
                $('emailchef_newsletter_emailchef_default_group').value = currentGroupSelected;
              },10);
            },
            parameters: {list: $('emailchef_newsletter_emailchef_list').value}
        });
    }); // End of emailchef list change
}

function initSelfTestObserver(url,url2) {
    $('emailchef_selftest_button').observe('click', function (event) {
        var request = new Ajax.Request(url, {
            method: 'get',
            onFailure: function(transport) {$('messages').update('<ul class="messages"><li class="error-msg"><ul><li>Error checking connection details</li></ul></li></ul>')},
            onComplete: function(transport) {
                $('messages').update(transport.responseText);
                Element.hide('loading-mask');

                // Update lists
                var currentListSelected = $('emailchef_newsletter_emailchef_list').value;
                var currentGroupSelected = $('emailchef_newsletter_emailchef_default_group').value;
                var updater = new Ajax.Updater('emailchef_newsletter_emailchef_list', url2, {
                    method: 'get',
                    onSuccess: function () {
                        setTimeout(function(){
                          $('emailchef_newsletter_emailchef_list').value = currentListSelected;
                          $('emailchef_newsletter_emailchef_default_group').value = currentGroupSelected;
                        },10);
                    },
                    parameters: {}
                });
            },
            parameters: {
                username_ws: $('emailchef_newsletter_emailchef_username_ws').value,
                password_ws: $('emailchef_newsletter_emailchef_password_ws').value
            }
        });
    }); // End of emailchef selftest button click change
}

function initCreateFieldsObserver(url) {
    $('emailchef_createfields_button').observe('click', function (event) {
      window.location.href=url;
    });
}
