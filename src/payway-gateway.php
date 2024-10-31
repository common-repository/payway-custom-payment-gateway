<?php
/**
 * Payway WooCommerce Payment Gateway.
 */
class Payway_Gateway extends WC_Payment_Gateway
{

  public $domain;

  /**
   * Class constructor, more about it in Step 3.
   */
  public function __construct()
  {
    $this->domain = __CLASS__;
    $this->id = 'payway';
    $this->icon = apply_filters( 'woocommerce_custom_gateway_icon', plugins_url( '/assets/img/payway.png', __DIR__ ) );
    $this->has_fields = false;
    $this->title = __( 'Payway', $this->domain );
    $this->method_title = __( 'Payway', $this->domain );
    $this->method_description = __( 'Payway works by adding credit card fields on the checkout and then sending the details to the gateway for processing the transactions.', $this->domain );
    $this->instructions = null;

    // gateways can support subscriptions, refunds, saved payment methods,
    // but in this tutorial we begin with simple payments
    $this->supports = [
      'products',
      'default_credit_card_form',
      'refunds',
      'subscriptions',
      'subscription_cancellation',
      'subscription_suspension',
      'subscription_reactivation',
      'subscription_amount_changes',
      'subscription_date_changes',
      'subscription_payment_method_change',
      'subscription_payment_method_change_customer',
      'subscription_payment_method_change_admin',
      'multiple_subscriptions',
    ];

    // Method with all the options fields
    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables
    $this->title = $this->get_option( 'title' );
    $this->description = $this->get_option( 'description' );
    $this->enabled = $this->get_option( 'enabled' );
    $this->company_id = $this->get_option( 'company_id' );
    $this->userName = $this->get_option( 'userName' );
    $this->password = $this->get_option( 'password' );
    $this->sourceId = $this->get_option( 'sourceId' );

    $this->order_status = $this->get_option( 'order_status', 'completed' );
    $this->mode = $this->get_option( 'mode' );

    add_action( 'admin_notices', [ $this, 'do_ssl_check' ] );
    add_action( 'admin_notices', [ $this, 'admin_notices' ] );
    add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
    add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
    add_action( 'woocommerce_email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );
  }

  /**
   * Displaying the Payway plugin options into admin payment section inside woocommerce tab.
   */
  public function init_form_fields(): void
  {
    $this->form_fields = [
      'enabled' => [
        'title' => __( 'Enable/Disable', $this->domain ),
        'type' => 'checkbox',
        'label' => __( 'Enable Payway Payment', $this->domain ),
        'description' => __( 'This enable the Payway gateway which allow to accept payment through creadit card.', $this->domain ),
        'default' => 'no',
      ],
      'title' => [
        'title' => __( 'Title', $this->domain ),
        'type' => 'text',
        'description' => __( 'This controls the title which the user sees during checkout.', $this->domain ),
        'default' => __( 'Payway Payment', $this->domain ),
        'desc_tip' => true,
      ],
      'order_status' => [
        'title' => __( 'Order Status', $this->domain ),
        'type' => 'select',
        'class' => 'wc-enhanced-select',
        'description' => __( 'Choose whether status you wish after checkout.', $this->domain ),
        'default' => 'wc-completed',
        'desc_tip' => true,
        'options' => wc_get_order_statuses(),
      ],
      'description' => [
        'title' => __( 'Description', $this->domain ),
        'type' => 'textarea',
        'description' => __( 'Payment method description that the customer will see on your checkout.', $this->domain ),
        'default' => __( 'Payment Information', $this->domain ),
        'desc_tip' => true,
      ],
      'instructions' => [
        'title' => __( 'Instructions', $this->domain ),
        'type' => 'textarea',
        'description' => __( 'Instructions that will be added to the thank you page and emails.', $this->domain ),
        'default' => '',
        'desc_tip' => true,
      ],
      'company_id' => [
        'title' => __( 'Company ID', $this->domain ),
        'type' => 'text',
        'description' => __( 'Enter the Company ID.', $this->domain ),
        'default' => '',
        'required' => true,
        'desc_tip' => true,
      ],
      'sourceId' => [
        'title' => __( 'Source ID', $this->domain ),
        'type' => 'text',
        'description' => __( 'Enter the sourceId.', $this->domain ),
        'default' => '',
        'required' => true,
        'desc_tip' => true,
      ],
      'userName' => [
        'title' => __( 'Username', $this->domain ),
        'type' => 'text',
        'description' => __( 'Enter the username.', $this->domain ),
        'default' => '',
        'required' => true,
        'desc_tip' => true,
      ],
      'password' => [
        'title' => __( 'Password', $this->domain ),
        'type' => 'password',
        'description' => __( 'Enter the password.', $this->domain ),
        'default' => '',
        'required' => true,
        'desc_tip' => true,
      ],
      'mode' => [
        'type' => 'select',
        'title' => __( 'Mode', $this->domain ),
        'class' => 'wc-enhanced-select',
        'options' => [
          'test' => __( 'Test', $this->domain ),
          'live' => __( 'Live', $this->domain ),
        ],
        'default' => 'test',
        'desc_tip' => true,
        'description' => __(
          'The mode determines if you are processing test transactions or live transactions on your site. Test mode allows you to simulate payments so you can test your integration.',
          $this->domain
        ),
      ],
    ];
  }

  /**
   * Invalid Field Check.
   *
   * @param mixed $name
   */
  public function invalid( $name ): void
  {
    global $payway_validation_notices;

    $valid = $payway_validation_notices[ $name ] ?? null;

    echo ( false === $valid ) ? ' payway__invalid' : '';
  }

  /**
   * Payment form on checkout page.
   */
  public function payment_fields(): void
  {
    // Description
    $description = $this->get_description();
    $description = ! empty( $description ) ? trim( $description ) : '';
    $description = apply_filters( 'wc_payway_description', wpautop( wp_kses_post( $description ) ), $this->id );

    // Form
    ob_start();
    ?>
  <div class="payway__description"><?php esc_attr_e( $description ); ?></div>
  <div id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class="wc-credit-card-form wc-payment-form">
    <div id="payway">
    <div class="payway__container">
      <div class="payway__form-row" data-payway-field="card_name">
      <label class="payway__label" for="card-name">Cardholder Name <abbr class="required">*</abbr></label>
      <input class="payway__input card-name" id="card-name" type="text" name="card_name" placeholder="First Last" onkeypress='return ((event.charCode >= 65 && event.charCode <= 90) || (event.charCode >= 97 && event.charCode
        <= 122) || (event.charCode == 32))' />
      </div>
      <div class="payway__form-row" data-payway-field="number">
      <label class="payway__label" for="card-number">Card Number <abbr class="required">*</abbr></label>
      <input class="payway__input card-number" id="card-number" type="text" name="number" placeholder="•••• •••• •••• ••••" />
      </div>
      <div class="payway__form-cols">
      <div class="payway__form-col" data-payway-field="expiry">
        <label class="payway__label" for="card-expiry">Expiration <abbr class="required">*</abbr></label>
        <input class="payway__input card-expiry" id="card-expiry" type="text" name="expiry" placeholder="MM/YY" />
      </div>
      <div class="payway__form-col" data-payway-field="cvc">
        <label class="payway__label" for="card-cvc">CVC <abbr class="required">*</abbr></label>
        <input class="payway__input card-cvc" id="card-cvc" type="text" name="cvc" placeholder="•••" />
      </div>
      </div>
    </div>
    </div>
  </div>
    <?php

    do_action( 'wc_payway_payment_fields_payway', $this->id );

    ob_end_flush();
  }

  // Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
  public function payment_scripts(): void
  {
    // we need JavaScript to process a token only on cart/checkout pages, right?
    if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['place_order'] ) ) {
      return;
    }

    // if our payment gateway is disabled, we do not have to enqueue JS too
    if ( 'no' === $this->enabled ) {
      return;
    }

    $assets = plugin_dir_url( __DIR__ ) . 'assets';
    $path = plugin_dir_path( __DIR__ );

    wp_enqueue_script( 'payway-checkout', "$assets/js/checkout.js", [ 'jquery' ], filemtime( "$path/assets/js/checkout.js" ), true );

    wp_enqueue_style( 'payway-checkout-css', "$assets/css/payway.css", [], filemtime( "$path/assets/css/payway.css" ) );
  }

  /**
   * Displaying SSL error message on admin dashboard.
   */
  public function do_ssl_check(): void
  {
    if ( 'yes' == $this->enabled ) {
      if ( 'no' == get_option( 'woocommerce_force_ssl_checkout' ) ) {
        echo '<div class="error"><p>' . sprintf( __( '<strong>%1$s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href="%2$s">forcing the checkout pages to be secured.</a>' ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=advanced' ) ) . '</p></div>';
      }
    }
  }

  /**
   * Fields validation, warning error showing on admin side and saving and updating data.
   */
  public function admin_notices(): void
  {
    if ( 'no' == $this->enabled ) {
      return;
    }

    // Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected
    if ( ! wc_checkout_is_https() ) {
      echo '<div class="notice notice-warning"><p>' . sprintf( __( 'Payway is enabled, but a SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid <a href="%1$s" target="_blank">SSL certificate</a>', $this->domain ), 'https://en.wikipedia.org/wiki/Transport_Layer_Security' ) . '</p></div>';
    }

    if ( '' == ! $this->company_id ) {
      ?>
    <script type="text/javascript">
    jQuery(document).ready(function() {
      jQuery('.woocommerce-save-button').click(function() {
       if (!jQuery('#woocommerce_payway_company_id').val()) {
          alert('Enter the payway Company id');
          return false
          }else if (!jQuery('#woocommerce_payway_userName').val()) {
            alert('Enter the payway UserName');
            return false
          }else if (!jQuery('#woocommerce_payway_password').val()) {
            alert('Enter the payway Password');
            return false
          }else if (!jQuery('#woocommerce_payway_sourceId').val()) {
            alert('Enter the payway source Id');
            return false
          }
      });
    });
    </script>
      <?php
    }
  }

  /**
   * Output for the order received page.
   */
  public function thankyou_page(): void
  {
    if ( $this->instructions ) {
      echo wpautop( wptexturize( esc_attr( $this->instructions ) ) );
    }
  }

  /**
   * Add content to the WC emails.
   *
   * @param WC_Order $order
   * @param bool     $sent_to_admin
   * @param bool     $plain_text
   */
  public function email_instructions( $order, $sent_to_admin, $plain_text = false ): void
  {
    if ( $this->instructions && ! $sent_to_admin && 'payway' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
      echo wpautop( wptexturize( esc_attr( $this->instructions ) ) ) . PHP_EOL;
    }
  }

  /**
   * We're processing the payments here, everything about it is in Step 5
   * funation process_payment is working for the payment process.
   *
   * @param mixed $order_id
   */
  public function process_payment( $order_id )
  {
    global $woocommerce, $wpdb;

    $obj = new PaywayAPI();
    $tokenResponse = $obj->token;
    $saleApiResponce = $obj->sale;
    $urls = $obj->urls;

    // we need it to get any order details
    $order = wc_get_order( $order_id );

    $data = [];
    $data['company_id'] = $this->get_option( 'company_id' );
    $data['password'] = $this->get_option( 'password' );
    $data['source_id'] = $this->get_option( 'sourceId' );
    $data['mode'] = $this->get_option( 'mode' );
    $data['userName'] = $this->get_option( 'userName' );
    $Modetype = $data['mode'];

    if ( 'live' == $Modetype ) {
      $url = $urls['tokenUrl'];
      $saleApiUrl = $urls['saleUrl'];
    } else {
      $url = $urls['testTokenUrl'];
      $saleApiUrl = $urls['testSaleUrl'];
    }
    $ids = $order->id;
    $address_1 = $order->data['billing']['address_1'];
    $city = $order->data['billing']['city'];
    $state = $order->data['billing']['state'];
    $postcode = $order->data['billing']['postcode'];
    $emails = $order->data['billing']['email'];
    $phones = $order->data['billing']['phone'];
    $customerNote = $order->data['customer_note'];

    $newComp = $data['company_id'];
    $userName = $data['userName'];
    $password = $data['password'];
    $tokenResponse['companyId'] = trim( $newComp );
    $tokenResponse['userName'] = trim( $userName );
    $tokenResponse['password'] = trim( $password );

    $body = $tokenResponse;
    $response = wp_remote_post(
      $url,
      [
        'method' => 'POST',
        'timeout' => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode( $body ),
        'cookies' => [],
      ]
    );

    if ( is_wp_error( $response ) ) {
      $error_message = $response->get_error_message();
      esc_attr_e( "Something went wrong: $error_message" );
      wc_add_notice( 'Failed to logon.', 'error' );
    } else {
      $data_token = json_decode( $response['body'] );
      $token_responce = $data_token->paywaySessionToken;
    }

    if ( ! empty( $token_responce ) ) {
      $totalProductAmount = $order->get_total();
      $newTotal = ( $totalProductAmount ) * 100;
      $totalTax = $order->data['total_tax'];
      $newTax = ( $totalTax ) * 100;
      $newTax = (int) $newTax;
      $paywaySessionToken = $token_responce;

      $creditCardNumber = sanitize_text_field( $_POST['number'] );
      $creditCardNumber = str_replace( ' ', '', $creditCardNumber );

      $cardholerName = sanitize_text_field( $_POST['card_name'] );
      $cardCVC = sanitize_text_field( $_POST['cvc'] );
      $sourceId = $data['source_id'];
      $arr = explode( ' ', $cardholerName );

      $arrLength = \count( $arr );
      if ( 3 == $arrLength ) {
        $firstNames = $arr[0];
        $middleName = $arr[1];
        $lastNames = $arr[2];
      } elseif ( 2 == $arrLength ) {
        $firstNames = $arr[0];
        $lastNames = $arr[1];
      } else {
        $firstNames = $arr[0];
      }
      $cardExpiry = sanitize_text_field( $_POST['expiry'] );
      $cardDate = str_replace( ' ', '', $cardExpiry );
      $saleApiResponce['cardAccount']['accountNumber'] = $creditCardNumber;
      $saleApiResponce['cardAccount']['expirationDate'] = $cardDate;
      $saleApiResponce['cardAccount']['firstName'] = $firstNames;
      if ( isset( $middleName ) ) {
        $saleApiResponce['cardAccount']['middleName'] = $middleName;
      }
      $saleApiResponce['cardAccount']['lastName'] = $lastNames;
      $saleApiResponce['cardAccount']['fsv'] = $cardCVC;
      $saleApiResponce['cardAccount']['address'] = $address_1;
      $saleApiResponce['cardAccount']['city'] = $city;
      $saleApiResponce['cardAccount']['state'] = $state;
      $saleApiResponce['cardAccount']['zip'] = $postcode;
      $saleApiResponce['cardAccount']['email'] = $emails;
      $saleApiResponce['cardAccount']['phone'] = $phones;

      $saleApiResponce['cardTransaction']['amount'] = $newTotal;
      $saleApiResponce['cardTransaction']['name'] = '';
      $saleApiResponce['cardTransaction']['sourceId'] = $sourceId;
      $saleApiResponce['cardTransaction']['tax'] = $newTax;
      $saleApiResponce['cardTransaction']['transactionNotes1'] = $ids;
      $saleApiResponce['cardTransaction']['transactionNotes2'] = $customerNote;
      $saleApiResponce['paywaySessionToken'] = $paywaySessionToken;
      $body = $saleApiResponce;
      $response = wp_remote_post(
        $saleApiUrl,
        [
          'method' => 'POST',
          'timeout' => 60,
          'redirection' => 5,
          'httpversion' => '1.0',
          'blocking' => true,
          'headers' => [
            'Content-Type' => 'application/json',
            'accept' => 'application/json',
          ],
          'body' => wp_json_encode( $body ),
          'cookies' => [],
        ]
      );

      if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        wc_add_notice( $error_message );

        return;
      }
      $data_resp = json_decode( $response['body'] );
      $paywayCode = $data_resp->paywayCode;
      $paywayError = $data_resp->paywayError;
      $paywayError = str_replace( [ 'column: ' ], '', $paywayError );
      $paywayError = "PayWay payment processing error: $paywayError";
      trigger_error( $paywayError, E_USER_NOTICE );

      if ( ! empty( $order ) ) {
        $status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;
        if ( ! is_wp_error( $response ) ) {
          // it could be different depending on your payment processor
          if ( '5000' == $paywayCode ) {
            global $wpdb;

            // Set order status
            $order->update_status( $status, __( 'Checkout with custom payment. ', $this->domain ) );
            // we received the payment
            $order->payment_complete();
            $order->reduce_order_stock();

            // some notes to customer (replace true with false to make it private)
            $order->add_order_note( 'Hey, your order is paid! Thank you!', true );

            // Empty cart
            WC()->cart->empty_cart();

            // Redirect to the thank you page
            return [
              'result' => 'success',
              'redirect' => $this->get_return_url( $order ),
            ];
          }
          wc_add_notice( "Payment processing error: please confirm that the card number, expiration date, and CVC code you've provided are correct.", 'error' );

          return;
        }
        wc_add_notice( 'Connection error.', 'error' );

        return;
      }
    } else {
      wc_add_notice( 'Failed to logon.', 'error' );
    }
  }
}
