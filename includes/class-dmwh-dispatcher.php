<?php
/**
 * Sends signed payloads and retries failures via WP-Cron.
 *
 * @package DM_Webhooks
 */

defined( 'ABSPATH' ) || exit;

class DMWH_Dispatcher {

	const RETRY_HOOK = 'dmwh_retry_delivery';

	public static function init() {
		add_action( self::RETRY_HOOK, array( __CLASS__, 'deliver' ), 10, 4 );
	}

	/**
	 * Fans an event out to every active endpoint subscribed to it.
	 *
	 * @param string $event   Event key, e.g. order.paid.
	 * @param array  $payload Event data.
	 */
	public static function dispatch( $event, array $payload ) {
		$settings = DMWH_Settings::get();
		$body     = array(
			'id'         => wp_generate_uuid4(),
			'event'      => $event,
			'created_at' => gmdate( 'c' ),
			'site'       => home_url(),
			'data'       => apply_filters( 'dmwh_payload', $payload, $event ),
		);
		foreach ( $settings['endpoints'] as $ep ) {
			if ( $ep['active'] && in_array( $event, $ep['events'], true ) ) {
				// Deliver asynchronously so checkout is never slowed down by a third party.
				wp_schedule_single_event( time(), self::RETRY_HOOK, array( $ep['url'], $event, $body, 0 ) );
			}
		}
		spawn_cron();
	}

	/**
	 * HMAC-SHA256 signature of the raw body.
	 *
	 * @param string $raw    JSON body.
	 * @param string $secret Shared secret.
	 * @return string
	 */
	public static function sign( $raw, $secret ) {
		return 'sha256=' . hash_hmac( 'sha256', $raw, $secret );
	}

	/**
	 * Performs one delivery attempt and schedules a retry on failure.
	 *
	 * @param string $url     Endpoint.
	 * @param string $event   Event key.
	 * @param array  $body    Envelope.
	 * @param int    $attempt Zero-based attempt number.
	 * @return bool
	 */
	public static function deliver( $url, $event, $body, $attempt = 0 ) {
		$settings = DMWH_Settings::get();
		$raw      = wp_json_encode( $body );
		$headers  = array(
			'Content-Type'    => 'application/json',
			'User-Agent'      => 'DM-Webhooks/' . DMWH_VERSION,
			'X-DMWH-Event'    => $event,
			'X-DMWH-Delivery' => $body['id'],
		);
		if ( $settings['secret'] ) {
			$headers['X-DMWH-Signature'] = self::sign( $raw, $settings['secret'] );
		}

		$res  = wp_remote_post( $url, array( 'body' => $raw, 'headers' => $headers, 'timeout' => 15 ) );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$ok   = $code >= 200 && $code < 300;

		DMWH_Log::add( $url, $event, $body['id'], $attempt + 1, $code, is_wp_error( $res ) ? $res->get_error_message() : '' );

		if ( ! $ok && $attempt < $settings['max_retries'] ) {
			// Exponential backoff: 1, 2, 4, 8... minutes.
			$delay = MINUTE_IN_SECONDS * ( 2 ** $attempt );
			wp_schedule_single_event( time() + $delay, self::RETRY_HOOK, array( $url, $event, $body, $attempt + 1 ) );
		}
		do_action( 'dmwh_delivered', $ok, $url, $event, $body, $code );
		return $ok;
	}
}
