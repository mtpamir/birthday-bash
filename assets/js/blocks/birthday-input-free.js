// birthday-bash/assets/js/blocks/birthday-input-free.js

const { registerBlockType } = wp.blocks;
const { createElement } = wp.element;
const { __ } = wp.i18n;
const { SelectControl, TextControl, PanelBody } = wp.components;
const { useBlockProps, InspectorControls } = wp.blockEditor;

registerBlockType("birthday-bash/birthday-input-free", {
  title: __("Birthday Input Form (Free)", "birthday-bash"),
  icon: "cake",
  category: "widgets",
  description: __(
    "Allows users to enter their birthday (day and month) on the frontend.",
    "birthday-bash"
  ),
  keywords: [
    __("birthday", "birthday-bash"),
    __("form", "birthday-bash"),
    __("coupon", "birthday-bash"),
  ],

  /**
   * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
   */
  edit: function ({ attributes, setAttributes }) {
    const blockProps = useBlockProps();

    const monthOptions = [
      { label: __("Select Month", "birthday-bash"), value: "" },
      { label: __("January", "birthday-bash"), value: "1" },
      { label: __("February", "birthday-bash"), value: "2" },
      { label: __("March", "birthday-bash"), value: "3" },
      { label: __("April", "birthday-bash"), value: "4" },
      { label: __("May", "birthday-bash"), value: "5" },
      { label: __("June", "birthday-bash"), value: "6" },
      { label: __("July", "birthday-bash"), value: "7" },
      { label: __("August", "birthday-bash"), value: "8" },
      { label: __("September", "birthday-bash"), value: "9" },
      { label: __("October", "birthday-bash"), value: "10" },
      { label: __("November", "birthday-bash"), value: "11" },
      { label: __("December", "birthday-bash"), value: "12" },
    ];

    return createElement(
      "div",
      blockProps,
      createElement(
        "div",
        { className: "birthday-bash-gutenberg-block-editor-preview" },
        createElement(
          "h3",
          {},
          __("Birthday Input Form Preview", "birthday-bash")
        ),
        createElement(
          "p",
          {},
          __(
            "This block allows users to enter their birthday day and month.",
            "birthday-bash"
          )
        ),
        createElement(
          "p",
          {},
          __(
            "The mandatory status is controlled by the plugin settings.",
            "birthday-bash"
          )
        ),
        createElement(
          "p",
          {},
          __("Day: ", "birthday-bash"),
          createElement("input", { type: "number", placeholder: "DD" })
        ),
        createElement(
          "p",
          {},
          __("Month: ", "birthday-bash"),
          createElement(
            "select",
            {},
            monthOptions.map((option) =>
              createElement("option", { value: option.value }, option.label)
            )
          )
        ),
        createElement(
          "button",
          { className: "button" },
          __("Save Birthday", "birthday-bash")
        )
      )
    );
  },

  /**
   * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#save
   */
  save: function () {
    return createElement(
      "div",
      useBlockProps.save(),
      // The actual form HTML is rendered server-side by PHP's render_callback
      // So we return null or an empty div here, as the content is dynamic.
      null // Render callback will handle the dynamic content.
    );
  },
});
