<?php
/**
 * Payway Initialize
 *
 * Adds a PayWay payment gateway to WooCommerce
 */
class Payway_Init
{

  public function __construct()
  {
    add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'payment_update_order_meta' ] );
    add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'checkout_field_display_admin_order_meta' ], 10, 1 );
    add_action( 'woocommerce_checkout_process', [ $this, 'validate_payment' ] );
    add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateway' ], 1 );
    add_action( 'plugins_loaded', [ $this, 'initialize' ], 0 );
  }

  /**
   * Initialize WooCommerce Gateway.
   */
  public function initialize(): void
  {
    // WooCommerce not installed
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
      return;
    }

    include_once plugin_dir_path( __FILE__ ) . 'payway-api.php';
    include_once plugin_dir_path( __FILE__ ) . 'payway-gateway.php';
  }

  /**
   * Payway WooCommerce Gateway.
   *
   * @param mixed $methods
   */
  public function add_gateway( $methods )
  {
    $methods[] = 'Payway_Gateway';

    return $methods;
  }

  /**
   * Display data on to admin order detail page.
   */
  public function validate_payment(): void
  {
    if ( 'payway' != sanitize_text_field( $_POST['payment_method'] ) ) {
      return;
    }

    global $payway_validation_notices;

    $payway_validation_notices = [];
    $card_name = isset( $_POST['card_name'] ) ? sanitize_text_field( $_POST['card_name'] ) : null;
    $number = isset( $_POST['number'] ) ? sanitize_text_field( $_POST['number'] ) : null;
    $number = str_replace( ' ', '', $number );
    $expiry = isset( $_POST['expiry'] ) ? sanitize_text_field( $_POST['expiry'] ) : null;
    $cvc = isset( $_POST['cvc'] ) ? sanitize_text_field( $_POST['cvc'] ) : null;

    $payway_validation_notices['card_name'] = empty( $card_name );
    $payway_validation_notices['number'] = empty( $number );
    $payway_validation_notices['expiry'] = empty( $expiry );
    $payway_validation_notices['cvc'] = empty( $cvc );

    if ( empty( $card_name ) ) {
      wc_add_notice( __( '<strong data-payway-error="card_name">Cardholder Name</strong> is a required field.', 'payway_payment' ), 'error' );
    }

    // Number
    if ( empty( $number ) ) {
      wc_add_notice( __( '<strong data-payway-error="number">Card Number</strong> is a required field.', 'payway_payment' ), 'error' );
    }
    if ( 15 !== \strlen( $number ) && 16 !== \strlen( $number ) ) {
      wc_add_notice( __( '<strong data-payway-error="number">Card Number</strong> provided is invalid.', 'payway_payment' ), 'error' );
    }

    // Expiration
    if ( empty( $expiry ) ) {
      wc_add_notice( __( '<strong data-payway-error="expiry">Expiry Date</strong> is a required field.', 'payway_payment' ), 'error' );
    }
    $expiry_match = preg_match_all( '~[0-9]{2}/[0-9]{2}~m', $expiry, $matches, PREG_SET_ORDER, 0 );
    if ( 1 !== $expiry_match ) {
      wc_add_notice( __( '<strong data-payway-error="expiry">Expiry Date</strong> must be in the format MM/DD.', 'payway_payment' ), 'error' );
    }

    // CVC
    if ( empty( $cvc ) ) {
      wc_add_notice( __( '<strong data-payway-error="cvc">CVC</strong> is a required field.', 'payway_payment' ), 'error' );
    }
    if ( 3 !== \strlen( $cvc ) && 4 !== \strlen( $cvc ) ) {
      wc_add_notice( __( '<strong data-payway-error="cvc">CVC</strong> must be a 3 or 4 digit number.', 'payway_payment' ), 'error' );
    }
  }

  /**
   * Update the order meta with field value.
   *
   * @param mixed $order_id
   */
  public function payment_update_order_meta( $order_id ): void
  {
    if ( 'payway' != sanitize_text_field( $_POST['payment_method'] ) ) {
      return;
    }

    $number = str_replace( ' ', '', sanitize_text_field( $_POST['number'] ) );
    update_post_meta( $order_id, 'card_name', sanitize_text_field( $_POST['card_name'] ) );
    update_post_meta( $order_id, 'number', $number );
    update_post_meta( $order_id, 'expiry', sanitize_text_field( $_POST['expiry'] ) );
    update_post_meta( $order_id, 'cvc', sanitize_text_field( $_POST['cvc'] ) );
  }

  /**
   * Display field value on the order edit page.
   *
   * @param mixed $order
   */
  public function checkout_field_display_admin_order_meta( $order ): void
  {
    $method = get_post_meta( $order->id, '_payment_method', true );
    if ( 'payway' != $method ) {
      return;
    }

    $name = get_post_meta( $order->id, 'card_name', true );
    $name = esc_attr( $name );
    $number = get_post_meta( $order->id, 'number', true );
    $expiry = get_post_meta( $order->id, 'expiry', true );
    $cvc = get_post_meta( $order->id, 'cvc', true );

    echo '<p><strong>' . __( 'Name on the Card' ) . ':</strong> ' . $name . '</p>';
  }
}

new Payway_Init();
