/**
 * @file
 * Javascript library for BlueOI Commerce Admin Order.
 */

(function ($, Drupal, drupalSettings) {
    Drupal.blueoi_commerce_admin_order = {};

    var click = false;
    var edit = false;
    Drupal.behaviors.blueoiCommerceAdminOrder = {

        attach: function (context) {

            // Prevent the backspace key from triggering
            // the browsers back button functionality.
            var rx = /INPUT|SELECT|TEXTAREA/i;
            $(document).bind("keydown keypress", function(e){
              if( e.which == 8 ){ // 8 == backspace
                if(!rx.test(e.target.tagName) || e.target.disabled || e.target.readOnly ){
                  e.preventDefault();
                }
              }
            });

            // Set the edit flag when a form element
            // has an error after reload.
            if ($(".commerce-order-form .error").length > 0) {
                edit = true;
            }

            // If they leave an input field, assume they changed it.
            $(".commerce-order-form :input").each(function () {
                $(this).blur(function () {
                    edit = true;
                });
            });

            // Let all form submit buttons through.
            $(".commerce-order-form input[type='submit']").each(function () {
                $(this).addClass('blueoi-commerce-admin-order-processed');
                $(this).click(function () {
                    click = true;
                });
            });

            // Catch all links and buttons EXCEPT for "#" links.
            $("a, button, input[type='submit']:not(.blueoi-commerce-admin-order-processed)")
                .each(function () {
                    $(this).click(function () {
                        // Return when a "#" link is clicked so as to skip the
                        // window.onbeforeunload function.
                        if (edit && $(this).attr("href") != "#") {
                            return 0;
                        }
                    });
                });

            // Handle backbutton, exit etc.
            window.onbeforeunload = function () {
                if (edit && !click) {
                    click = false;
                    return (Drupal.t("Unsaved order updates may be lost. Please confirm or save your changes."));
                }
            }
        }
    };

})(jQuery, Drupal, drupalSettings);


