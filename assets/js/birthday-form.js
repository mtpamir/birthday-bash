// birthday-bash/assets/js/frontend/birthday-form.js

jQuery(document).ready(function ($) {
  $(".birthday-bash-block-birthday-form").on("submit", function (e) {
    e.preventDefault();

    const form = $(this);
    const dayInput = form.find("#birthday_bash_block_day");
    const monthSelect = form.find("#birthday_bash_block_month");
    const messageDiv = form.find(".birthday-bash-block-message");
    const submitButton = form.find('button[type="submit"]');

    messageDiv.empty().removeClass("success error");
    submitButton.prop("disabled", true);

    const day = dayInput.val();
    const month = monthSelect.val();
    const nonce = form.find("#birthday_bash_block_nonce_field").val();

    // Basic client-side validation
    if (
      birthday_bash_free_block_vars.is_mandatory &&
      (day === "" || month === "")
    ) {
      messageDiv
        .text(birthday_bash_free_block_vars.messages.error)
        .addClass("error");
      submitButton.prop("disabled", false);
      return;
    }
    if (day !== "" && (parseInt(day) < 1 || parseInt(day) > 31)) {
      messageDiv
        .text(birthday_bash_free_block_vars.messages.error)
        .addClass("error");
      submitButton.prop("disabled", false);
      return;
    }
    if (month !== "" && (parseInt(month) < 1 || parseInt(month) > 12)) {
      messageDiv
        .text(birthday_bash_free_block_vars.messages.error)
        .addClass("error");
      submitButton.prop("disabled", false);
      return;
    }

    $.ajax({
      url: birthday_bash_free_block_vars.ajax_url,
      type: "POST",
      data: {
        action: "birthday_bash_save_birthday_block_free",
        nonce: nonce,
        day: day,
        month: month,
      },
      success: function (response) {
        if (response.success) {
          messageDiv.text(response.data.message).addClass("success");
        } else {
          messageDiv.text(response.data.message).addClass("error");
        }
      },
      error: function () {
        messageDiv
          .text(birthday_bash_free_block_vars.messages.error)
          .addClass("error");
      },
      complete: function () {
        submitButton.prop("disabled", false);
      },
    });
  });
});
