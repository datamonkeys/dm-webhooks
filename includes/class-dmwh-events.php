<?php
/**
 * Hooks WooCommerce order lifecycle into webhook events.
 *
 * @package DM_Webhooks
 */

defined( 'ABSPATH' ) || exit;

class DMWH_Events {

	public static function init() {
		add_action( 'woocommerce_new_order', array( __CLASS__, 'created' ), 20, 2 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'paid' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'status_changed' ), 10, 4 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'refunded' ), 10, 2 );
	}

	public static function created( $order_id, $order = null ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( $order ) {
			DMWH_Dispatcher::dispatch( 'order.created', self::order_payload( $order ) );
		}
	}

	public static function paid( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			DMWH_Dispatcher::dispatch( 'order.paid', self::order_payload( $order ) );
		}
	}

	public static function status_changed( $order_id, $from, $to, $order ) {
		$data         = self::order_payload( $order );
		$data['from'] = $from;
		$data['to']   = $to;
		DMWH_Dispatcher::dispatch( 'order.status_changed', $data );
	}

	public static function refunded( $order_id, $refund_id ) {
		$order  = wc_get_order( $order_id );
		$refund = wc_get_order( $refund_id );
		if ( $order ) {
			$data           = self::order_payload( $order );
			$data['refund'] = array(
				'id'     => $refund_id,
				'amount' => $refund ? (float) $refund->get_amount() : null,
				'reason' => $refund ? $refund->get_reason() : '',
			);
			DMWH_Dispatcher::dispatch( 'order.refunded', $data );
		}
	}

	/**
	 * Builds a compact, CRM-friendly order payload.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function order_payload( WC_Order $order ) {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$items[] = array(
				'product_id' => $item->get_product_id(),
				'sku'        => $product ? $product->get_sku() : '',
				'name'       => $item->get_name(),
				'quantity'   => $item->get_quantity(),
				'total'      => (float) $item->get_total(),
			);
		}
		return array(
			'order_id'       => $order->get_id(),
			'number'         => $order->get_order_number(),
			'status'         => $order->get_status(),
			'currency'       => $order->get_currency(),
			'total'          => (float) $order->get_total(),
			'payment_method' => $order->get_payment_method(),
			'customer'       => array(
				'id'         => $order->get_customer_id(),
				'email'      => $order->get_billing_email(),
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'phone'      => $order->get_billing_phone(),
			),
			'items'          => $items,
			'utm'            => array_filter(
				array(
					'source'   => $order->get_meta( '_wc_order_attribution_utm_source' ),
					'medium'   => $order->get_meta( '_wc_order_attribution_utm_medium' ),
					'campaign' => $order->get_meta( '_wc_order_attribution_utm_campaign' ),
				)
			),
		);
	}
}
