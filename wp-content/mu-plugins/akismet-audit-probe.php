<?php
/**
 * Temporary, read-only Akismet probe. Remove immediately after the audit.
 * All credentials and requests used here are synthetic; no Akismet request
 * from this probe is allowed to reach the network.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || 'run' !== ( $_GET['akismet_audit_probe'] ?? '' ) ) {
		return;
	}

	if ( ! class_exists( 'Akismet' ) || ! Akismet::get_api_key() ) {
		wp_send_json( array( 'error' => 'Akismet is inactive or has no API key.' ), 503 );
	}

	$synthetic_auth = 'Basic ' . base64_encode( 'audit-only:synthetic-value' );
	$previous_auth  = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
	$previous_post  = $_POST;
	$fields         = array();
	$calls          = array();
	$error          = null;

	$stop_after_capture = function ( $comment, $endpoint ) use ( &$fields, $synthetic_auth ) {
		if ( 'comment-check' === $endpoint ) {
			$fields = array(
				'authorization_present' => isset( $comment['HTTP_AUTHORIZATION'] ),
				'synthetic_authorization_copied' => ( $comment['HTTP_AUTHORIZATION'] ?? null ) === $synthetic_auth,
				'synthetic_post_field_copied' => ( $comment['POST_aeg_audit_field'] ?? null ) === 'synthetic-value',
			);
			throw new RuntimeException( 'AKISMET_AUDIT_CAPTURED' );
		}
		return $comment;
	};

	$block_network = function ( $pre, $args, $url ) use ( &$calls ) {
		if ( 'rest.akismet.com' === wp_parse_url( $url, PHP_URL_HOST ) && '/1.1/comment-check' === wp_parse_url( $url, PHP_URL_PATH ) ) {
			$calls[] = wp_parse_url( $url, PHP_URL_SCHEME );
			return new WP_Error( 'akismet_audit_blocked', 'Synthetic network failure for audit.' );
		}
		return $pre;
	};

	try {
		$_SERVER['HTTP_AUTHORIZATION'] = $synthetic_auth;
		$_POST['aeg_audit_field']      = 'synthetic-value';
		add_filter( 'akismet_request_args', $stop_after_capture, PHP_INT_MAX, 2 );
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
		remove_filter( 'akismet_request_args', $stop_after_capture, PHP_INT_MAX );

		// Override the option only in this request, so the HTTPS path is tested
		// even if a prior connection error disabled it on the site.
		add_filter( 'option_akismet_ssl_disabled', '__return_false' );
		add_filter( 'pre_http_request', $block_network, PHP_INT_MAX, 3 );
		Akismet::http_post( 'audit=synthetic', 'comment-check' );
	} catch ( Throwable $exception ) {
		$error = 'The probe stopped before completing.';
	} finally {
		remove_filter( 'akismet_request_args', $stop_after_capture, PHP_INT_MAX );
		remove_filter( 'pre_http_request', $block_network, PHP_INT_MAX );
		remove_filter( 'option_akismet_ssl_disabled', '__return_false' );
		remove_filter( 'akismet_debug_log', '__return_false' );
		$_POST = $previous_post;
		if ( null === $previous_auth ) {
			unset( $_SERVER['HTTP_AUTHORIZATION'] );
		} else {
			$_SERVER['HTTP_AUTHORIZATION'] = $previous_auth;
		}
	}

	nocache_headers();
	wp_send_json( array(
		'fields' => $fields,
		'blocked_outbound_schemes' => $calls,
		'error' => $error,
	), $error ? 500 : 200 );
}, 20 );
