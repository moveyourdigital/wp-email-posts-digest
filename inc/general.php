<?php
/**
 * General templating and frontend API.
 *
 * @package Email_Posts_Digest
 */

namespace Email_Posts_Digest;

add_action(
	'init',
	function () {
		if ( get_option( 'posts_email_digest_browser_view_enabled' ) ) {
			$base_url = get_option( 'posts_email_digest_permalink_base', 'posts-digest' );

			add_rewrite_rule(
				untrailingslashit( $base_url ) . '/([a-z0-9-]+)[/]?$',
				'index.php?email_posts_digest=$matches[1]',
				'top'
			);
		}
	}
);

add_filter(
	'query_vars',
	function ( $query_vars ) {
		if ( get_option( 'posts_email_digest_browser_view_enabled' ) ) {
			$query_vars[] = 'email_posts_digest';
		}
		return $query_vars;
	}
);

/**
 * Returns 404
 *
 * @return string
 */
function get_404() {
	global $wp_query;

	$wp_query->set_404();
	$wp_query->post_count = 0;

	status_header( 404 );

	return get_404_template();
}

add_filter(
	'template_include',
	function ( $template ) {
		if ( ! get_option( 'posts_email_digest_browser_view_enabled' ) ) {
			return $template;
		}

		$query_var = get_query_var( 'email_posts_digest' );

		if ( ! $query_var ) {
			return $template;
		}

		if ( strtotime( $query_var ) === false ) {
			return get_404();
		}

		if ( false !== $query_var && '' !== $query_var ) {
			if ( locate_template( 'digest-posts.php' ) !== '' ) {
				get_template_part( 'digest', 'posts' );
			} else {
				$wp_digest_query = get_digest_query( 'archive', esc_sql( $query_var ) );

				if ( ! $wp_digest_query ) {
					return get_404();
				}

				// phpcs:ignore
				echo render_digest( $wp_digest_query, 'archive' );
			}

			return;
		}

		return $template;
	}
);

add_action(
	'login_form_unsubscribe',
	function () {

		$user  = isset( $_GET['user'] ) ? sanitize_key( $_GET['user'] ) : '';
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		$errors = new \WP_Error();

		if ( ! $user || ! $token ) {
			$errors->add( 'no-token', __( 'No token or user ID provided.' ) );
		}

		$this_link = get_unsubscribe_url( $user );

		if ( ! check_unsubscribe_token( $user, $token ) ) {
			$errors->add( 'invalid-token', __( 'The provided token has expired.' ) );
		}

		if ( $errors->has_errors() ) {
			// phpcs:ignore
			wp_die( $errors );
		}

		$message = '';

		if ( isset( $_POST['update_post_digest_subscribe'] ) ) {
			check_admin_referer( 'confirm_subscriptions', 'confirm_subscriptions_nonce' );
			update_user_meta( $user, '_email_posts_digest_enabled', isset( $_POST['posts_digest_subscribed'] ) );

			$message = '<div class="update-nag notice notice-success">' . __( 'Digest preferences updated', 'email-posts-digest' ) . '</div>';
		}

		login_header( __( 'Subscriptions options.', 'email-posts-digest' ), $message, $errors );

		?>

		<form class="admin-email-confirm-form" name="admin-email-confirm-form" action="<?php echo esc_url( $this_link ); ?>" method="post">
			<?php

			wp_nonce_field( 'confirm_subscriptions', 'confirm_subscriptions_nonce' );

			?>
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />

			<h1 class="admin-email__heading">
				<?php esc_html_e( 'Subscriptions options', 'email-posts-digest' ); ?>
			</h1>

			<p class="admin-email__details">
				<?php esc_html_e( 'Please, tick or untick the following subscription preferences to update.', 'email-posts-digest' ); ?>
			</p>

			<p class="admin-email__details">
				<label for="posts_digest_subscribed">
					<input name="posts_digest_subscribed" type="checkbox" id="posts_digest_subscribed" value="1" <?php echo checked( user_is_subscribed( $user ) ); ?> />
				
					<?php esc_html_e( 'Receive posts digest regularly via e-mail.', 'email-posts-digest' ); ?>
				</label>
			</p>

			<div class="admin-email__actions">
				<div class="admin-email__actions-primary">
					<button type="submit" name="update_post_digest_subscribe" id="update_post_digest_subscribe" class="button button-primary button-large">
						<?php esc_attr_e( 'Update' ); ?>
					</button>
				</div>
			</div>
		</form>

		<?php

		login_footer();
		exit();
	}
);
