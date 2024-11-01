<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( ! defined( 'WT_BACKUPS_INIT' ) || WT_BACKUPS_INIT !== true ) {
	if ( ! headers_sent() ) {
		header( 'HTTP/1.1 403 Forbidden' );
	}
	exit( 1 );
}

/**
 * WebTotem Option class.
 */
class WT_Backups_Option {

	/**
	 * Get config option.
	 *
	 * @param string $option
	 *   Option name.
	 *
	 * @return mixed
	 *   Returns saved data by option name.
	 */
	public static function getOption( $option ) {
		$data = WT_Backups_DB::getData( $option, 'settings' );

		return ( array_key_exists( 'value', $data ) ) ? $data['value'] : '';
	}

	/**
	 * Save multiple configuration options.
	 *
	 * @param array $options
	 *   Array of data, key is name of option.
	 *
	 * @return bool
	 *   Returns TRUE after setting the options.
	 */
	public static function setOptions( array $options ) {
		foreach ( $options as $option => $value ) {
			$value = is_array( $value ) ? wp_json_encode( $value ) : $value;
			WT_Backups_DB::setData( [ 'name' => $option, 'value' => $value, ], 'settings', $option );
		}

		return true;
	}

	/**
	 * Clear multiple configuration options.
	 *
	 * @param array $options
	 *   Array of data, key is name of option.
	 *
	 * @return bool
	 *   Returns TRUE after clearing the options.
	 */
	public static function clearOptions( array $options ) {
		foreach ( $options as $option ) {
			WT_Backups_DB::deleteData( [ 'name' => $option ], 'settings' );
		}

		return true;
	}

    /**
     * Save authentication token and token expiration dates in settings.
     *
     * @param array $params
     *   Parameters for authorization.
     *
     * @return string
     *   Returns TRUE after setting the options.
     */
    public static function login(array $params) {
        $token_expired = time() + $params['token']['expiresIn'] - 60;

        self::setOptions([
            'activated' => TRUE,
            'auth_token_expired' => $token_expired,
            'auth_token' => $params['token']['value'],
            'api_key' => $params['api_key']
        ]);

        return TRUE;
    }

    /**
     * Checks whether the user has activated the plugin using the API key.
     *
     * @return bool
     *   Returns the module activation status.
     */
    public static function isActivated() {
        return true; //(boolean) self::getOption('activated');
    }

    /**
     * Remove module settings.
     *
     * @return string
     *   Returns TRUE after clearing the options.
     */
    public static function logout() {

        self::clearOptions([
            'activated',
            'auth_token_expired',
            'auth_token',
            'api_key',
            'api_url',
        ]);
        return TRUE;
    }


    /**
	 * Save multiple some options to session.
	 *
	 * @param array $options
	 *   Array of data, key is name of option.
	 *
	 * @return bool
	 *   Returns TRUE after setting the session options.
	 */
	public static function setSessionOptions( array $options ) {

		$sessions = json_decode( self::getOption( 'sessions' ), true ) ?: [];
		$user_id  = get_current_user_id();

		foreach ( $options as $option => $value ) {
			$sessions[ $user_id ][ $option ] = $value;
		}

		self::setOptions( [ 'sessions' => $sessions ] );

		return true;
	}

	/**
	 * Get option from session.
	 *
	 * @param string $option
	 *   Option name.
	 *
	 * @return mixed
	 *   Returns saved data by option name.
	 */
	public static function getSessionOption( $option ) {

		$sessions = json_decode( self::getOption( 'sessions' ), true ) ?: [];
		$user_id  = get_current_user_id();

		if ( array_key_exists( $user_id, $sessions ) and array_key_exists( $option, $sessions[ $user_id ] ) ) {
			return $sessions[ $user_id ][ $option ];
		} else {
			return [];
		}

	}

	/**
	 * Set notification.
	 *
	 * @param string $type
	 *   Notification Type.
	 * @param string $notice
	 *   Notification Text.
	 */
	public static function setNotification( $type, $notice ) {
		$notifications = self::getSessionOption( 'notifications' ) ?: [];

		if ( array_key_exists( $type, $notifications ) ) {
			if ( ! in_array( $notice, $notifications[ $type ] ) ) {
				$notifications[ $type ][] = $notice;
				self::setSessionOptions( [ 'notifications' => $notifications ] );
			}
		} else {
			$notifications[ $type ][] = $notice;
			self::setSessionOptions( [ 'notifications' => $notifications ] );
		}

	}

	/**
	 * Get notifications.
	 *
	 * @return array
	 *   Notifications array.
	 */
	public static function getNotificationsData() {
		$types = [ 'error', 'info', 'warning', 'success' ];

		$notifications = self::getSessionOption( 'notifications' ) ?: [];
		$result        = [];

		foreach ( $types as $type ) {
			if ( array_key_exists( $type, $notifications ) ) {
				foreach ( $notifications[ $type ] as $notification ) {
					$result[] = [ 'type' => $type, 'notice' => $notification ];
				}
			}
		}

		// Remove notifications.
		self::setSessionOptions( [ 'notifications' => [] ] );

		return $result;
	}


}
