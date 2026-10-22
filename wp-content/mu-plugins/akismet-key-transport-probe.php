<?php
/** Temporary Akismet transport probe. Remove after one run. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || 'run' !== ( $_GET['akismet_key_transport_probe'] ?? '' ) ) {
		return;
	}
	if ( ! class_exists( 'Akismet' ) || ! Akismet::get_api_key() ) {
		wp_send_json( array( 'error' => 'Akismet is inactive or has no API key.' ), 503 );
	}

	$configured_key = (string) Akismet::get_api_key();
	$key_proof = array(
		'length' => strlen( $configured_key ),
		'last_four' => substr( $configured_key, -4 ),
		'sha256' => hash( 'sha256', $configured_key ),
	);
	$synthetic_auth = 'Basic ' . base64_encode( 'audit-only:synthetic-value' );
	$previous_auth  = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
	$previous_post  = $_POST;
	$comment_args   = null;
	$attempts       = array();
	$http_result    = null;
	$error          = null;
	$missing        = array( 'akismet_audit_missing' => true );
	$option_names   = array(
		'akismet_ssl_disabled', 'akismet_alert_code', 'akismet_alert_msg',
		'akismet_alert_api_calls', 'akismet_alert_usage_limit',
		'akismet_alert_upgrade_plan', 'akismet_alert_upgrade_url',
		'akismet_alert_upgrade_type', 'akismet_alert_upgrade_via_support',
		'akismet_alert_recommended_plan_name',
	);
	$old_options = array();
	foreach ( $option_names as $option_name ) {
		$old_options[ $option_name ] = get_option( $option_name, $missing );
	}

	$capture_comment = function ( $comment, $endpoint ) use ( &$comment_args ) {
		if ( 'comment-check' === $endpoint ) {
			$comment_args = $comment;
			throw new RuntimeException( 'AKISMET_AUDIT_CAPTURED' );
		}
		return $comment;
	};
	$transport = function ( $pre, $args, $url ) use ( &$attempts, $synthetic_auth ) {
		if ( 'rest.akismet.com' !== wp_parse_url( $url, PHP_URL_HOST ) || '/1.1/comment-check' !== wp_parse_url( $url, PHP_URL_PATH ) ) {
			return $pre;
		}
		$body = array();
		parse_str( $args['body'], $body );
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$attempts[] = array(
			'scheme' => $scheme,
			'configured_key_in_body' => isset( $body['api_key'] ) && hash_equals( (string) Akismet::get_api_key(), (string) $body['api_key'] ),
			'outbound_key_sha256' => isset( $body['api_key'] ) ? hash( 'sha256', (string) $body['api_key'] ) : null,
			'synthetic_authorization_in_body' => ( $body['HTTP_AUTHORIZATION'] ?? null ) === $synthetic_auth,
		);
		if ( 'https' === $scheme ) {
			return new WP_Error( 'akismet_audit_https_failure', 'Simulated HTTPS failure.' );
		}
		return $pre; // Let the actual HTTP request leave the server.
	};
	$observe = function ( $response, $context, $class, $args, $url ) use ( &$http_result ) {
		if ( 'http' !== wp_parse_url( $url, PHP_URL_SCHEME ) || 'rest.akismet.com' !== wp_parse_url( $url, PHP_URL_HOST ) ) {
			return;
		}
		$http_result = is_wp_error( $response )
			? array( 'error_code' => $response->get_error_code() )
			: array( 'status' => wp_remote_retrieve_response_code( $response ) );
	};

	try {
		$_SERVER['HTTP_AUTHORIZATION'] = $synthetic_auth;
		$_POST['aeg_audit_field']      = 'synthetic-value';
		add_filter( 'akismet_request_args', $capture_comment, PHP_INT_MAX, 2 );
		add_filter( 'akismet_debug_log', '__return_false' );
		try {
			Akismet::auto_check_comment( array(
				'comment_post_ID'      => 1,
				'comment_author'       => 'Synthetic audit',
				'comment_author_email' => 'audit@example.invalid',
				'comment_content'      => 'Synthetic Akismet audit probe.',
				'comment_author_IP'    => '127.0.0.1',
				'comment_agent'        => 'Akismet audit probe',
			) );
		} catch ( RuntimeException $exception ) {
			if ( 'AKISMET_AUDIT_CAPTURED' !== $exception->getMessage() ) {
				throw $exception;
			}
		}
		remove_filter( 'akismet_request_args', $capture_comment, PHP_INT_MAX );
		if ( ! is_array( $comment_args ) ) {
			throw new RuntimeException( 'Akismet did not assemble comment data.' );
		}
		add_filter( 'option_akismet_ssl_disabled', '__return_false' );
		add_filter( 'pre_http_request', $transport, PHP_INT_MAX, 3 );
		add_action( 'http_api_debug', $observe, 10, 5 );
		Akismet::http_post( Akismet::build_query( $comment_args ), 'comment-check' );
	} catch ( Throwable $exception ) {
		$error = 'The transport probe stopped before completing.';
	} finally {
		remove_filter( 'akismet_request_args', $capture_comment, PHP_INT_MAX );
		remove_filter( 'pre_http_request', $transport, PHP_INT_MAX );
		remove_action( 'http_api_debug', $observe, 10 );
		remove_filter( 'option_akismet_ssl_disabled', '__return_false' );
		remove_filter( 'akismet_debug_log', '__return_false' );
		$_POST = $previous_post;
		if ( null === $previous_auth ) {
			unset( $_SERVER['HTTP_AUTHORIZATION'] );
		} else {
			$_SERVER['HTTP_AUTHORIZATION'] = $previous_auth;
		}
		foreach ( $old_options as $option_name => $old_value ) {
			if ( $old_value === $missing ) {
				delete_option( $option_name );
			} elseif ( get_option( $option_name ) !== $old_value ) {
				update_option( $option_name, $old_value );
			}
		}
	}

	nocache_headers();
	wp_send_json( array(
		'key_proof' => $key_proof,
		'comment_authorization_copied' => ( $comment_args['HTTP_AUTHORIZATION'] ?? null ) === $synthetic_auth,
		'attempts' => $attempts,
		'http_result' => $http_result,
		'error' => $error,
	), $error ? 500 : 200 );
}, 20 );
