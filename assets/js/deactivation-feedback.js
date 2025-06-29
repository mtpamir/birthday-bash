// birthday-bash/assets/js/deactivation-feedback.js

jQuery(document).ready(function ($) {
  const modal = $("#birthday-bash-deactivation-modal");
  const form = $("#birthday-bash-deactivation-form");
  const skipButton = $(".birthday-bash-skip-feedback");
  const cancelButton = $(".birthday-bash-cancel-deactivation");
  const deactivateLink = $('tr[data-slug="birthday-bash"] .deactivate a');
  const reasonSelect = $("#deactivation_reason"); // Changed from radio buttons
  const reasonOtherText = $('textarea[name="reason_other_text"]');
  const consentDataCollectionCheckbox = $("#consent_data_collection");
  const adminContactConsentDiv = $(".birthday-bash-admin-contact-consent");

  let originalDeactivateUrl = "";

  // Show the "Other" text field when "Other" option is selected from dropdown
  reasonSelect.on("change", function () {
    // Changed event from 'click' on radios to 'change' on select
    if ($(this).val() === "other") {
      reasonOtherText.slideDown();
    } else {
      reasonOtherText.slideUp();
      reasonOtherText.val(""); // Clear text when "other" is not selected
    }
  });

  // Toggle visibility of "contact all admins" consent based on main consent
  consentDataCollectionCheckbox.on("change", function () {
    if ($(this).is(":checked")) {
      adminContactConsentDiv.slideDown();
    } else {
      adminContactConsentDiv.slideUp();
      adminContactConsentDiv
        .find('input[name="consent_contact_all_admins"]')
        .prop("checked", false); // Uncheck if hidden
    }
  });

  // Capture the original deactivation URL and prevent immediate deactivation
  deactivateLink.on("click", function (e) {
    e.preventDefault(); // Prevent default deactivation
    originalDeactivateUrl = $(this).attr("href"); // Store the URL
    modal.fadeIn(); // Show the modal
  });

  // Handle form submission
  form.on("submit", function (e) {
    e.preventDefault();

    const submitButton = $(this).find(".birthday-bash-submit-feedback");
    submitButton.prop("disabled", true).text("Sending Feedback...");

    const reason = reasonSelect.val(); // Get value from select
    const feedbackMessage = $("#feedback_message").val();
    // Removed: const siteType = $('#site_type').val(); // Site type option removed
    const userEmail = $("#user_email").val();
    const additionalContactEmail = $("#additional_contact_email").val();
    const consentDataCollection = consentDataCollectionCheckbox.is(":checked");
    const consentContactAllAdmins = adminContactConsentDiv
      .find('input[name="consent_contact_all_admins"]')
      .is(":checked");

    if (!consentDataCollection) {
      alert("Please consent to sharing feedback before submitting.");
      submitButton.prop("disabled", false).text("Submit & Deactivate");
      return;
    }

    // Basic validation for dropdown selection
    if (reason === "") {
      alert("Please select a reason for deactivating.");
      submitButton.prop("disabled", false).text("Submit & Deactivate");
      return;
    }

    const data = {
      action: "birthday_bash_deactivation_feedback",
      nonce: birthday_bash_deactivation_vars.nonce,
      plugin_basename: birthday_bash_deactivation_vars.plugin_basename,
      reason: reason,
      reason_other_text: reasonOtherText.val(),
      feedback_message: feedbackMessage,
      // Removed: site_type: siteType, // Site type removed from data
      user_email: userEmail,
      additional_contact_email: additionalContactEmail,
      consent_data_collection: consentDataCollection ? 1 : 0,
      consent_contact_all_admins: consentContactAllAdmins ? 1 : 0,
    };

    $.post(birthday_bash_deactivation_vars.ajax_url, data)
      .done(function (response) {
        // Regardless of success/failure of sending feedback, proceed with deactivation
        window.location.href = originalDeactivateUrl;
      })
      .fail(function () {
        // If AJAX fails, still proceed with deactivation
        window.location.href = originalDeactivateUrl;
      });
  });

  // Handle skip button click
  skipButton.on("click", function () {
    window.location.href = originalDeactivateUrl; // Proceed with deactivation
  });

  // Handle cancel button click
  cancelButton.on("click", function (e) {
    e.preventDefault();
    modal.fadeOut(); // Hide the modal
    originalDeactivateUrl = ""; // Clear the URL
  });
});
