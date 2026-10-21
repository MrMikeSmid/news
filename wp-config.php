<?php
/**
 * WordPress configuration for the SQLite Database Integration drop-in.
 * Authentication salts are generated once and kept outside Git.
 */

define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', '' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'DB_ENGINE', 'sqlite' );

// Set this to an absolute path outside the web root when the host provides one.
$configured_db_path = getenv( 'WORDPRESS_SQLITE_DB_PATH' );
if ( is_string( $configured_db_path ) && '' !== $configured_db_path ) {
	define( 'DB_PATH', $configured_db_path );
}
unset( $configured_db_path );

$secret_dir = __DIR__ . '/wp-content/database';
if ( ! is_dir( $secret_dir ) && ! mkdir( $secret_dir, 0700, true ) && ! is_dir( $secret_dir ) ) {
	throw new RuntimeException( 'Cannot create the private WordPress data directory.' );
}

// Apache protection is installed before either secrets or the database are created.
foreach ( array( '.htaccess' => "Require all denied\n", 'index.php' => "<?php // Silence is golden.\n" ) as $name => $contents ) {
	$protection_file = $secret_dir . '/' . $name;
	if ( ! is_file( $protection_file ) && false === file_put_contents( $protection_file, $contents ) ) {
		throw new RuntimeException( 'Cannot protect the private WordPress data directory.' );
	}
}
unset( $name, $contents, $protection_file );

$secret_file = $secret_dir . '/wp-salts.php';
$lock_file = fopen( $secret_dir . '/.wp-salts.lock', 'c' );
if ( false === $lock_file || ! flock( $lock_file, LOCK_EX ) ) {
	throw new RuntimeException( 'Cannot lock WordPress secret generation.' );
}

$secret_names = array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' );
if ( ! is_file( $secret_file ) ) {
	$generated_secrets = array();
	foreach ( $secret_names as $secret_name ) {
		$generated_secrets[ $secret_name ] = bin2hex( random_bytes( 32 ) );
	}
	$temporary_file = tempnam( $secret_dir, '.salts-' );
	if ( false === $temporary_file ) {
		throw new RuntimeException( 'Cannot create WordPress secrets.' );
	}
	$secret_source = "<?php\nreturn " . var_export( $generated_secrets, true ) . ";\n";
	if ( false === file_put_contents( $temporary_file, $secret_source ) || ! chmod( $temporary_file, 0600 ) || ! rename( $temporary_file, $secret_file ) ) {
		@unlink( $temporary_file );
		throw new RuntimeException( 'Cannot save WordPress secrets.' );
	}
	unset( $generated_secrets, $temporary_file, $secret_source );
}

$saved_secrets = require $secret_file;
foreach ( $secret_names as $secret_name ) {
	if ( ! isset( $saved_secrets[ $secret_name ] ) || ! is_string( $saved_secrets[ $secret_name ] ) ) {
		throw new RuntimeException( 'WordPress secrets are invalid.' );
	}
	define( $secret_name, $saved_secrets[ $secret_name ] );
}
flock( $lock_file, LOCK_UN );
fclose( $lock_file );
unset( $secret_dir, $secret_file, $lock_file, $secret_names, $secret_name, $saved_secrets );

$table_prefix = 'wp_';
define( 'WP_DEBUG', false );
define( 'DISALLOW_FILE_EDIT', true );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
