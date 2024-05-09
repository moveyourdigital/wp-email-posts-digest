<?php
/**
 * Schedules cron for sending emails
 *
 * @package Email_Posts_Digest
 */

namespace Email_Posts_Digest;

/**
 * Ensures the daily schedule exists and wasn't removed.
 */
add_filter(
	'cron_schedules',
	function ( $schedules ) {
		if ( ! array_key_exists( 'daily', $schedules ) ) {
			$schedules['daily'] = array(
				'interval' => DAY_IN_SECONDS,
				'display'  => __( 'Once Daily' ),
			);
		}

		return $schedules;
	},
	PHP_INT_MAX,
);

/**
 * Schedule action for digest
 *
 * Verifies daily if there are enough new
 * posts to generate a new digest.
 */
add_action(
	'admin_init',
	function () {
		add_action(
			schedule_hook(),
			__NAMESPACE__ . '\\check_and_send_digest'
		);

		if ( ! wp_next_scheduled( schedule_hook() ) ) {
			wp_schedule_event( time(), 'daily', schedule_hook() );
		}
	}
);
