(function ($) {
    Drupal.behaviors.blueoiPrivacy = {
        attach: function(context, settings) {

            // Show the cookie notice if the cookie doesn't exist.
            var cookies = document.cookie;
            if (cookies.indexOf('blueoi-privacy-cookie-policy') === -1) {
                $('#blueoi-privacy-sticky-footer', context).show();
            }

            // Set the cookie so the notice isn't shown again.
            document.cookie = "blueoi-privacy-cookie-policy=1";

            // Handle the close event for the cookie notice.
            var $close = $('.blueoi-privacy-close', context);
            if ($close.length > 0) {
                $close.on('click', function() {
                   $('#blueoi-privacy-sticky-footer', context).hide();
                });
            }
        }
    };
})(jQuery);
