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

        // Admin booking: toggle guest fields.
        $('#sws_admin_include_guest').on('change', function () {
            $('.sws-admin-guest-fields').toggle(this.checked);
        });

        // Event image fields: WordPress media uploader.
        $('.sws-image-field').each(function () {
            var $field = $(this);
            var targetId = $field.data('target');
            var $input = $('#' + targetId);
            var $preview = $('#' + targetId + '_preview');
            var $removeBtn = $field.find('.sws-image-field__remove');
            var frame;

            $field.find('.sws-image-field__select').on('click', function (e) {
                e.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                frame = wp.media({
                    title: 'Select Image',
                    button: { text: 'Use this image' },
                    library: { type: 'image' },
                    multiple: false
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $input.val(attachment.id);
                    var url = (attachment.sizes && attachment.sizes.medium)
                        ? attachment.sizes.medium.url
                        : attachment.url;
                    $preview.html('<img src="' + url + '" alt="">');
                    $removeBtn.show();
                });

                frame.open();
            });

            $removeBtn.on('click', function (e) {
                e.preventDefault();
                $input.val('');
                $preview.empty();
                $(this).hide();
            });
        });
    });
})(jQuery);
