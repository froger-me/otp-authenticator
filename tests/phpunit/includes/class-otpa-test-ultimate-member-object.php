<?php
/**
 * Object used to confirm saved form fields cannot create PHP objects.
 *
 * @package Otpa
 */

/** Records whether PHP restored this object from serialized text. */
final class Otpa_Test_Ultimate_Member_Object {
	/**
	 * Number of times PHP restored this object.
	 *
	 * @var int
	 */
	public static $wakeups = 0;

	/** Record an unsafe object restoration. */
	public function __wakeup() {
		++self::$wakeups;
	}
}
