/**
 * Checkout
 */
(function ($) {
  // Validation
  $(document.body).on("updated_checkout checkout_error init_checkout", (event) => {
    const inputs = ["card_name", "number", "expiry", "cvc"];
    $.each(inputs, function (index, name) {
      const $error = $(`[data-payway-error="${name}"]`);
      const $field = $(`[data-payway-field="${name}"]`);
      if ($error.length) {
        $field.addClass("payway__invalid");
      } else {
        $field.removeClass("payway__invalid");
      }
    });
  });

  // Test mode
  $(document.body).on("init_checkout", (event) => {
    if (location.search.includes("payway-test")) {
      setTimeout(() => {
        $("#billing_first_name").val("Kevin");
        $("#billing_last_name").val("Leary");
        $("#billing_country").val("US").trigger("change");
        $("#billing_address_1").val("1000 Main Street");
        $("#billing_city").val("New York");
        $("#billing_state").val("MA").trigger("change");
        $("#billing_postcode").val("10001");
        $("#billing_phone").val("2125044115");
        $("#billing_email").val(`info@${location.hostname}`);
        $("#card-name").val("Kevin Leary");
        $("#card-number").val("4242424242424242");
        const expiration = `${new Date().getFullYear() + 5}`.slice(-2);
        $("#card-expiry").val(`10/${expiration}`);
        $("#card-cvc").val("123");
      });
    }
  });

  // Inputs
  $(document).on("input", "#payway #card-number", (event) => {
    const $this = $(event.currentTarget);
    $this.val(function (index, value) {
      // Store cursor position
      let cursor = $this.get(0).selectionStart;

      // Filter characters and shorten CC (expanded for later use)
      const filterSpace = value.replace(/\s+/g, "");
      const filtered = filterSpace.replace(/[^0-9]/g, "");
      const cardNum = filtered.substr(0, 16);

      // Handle alternate segment length for American Express
      const partitions = cardNum.startsWith("34") || cardNum.startsWith("37") ? [4, 6, 5] : [4, 4, 4, 4];

      // Loop through the validated partition, pushing each segment into cardNumUpdated
      const cardNumUpdated = [];
      let position = 0;

      partitions.forEach((expandCard) => {
        const segment = cardNum.substr(position, expandCard);
        if (segment) cardNumUpdated.push(segment);
        position += expandCard;
      });

      // Combine segment array with spaces
      const cardNumFormatted = cardNumUpdated.join(" ");

      // Handle cursor position if user edits the number later
      if (cursor < cardNumFormatted.length - 1) {
        // Determine if the new value entered was valid, and set cursor progression
        cursor = filterSpace !== filtered ? cursor - 1 : cursor;
        setTimeout(() => {
          $this.get(0).setSelectionRange(cursor, cursor, "none");
        });
      }

      return cardNumFormatted;
    });
  });
})(window.jQuery);
