<?php
/**
 * Digest mechanism
 *
 * @package Email_Posts_Digest
 */

namespace Email_Posts_Digest;

/**
 * Generate the general content for a digest
 *
 * @param string $action Either 'email' or 'archive'.
 * @param string $date When action is 'archive', a date must be passed.
 * @return string the generated content
 * @since 0.1.0
 */
function get_digest_query( $action, $date = null ) {
	$posts_per_page = (int) get_option( 'posts_per_email_digest', 10 );

	switch ( $action ) {
		case 'email':
			$since_date = get_option( 'email-posts-digest_activation_date' );

			if ( ! $since_date ) {
				$since_date = current_time( 'mysql' );
				update_option( 'email-posts-digest_activation_date', $since_date, false );
			}

			$query = array(
				'date_query'     => array(
					'after'     => $since_date,
					'inclusive' => true,
				),
				// phpcs:ignore
				'meta_query' => array(
					array(
						'key'     => '_email_post_digest_date',
						'value'   => 'bug #23268',
						'compare' => 'NOT EXISTS',
					),
				),
				'posts_per_page' => $posts_per_page,
			);
			break;

		case 'archive':
			if ( ! $date ) {
				return;
			}

			$query = array(
				// phpcs:ignore
				'meta_query' => array(
					array(
						'key'     => '_email_post_digest_date',
						'value'   => esc_sql( $date ),
						'compare' => '=',
						'type'    => 'DATE',
					),
				),
			);
			break;

		default:
			return;
	}

	$query = new \WP_Query( $query );

	// check if there are posts available to generate a digest.
	if ( $query->post_count < $posts_per_page ) {
		return;
	}

	return $query;
}

/**
 * Save post to digest
 *
 * @param int    $post_id The post ID.
 * @param string $date The date.
 */
function save_digest_post( $post_id, $date ) {
	update_post_meta( $post_id, '_email_post_digest_date', $date );
}

/**
 * Renders Digest from Query
 *
 * @param \WP_Query $query The query.
 * @param string    $action Either 'email' or 'archive'.
 *
 * @return string
 */
function render_digest( \WP_Query $query, $action ) {
	// the posts contents already rendered.
	$posts_content = array();

	while ( $query->have_posts() ) {
		global $post;

		$query->the_post();
		$post_content = sprintf( "%s\n%s\n%s\n\n", get_the_title(), get_the_modified_date(), get_the_excerpt() );

		/**
		 * Filters the contents of each post on an email digest.
		 *
		 * @since 0.1.0
		 *
		 * @param string $post_content Default post content
		 * @param WP_Post $post The post object.
		 * @param WP_Query $query The query object.
		 */
		$posts_content[] = apply_filters( 'digest_email_post_content', $post_content, $post, $query );

		// save metadata email_post_digest_date!
		wp_reset_postdata();
	}

	// * translators: Do not translate USERNAME, ADMIN_EMAIL, NEW_EMAIL, EMAIL, SITENAME, POSTS_DIGEST, UNSUBSCRIBE, SITEURL: those are placeholders. */
	$digest_content = __(
		'Hi ###USERNAME###,

Your latest digest of updates from ###SITENAME###:

###POSTS_DIGEST###

To manage your email preferences or unsubscribe from these updates, please follow the link below.
###UNSUBSCRIBE###

Best regards,
All at ###SITENAME###
###SITEURL###
'
	);

	/**
	 * Filters the general contents of this digest.
	 *
	 * @since 0.1.0
	 *
	 * @param string $content The template content.
	 * @param string $action Whether sending an email or another location.
	 * @param array $posts_content The posts to be included.
	 */
	$digest_content = apply_filters( 'digest_email_template', $digest_content, $action, $posts_content );

	$digest_content = str_replace( '###ADMIN_EMAIL###', get_option( 'admin_email' ), $digest_content );
	$digest_content = str_replace( '###SITENAME###', get_option( 'blogname' ), $digest_content );
	$digest_content = str_replace( '###SITEURL###', home_url(), $digest_content );
	$digest_content = str_replace( '###POSTS_DIGEST###', implode( "\n", $posts_content ), $digest_content );

	return $digest_content;
}

/**
 * Creates a new unsubscribe URL
 *
 * @param int $user_id To create.
 * @return string
 */
function get_unsubscribe_url( $user_id ) {
	$meta_key = '_email_posts_digest_unsubscribe_token';
	$token    = get_user_meta( $user_id, $meta_key, true );

	if ( ! $token ) {
		$token = wp_generate_password( 16, false, false );
		update_user_meta( $user_id, $meta_key, $token );
	}

	return site_url( 'wp-login.php?action=unsubscribe&token=' . $token . '&user=' . $user_id, 'login' );
}

/**
 * Checks the provided input unsubscribe token
 *
 * @param int    $user_id user.
 * @param string $token the provided token.
 * @return boolean
 */
function check_unsubscribe_token( $user_id, $token ) {
	$hash = get_user_meta( $user_id, '_email_posts_digest_unsubscribe_token', true );
	return $token === $hash;
}

/**
 * Checks if user is subscribed
 *
 * @param int $user_id the user.
 * @return boolean
 */
function user_is_subscribed( $user_id ) {
	return (bool) get_user_meta( $user_id, '_email_posts_digest_enabled', true );
}

/**
 * Checks and sends a post email digest
 * This is used in the cron.
 *
 * @since 0.1.0
 */
function check_and_send_digest() {
	$wp_digest_query = get_digest_query( 'email' );

	if ( ! $wp_digest_query ) {
		return;
	}

	$digest_content = render_digest( $wp_digest_query, 'email' );

	if ( ! $digest_content || empty( $digest_content ) ) {
		return;
	}

	$subscribed_users = get_users(
		array(
			// phpcs:ignore
			'meta_key' => '_email_posts_digest_enabled',
		)
	);

	foreach ( $wp_digest_query->posts as $post ) {
		save_digest_post( $post->ID, current_time( 'mysql' ) );
	}

	foreach ( $subscribed_users as $user ) {
		// create default template
		// pass to filter (string $default_template, WP_User $user).

		$email = array(
			'to'      => $user->user_email,
			/* translators: Posts digest subject. %s: Site title. */
			'subject' => __( '[%s] Posts Digest', 'email-posts-digest' ),
			'message' => $digest_content,
			'headers' => '',
		);

		/**
		 * Filters the contents of the email sent for each user when a new posts digest is issued.
		 *
		 * @since 0.1.0
		 *
		 * @param array $email {
		 *     Used to build wp_mail().
		 *
		 *     @type string $to      The intended recipients.
		 *     @type string $subject The subject of the email.
		 *     @type string $message The content of the email.
		 *         The following strings have a special meaning and will get replaced dynamically:
		 *         - ###USERNAME###     The current user's username.
		 *         - ###EMAIL###        The current user's email.
		 *         - ###UNSUBSCRIBE###  The unsubscription link.
		 *     @type string $headers Headers.
		 * }
		 * @param array $user The user object.
		 */
		$email = apply_filters( 'digest_email_template_user', $email, $user );

		$blog_name = get_bloginfo( 'name' );

		$email['message'] = str_replace( '###USERNAME###', $user->user_login, $email['message'] );
		$email['message'] = str_replace( '###EMAIL###', $user->user_email, $email['message'] );
		$email['message'] = str_replace( '###UNSUBSCRIBE###', get_unsubscribe_url( $user->ID ), $email['message'] );

		wp_mail( $email['to'], sprintf( $email['subject'], $blog_name ), $email['message'], $email['headers'] );
	}
}
