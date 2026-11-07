<?php
/**
 * Passwordless service used by the plugin feature tests.
 *
 * @package Otpa
 */

/** Uses in-memory cookies so passwordless behavior can be tested without headers. */
final class Otpa_Test_Passwordless extends Otpa_Passwordless {
	/**
	 * Store the request token in the test request.
	 *
	 * @param string $request_token Passwordless request token.
	 * @param int    $expires       Token expiry time.
	 * @return bool Whether the token was stored.
	 */
	protected function set_request_cookie( $request_token, $expires ) {
		unset( $expires );
		$_COOKIE[ self::REQUEST_COOKIE ] = $request_token;

		return true;
	}

	/**
	 * Store the private nonce session in memory.
	 *
	 * @param string $session Private nonce session.
	 * @param int    $expires Session expiry time.
	 * @return bool Whether the session was stored.
	 */
	protected function set_nonce_session_cookie( $session, $expires ) {
		unset( $expires );
		$this->nonce_session = $session;

		return true;
	}
}
