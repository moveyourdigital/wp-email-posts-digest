<?php
/**
 * WordPress Admin UI
 *
 * @package Email_Posts_Digest
 */

namespace Email_Posts_Digest;

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'options-reading.php' === $hook ) {
			wp_register_style( 'admin-email-posts-digest', plugin_uri( '/css/admin-email-posts-digest.css' ), false, plugin_version() );
			wp_enqueue_script( 'admin-email-posts-digest', plugin_uri( '/js/admin-email-posts-digest.js' ), array( 'jquery' ), plugin_version(), true );
			wp_enqueue_style( 'admin-email-posts-digest' );
		}
	}
);

/**
 * Include help tab in settings
 *
 * @package Email_Posts_Digest
 */
add_action(
	'admin_head',
	function () {
		$current_screen = get_current_screen();

		if ( 'options-reading' === $current_screen->id ) {
			get_current_screen()->add_help_tab(
				array(
					'id'      => 'email-posts-digest',
					'title'   => __( 'Email digest settings' ),
					'content' => '<p>' . __( 'Configure the threshold for triggering digest emails below. Once the number of new posts on your WordPress site exceeds this threshold, a digest email will be automatically sent to all registered users.' ) . '</p>' .
					'<p><b>' . __( 'Number of New Posts to Trigger Digest' ) . '</b></p>' .
					'<p>' . __( 'Specify the minimum number of new posts required to trigger a digest email. When the number of new posts reaches or exceeds this value, a digest email will be generated and sent to all registered users.' ) . '</p>' .
					'<p><i>' . __( 'Note: Increasing this value may result in less frequent digest emails, while decreasing it may lead to more frequent updates for your users.' ) . '</i></p>',
				)
			);
		}
	}
);

/**
 * Admin settings in options-reading.php panel
 *
 * @package Email_Posts_Digest
 */
add_action(
	'admin_init',
	function () {
		if ( isset( $_POST['permalink_structure'] )
		&& isset( $_POST['posts_email_digest_permalink_base'] ) ) {
			check_admin_referer( 'update-permalink' );

			$value = wp_kses_data( wp_unslash( $_POST['posts_email_digest_permalink_base'] ) );
			update_option( 'posts_email_digest_permalink_base', esc_sql( $value ) );
		}

		register_setting(
			'reading',
			'posts_per_email_digest',
			array(
				'type'         => 'integer',
				'show_in_rest' => array(
					'name'   => 'posts_per_email_digest',
					'schema' => array(
						'type' => 'integer',
					),
				),
			)
		);

		add_settings_field(
			'posts_per_email_digest',
			__( 'Email digests show the most recent', 'email-posts-digest' ),
			function () {
				?>
	<input name="posts_per_email_digest" type="number" step="1" min="1" id="posts_per_email_digest" value="<?php echo esc_html( get_option( 'posts_per_email_digest', 10 ) ); ?>" class="small-text" />
				<?php esc_html_e( 'posts' ); ?>
				<?php
			},
			'reading',
			'default',
			array(
				'label_for' => 'posts_per_email_digest',
			)
		);

		register_setting(
			'reading',
			'posts_email_digest_browser_view_enabled',
			array(
				'type'         => 'boolean',
				'show_in_rest' => array(
					'name'   => 'posts_email_digest_browser_view_enabled',
					'schema' => array(
						'type' => 'boolean',
					),
				),
			)
		);

		add_settings_field(
			'posts_email_digest_browser_view_enabled',
			__( 'Posts digest browser view', 'email-posts-digest' ),
			function () {
				?>
	<input name="posts_email_digest_browser_view_enabled" id="posts_email_digest_browser_view_enabled" type="checkbox" value="1" class="tog smtp-input" <?php echo checked( 1, get_option( 'posts_email_digest_browser_view_enabled' ), false ); ?> />
	<label for="posts_email_digest_browser_view_enabled"><?php esc_html_e( 'Enable browser view, templating and statistics.', 'email-posts-digest' ); ?></label>
				<?php
			},
			'reading',
			'default',
			array(
				'label_for' => 'posts_email_digest_browser_view_enabled',
			)
		);

		register_setting(
			'permalink',
			'posts_email_digest_permalink_base',
		);

		add_settings_field(
			'posts_email_digest_permalink_base',
			__( 'Posts digest base', 'email-posts-digest' ),
			function () {
				?>
	<input name="posts_email_digest_permalink_base" type="text" id="posts_email_digest_permalink_base" value="<?php echo esc_attr( get_option( 'posts_email_digest_permalink_base' ) ); ?>" class="regular-text code" />
				<?php
			},
			'permalink',
			'optional',
			array(
				'label_for' => 'posts_email_digest_permalink_base',
			)
		);

		flush_rewrite_rules();
	}
);

/**
 * Show custom user profile fields
 *
 * @param  object $profileuser A WP_User object.
 * @return void
 */
function user_profile_fields( $profileuser ) {
	?>
	<div style="height: 20px;"></div>
	<h2><?php esc_html_e( 'Digest subscriptions', 'email-posts-digest' ); ?></h2>
	<table class="form-table">
		<tr>
			<th>
				<?php esc_html_e( 'Subscription' ); ?>
			</th>
			<td>
				<label for="posts_digest_subscribed">
					<input name="posts_digest_subscribed" type="checkbox" id="posts_digest_subscribed" value="1" <?php echo checked( user_is_subscribed( $profileuser->ID ) ); ?>>
					<?php esc_html_e( 'Receive posts digest regularly via e-mail.', 'email-posts-digest' ); ?>
				</label>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', __NAMESPACE__ . '\\user_profile_fields' );
add_action( 'edit_user_profile', __NAMESPACE__ . '\\user_profile_fields' );

/**
 * Update fields
 *
 * @param string $user_id User ID.
 */
function user_profile_fields_update( $user_id ) {
	check_admin_referer( 'update-user_' . $user_id );
	update_user_meta( $user_id, '_email_posts_digest_enabled', isset( $_POST['posts_digest_subscribed'] ) );
}

add_action( 'personal_options_update', __NAMESPACE__ . '\\user_profile_fields_update' );
add_action( 'edit_user_profile_update', __NAMESPACE__ . '\\user_profile_fields_update' );
