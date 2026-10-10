<?php
/**
 * REST endpoint to send a test event: POST /wp-json/dm-webhooks/v1/test
 *
 * @package DM_Webhooks
 */

defined( 'ABSPATH' ) || exit;

class DMWH_Rest {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'dm-webhooks/v1',
			'/test',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' );
				},
				'args'                => array(
					'event' => array(
						'type'    => 'string',
						'default' => 'order.created',
						'enum'    => array_keys( DMWH_Settings::events() ),
					),
				),
				'callback'            => array( __CLASS__, 'test' ),
			)
		);
	}

	/**
	 * Dispatches a sample payload so integrations can be wired up before real orders exist.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function test( WP_REST_Request $request ) {
		$event = $request->get_param( 'event' );
		DMWH_Dispatcher::dispatch(
			$event,
			array(
				'order_id' => 0,
				'test'     => true,
				'total'    => 99.9,
				'currency' => get_option( 'woocommerce_currency', 'USD' ),
				'customer' => array( 'email' => 'test@example.com' ),
			)
		);
		return new WP_REST_Response( array( 'queued' => true, 'event' => $event ), 202 );
	}
}
