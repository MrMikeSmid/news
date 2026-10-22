<?php
/**
 * Plugin Name: Account Enumeration Guard
 * Description: Limits public account enumeration and guides administrators through public author settings.
 * Version: 1.2.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep login error codes as well as messages uniform. WordPress uses the code
 * to decide whether to preserve the entered username in the form.
 *
 * @param WP_Error $errors Login errors.
 * @return WP_Error
 */
function aeg_generic_login_error( $errors ) {
	if ( ! $errors instanceof WP_Error ) {
		return $errors;
	}

	$account_errors = array( 'invalid_username', 'invalid_email', 'incorrect_password' );
	if ( ! array_intersect( $account_errors, $errors->get_error_codes() ) ) {
		return $errors;
	}

	$generic = new WP_Error();
	foreach ( $errors->get_error_codes() as $code ) {
		if ( in_array( $code, $account_errors, true ) ) {
			continue;
		}
		foreach ( $errors->get_error_messages( $code ) as $message ) {
			$generic->add( $code, $message, $errors->get_error_data( $code ) );
		}
	}

	$generic->add( 'invalid_credentials', __( '<strong>Error:</strong> The username, email address, or password is incorrect.' ) );
	return $generic;
}
add_filter( 'wp_login_errors', 'aeg_generic_login_error', 99 );

/**
 * WordPress returns an error for an unknown reset account and redirects for a
 * known one. Give unknown accounts the same confirmation redirect without
 * generating a reset email. Known accounts still use the core reset flow.
 */
function aeg_unknown_reset_confirmation() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['user_login'] ) || ! is_string( $_POST['user_login'] ) ) {
		return;
	}

	$login = trim( wp_unslash( $_POST['user_login'] ) );
	if ( '' === $login ) {
		return;
	}

	$user = false;
	if ( strpos( $login, '@' ) ) {
		$user = get_user_by( 'email', $login );
	}
	if ( ! $user ) {
		$user = get_user_by( 'login', $login );
	}
	if ( $user ) {
		return;
	}

	$redirect = ! empty( $_REQUEST['redirect_to'] ) && is_string( $_REQUEST['redirect_to'] )
		? wp_unslash( $_REQUEST['redirect_to'] )
		: 'wp-login.php?checkemail=confirm';
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'login_form_lostpassword', 'aeg_unknown_reset_confirmation' );
add_action( 'login_form_retrievepassword', 'aeg_unknown_reset_confirmation' );

/**
 * Hide the public REST user list and individual user records. Signed-in users
 * retain WordPress's normal permission checks, and /users/me stays available.
 *
 * @param mixed           $result  Earlier REST pre-dispatch result.
 * @param WP_REST_Server  $server  REST server.
 * @param WP_REST_Request $request REST request.
 * @return mixed
 */
function aeg_hide_public_rest_users( $result, $server, $request ) {
	if ( null !== $result || is_user_logged_in() || ! in_array( $request->get_method(), array( 'GET', 'HEAD' ), true ) ) {
		return $result;
	}

	$route = $request->get_route();
	if ( '/wp/v2/users' !== $route && ! preg_match( '#^/wp/v2/users/[0-9]+$#', $route ) ) {
		return $result;
	}

	return new WP_Error(
		'rest_user_cannot_view',
		__( 'Sorry, you are not allowed to list users.' ),
		array( 'status' => 403 )
	);
}
add_filter( 'rest_pre_dispatch', 'aeg_hide_public_rest_users', 10, 3 );

require_once __DIR__ . '/includes/author-setup.php';
