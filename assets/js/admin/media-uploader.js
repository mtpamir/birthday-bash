// birthday-bash/assets/js/admin/media-uploader.js

jQuery(document).ready(function($) {
    var mediaUploader;

    // When the "Select Image" button is clicked
    $('.birthday-bash-select-image-button').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $hiddenInput = $button.siblings('input#birthday_bash_email_logo');
        var $imagePreview = $button.siblings('img#birthday_bash_email_logo_preview');
        var $removeButton = $button.siblings('.birthday-bash-remove-image-button');

        // If the media uploader already exists, reopen it
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // Create the WordPress media uploader frame
        mediaUploader = wp.media({
            title: 'Select Email Logo', // Title for the media frame
            button: {
                text: 'Use this image' // Text for the button in the media frame
            },
            multiple: false // Only allow one image to be selected
        });

        // When an image is selected in the media frame
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();

            // Set the selected image URL to the hidden input field
            $hiddenInput.val(attachment.url);

            // Update the image preview
            $imagePreview.attr('src', attachment.url).show();

            // Show the "Remove Image" button
            $removeButton.show();

            // Change the "Select Image" button text to "Change Image"
            $button.text('Change Image');
        });

        // Open the media frame
        mediaUploader.open();
    });

    // When the "Remove Image" button is clicked
    $('.birthday-bash-remove-image-button').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $hiddenInput = $button.siblings('input#birthday_bash_email_logo');
        var $imagePreview = $button.siblings('img#birthday_bash_email_logo_preview');
        var $selectButton = $button.siblings('.birthday-bash-select-image-button');

        // Clear the hidden input field
        $hiddenInput.val('');

        // Hide and clear the image preview
        $imagePreview.attr('src', '').hide();

        // Hide the "Remove Image" button
        $button.hide();

        // Change the "Select Image" button text back to "Select Image"
        $selectButton.text('Select Image');
    });
});