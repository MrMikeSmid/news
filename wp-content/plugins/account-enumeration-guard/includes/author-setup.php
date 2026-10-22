<?php
/** Author privacy setup and public route protection. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Prevent author ID redirects and, optionally, all author archives. */
function aeg_protect_author_routes() {
	if ( ! isset( $_GET['author'] ) && ( ! get_option( 'aeg_block_author_archives', false ) || ! is_author() ) ) {
		return;
	}

	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	$template = get_404_template();
	if ( $template ) {
		include $template;
	} else {
		wp_die( esc_html__( 'Page not found.', 'account-enumeration-guard' ), '', array( 'response' => 404 ) );
	}
	exit;
}
add_action( 'template_redirect', 'aeg_protect_author_routes', 0 );

/** Remove author sitemaps when author archives have been disabled. */
function aeg_filter_author_sitemap( $provider, $name ) {
	if ( 'users' === $name && get_option( 'aeg_block_author_archives', false ) ) {
		return false;
	}
	return $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'aeg_filter_author_sitemap', 10, 2 );

/** Add the guided setup beneath Users. */
function aeg_register_setup_page() {
	add_users_page(
		__( 'Author privacy setup', 'account-enumeration-guard' ),
		__( 'Author privacy', 'account-enumeration-guard' ),
		'manage_options',
		'aeg-author-setup',
		'aeg_render_setup_page'
	);
}
add_action( 'admin_menu', 'aeg_register_setup_page' );

/** Point administrators to setup after activation. */
function aeg_setup_notice() {
	if ( get_option( 'aeg_setup_completed', false ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="notice notice-info"><p>';
	echo esc_html__( 'Account Enumeration Guard: review public author names and routes in the setup guide.', 'account-enumeration-guard' ) . ' ';
	echo '<a href="' . esc_url( admin_url( 'users.php?page=aeg-author-setup' ) ) . '">' . esc_html__( 'Open author privacy setup', 'account-enumeration-guard' ) . '</a>';
	echo '</p></div>';
}
add_action( 'admin_notices', 'aeg_setup_notice' );

/** Process one setup action per request, then redirect to avoid duplicate saves. */
function aeg_handle_setup_post() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_GET['page'] ) || 'aeg-author-setup' !== $_GET['page'] ) {
		return;
	}
	if ( ! isset( $_POST['aeg_action'] ) || ! is_string( $_POST['aeg_action'] ) ) {
		return;
	}
	$action = sanitize_key( wp_unslash( $_POST['aeg_action'] ) );
	if ( ! in_array( $action, array( 'save_protection', 'save_author', 'finish_setup' ), true ) ) {
		return;
	}
	if ( ! isset( $_POST['aeg_nonce'] ) || ! is_string( $_POST['aeg_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aeg_nonce'] ) ), 'aeg_setup' ) ) {
		wp_die( esc_html__( 'Invalid setup request.', 'account-enumeration-guard' ), '', array( 'response' => 403 ) );
	}

	$message = 'saved';
	if ( 'save_protection' === $action ) {
		update_option( 'aeg_block_author_archives', isset( $_POST['block_author_archives'] ) );
	} elseif ( 'finish_setup' === $action ) {
		if ( isset( $_POST['confirm_review'] ) ) {
			update_option( 'aeg_setup_completed', true );
		} else {
			$message = 'invalid';
		}
	} else {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$user    = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user || ! isset( $_POST['public_name'], $_POST['author_slug'] ) || ! is_string( $_POST['public_name'] ) || ! is_string( $_POST['author_slug'] ) ) {
			$message = 'invalid';
		} else {
			$name = sanitize_text_field( wp_unslash( $_POST['public_name'] ) );
			$slug = sanitize_title( sanitize_user( wp_unslash( $_POST['author_slug'] ), true ) );
			$other_user = $slug ? get_user_by( 'slug', $slug ) : false;
			if ( '' === $name || '' === $slug || strlen( $slug ) > 50 || 0 === strcasecmp( $name, $user->user_login ) || 0 === strcasecmp( $slug, sanitize_title( $user->user_login ) ) || ( $other_user && (int) $other_user->ID !== $user_id ) ) {
				$message = 'invalid';
			} else {
				$result = wp_update_user( array(
					'ID'            => $user_id,
					'display_name'  => $name,
					'user_nicename' => $slug,
				) );
				if ( is_wp_error( $result ) ) {
					$message = 'invalid';
				}
			}
		}
	}

	$page = isset( $_POST['aeg_page'] ) ? max( 1, absint( $_POST['aeg_page'] ) ) : 1;
	wp_safe_redirect( add_query_arg( array( 'page' => 'aeg-author-setup', 'paged' => $page, 'aeg_message' => $message ), admin_url( 'users.php' ) ) );
	exit;
}
add_action( 'admin_init', 'aeg_handle_setup_post' );

/** Flag names that are identical to the login or its default author slug. */
function aeg_author_needs_review( $user ) {
	return 0 === strcasecmp( $user->display_name, $user->user_login )
		|| 0 === strcasecmp( $user->user_nicename, sanitize_title( $user->user_login ) );
}

/** Render the guided account review. */
function aeg_render_setup_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$query = new WP_User_Query( array(
		'number'  => 20,
		'paged'   => $paged,
		'orderby' => 'ID',
		'order'   => 'ASC',
	) );
	$users = $query->get_results();
	$pages = (int) ceil( $query->get_total() / 20 );
	$blocked = (bool) get_option( 'aeg_block_author_archives', false );
	echo '<div class="wrap"><h1>' . esc_html__( 'Author privacy setup', 'account-enumeration-guard' ) . '</h1>';
	echo '<p>' . esc_html__( 'Review public author identities, then choose whether to disable author archives. Changing a display name does not change the login name or the author URL.', 'account-enumeration-guard' ) . '</p>';
	if ( isset( $_GET['aeg_message'] ) && is_string( $_GET['aeg_message'] ) ) {
		$valid = 'saved' === sanitize_key( wp_unslash( $_GET['aeg_message'] ) );
		echo '<div class="notice ' . ( $valid ? 'notice-success' : 'notice-error' ) . ' is-dismissible"><p>' . esc_html( $valid ? __( 'Settings saved.', 'account-enumeration-guard' ) : __( 'Nothing was changed. Enter a distinct public name and author slug; the slug must be unique and no longer than 50 characters.', 'account-enumeration-guard' ) ) . '</p></div>';
	}
	echo '<h2>' . esc_html__( '1. Protect author routes', 'account-enumeration-guard' ) . '</h2>';
	echo '<p>' . esc_html__( 'Numeric author lookup (?author=1) returns 404. You can also make all author archive pages return 404. This may break existing author links.', 'account-enumeration-guard' ) . '</p>';
	echo '<form method="post" action=""><input type="hidden" name="aeg_action" value="save_protection"><input type="hidden" name="aeg_page" value="' . esc_attr( $paged ) . '">';
	wp_nonce_field( 'aeg_setup', 'aeg_nonce' );
	echo '<label><input type="checkbox" name="block_author_archives" value="1" ' . checked( $blocked, true, false ) . '> ' . esc_html__( 'Disable public author archives and the WordPress user sitemap', 'account-enumeration-guard' ) . '</label>';
	submit_button( __( 'Save route protection', 'account-enumeration-guard' ) );
	echo '</form><h2>' . esc_html__( '2. Review public identities', 'account-enumeration-guard' ) . '</h2>';
	echo '<p>' . esc_html__( 'Choose a public name and author slug that do not reveal the login name. Existing author links may stop working after a slug change. Repeat this review when new authors are added.', 'account-enumeration-guard' ) . '</p>';
	foreach ( $users as $user ) {
		echo '<div class="card" style="max-width:700px"><h3>' . esc_html( $user->user_login ) . ' '; // Admin-only diagnostic.
		if ( aeg_author_needs_review( $user ) ) {
			echo '<span class="notice-inline" style="color:#b32d2e">' . esc_html__( '(review recommended)', 'account-enumeration-guard' ) . '</span>';
		} else {
			echo '<span style="color:#008a20">' . esc_html__( '(public names differ)', 'account-enumeration-guard' ) . '</span>';
		}
		echo '</h3><form method="post" action=""><input type="hidden" name="aeg_action" value="save_author"><input type="hidden" name="aeg_page" value="' . esc_attr( $paged ) . '"><input type="hidden" name="user_id" value="' . esc_attr( $user->ID ) . '">';
		wp_nonce_field( 'aeg_setup', 'aeg_nonce' );
		echo '<p><label>' . esc_html__( 'Public display name', 'account-enumeration-guard' ) . '<br><input class="regular-text" required name="public_name" value="' . esc_attr( $user->display_name ) . '"></label></p>';
		echo '<p><label>' . esc_html__( 'Author URL slug', 'account-enumeration-guard' ) . '<br><input class="regular-text" required maxlength="50" name="author_slug" value="' . esc_attr( $user->user_nicename ) . '"></label></p>';
		echo '<p>' . esc_html__( 'Current author URL:', 'account-enumeration-guard' ) . ' <code>' . esc_html( get_author_posts_url( $user->ID, $user->user_nicename ) ) . '</code></p>';
		submit_button( __( 'Save public identity', 'account-enumeration-guard' ), 'secondary', 'submit', false );
		echo '</form></div>';
	}
	if ( $pages > 1 ) {
		echo '<p class="tablenav-pages">';
		for ( $page = 1; $page <= $pages; $page++ ) {
			echo ' <a class="button ' . ( $page === $paged ? 'button-primary' : '' ) . '" href="' . esc_url( add_query_arg( array( 'page' => 'aeg-author-setup', 'paged' => $page ), admin_url( 'users.php' ) ) ) . '">' . esc_html( $page ) . '</a>';
		}
		echo '</p>';
	}
	echo '<h2>' . esc_html__( '3. Check the public result', 'account-enumeration-guard' ) . '</h2>';
	echo '<p>' . esc_html__( 'Open the site in a private browser window. Check author links, posts, feeds and any other plugins that publish user data. This tool cannot erase names already indexed or shown elsewhere.', 'account-enumeration-guard' ) . '</p>';
	echo '<form method="post" action=""><input type="hidden" name="aeg_action" value="finish_setup"><input type="hidden" name="aeg_page" value="' . esc_attr( $paged ) . '">';
	wp_nonce_field( 'aeg_setup', 'aeg_nonce' );
	echo '<label><input type="checkbox" name="confirm_review" value="1" required> ' . esc_html__( 'I have reviewed the public names and checked the site.', 'account-enumeration-guard' ) . '</label>';
	submit_button( __( 'Finish setup', 'account-enumeration-guard' ) );
	echo '</form></div>';
}
