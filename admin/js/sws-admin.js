/**
 * SWS Members Club — Admin JavaScript.
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        // Auto-generate slug from tier name.
        $('#tier_name').on('input', function () {
            var slug = $(this).val()
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '');
            $('#tier_slug').val(slug);
        });
    });
})(jQuery);
