<?php
/**
 * WP-CLI command for SDCOM_Timestamps namespace.
 *
 * phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
 *
 * @since  1.8.0
 * @package SDCOM_Timestamps
 */

namespace SDCOM_Timestamps;

use WP_CLI_Command;
use WP_CLI;
use SDCOM_Timestamps\Command\Utility;

if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit; // Exit if accessed directly.
	// @codeCoverageIgnoreEnd
}

/**
 * CLI Commands for SDCOM_Timestamps namespace.
 */
class Command extends WP_CLI_Command {

	/**
	 * Holds temporary wp_actions when indexing with pagination.
	 *
	 * @since 1.8.0
	 * @var  array
	 */
	private $temporary_wp_actions = [];

	/**
	 * Holds CLI command position args.
	 *
	 * Useful to share arguments to methods called by hooks.
	 *
	 * @since 1.8.0
	 * @var array
	 */
	protected $args = [];

	/**
	 * Holds CLI command associative args.
	 *
	 * Useful to share arguments to methods called by hooks.
	 *
	 * @since 1.8.0
	 * @var array
	 */
	protected $assoc_args = [];

	/**
	 * Internal timer.
	 *
	 * @since 1.8.0
	 *
	 * @var float
	 */
	protected $time_start = null;

	/**
	 * Holds the WP CLI progress bar.
	 *
	 * @var \WP_CLI\Progress\Bar
	 */
	public $progress_bar = null;

	/**
	 * Create Command.
	 *
	 * @since  1.8.0
	 */
	public function __construct() {}

	/**
	 * Generate timestamps for posts.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Force the generation of timestamps without confirmation.
	 *
	 * [--post_type=<post_types>]
	 * : Specify which post types will be used in the command (by default: only the `post` post type is used). For example, `--post_type="my_custom_post_type"` would limit to only posts from the post type "my_custom_post_type". Accepts multiple post types separated by comma.
	 *
	 * [--post_ids=<IDs>]
	 * : Choose which post IDs to include in the command. For example, `--post_ids="1,2,3"` would limit to only posts with the IDs 1, 2, and 3. Accepts multiple post IDs separated by comma.
	 *
	 * [--post_status=<IDs>]
	 * : Choose which post statuses to include in the command. For example, `--post_status="publish,draft"` would limit to only posts with the post status "publish" and "draft". Accepts multiple post statuses separated by comma.
	 *
	 * @since 1.8.0
	 *
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 *
	 * @subcommand generate
	 */
	public function generate( $args, $assoc_args ) {
		$post_type    = \WP_CLI\Utils\get_flag_value( $assoc_args, 'post_type', array( 'post' ) );
		$post_ids     = \WP_CLI\Utils\get_flag_value( $assoc_args, 'post_ids', array() );
		$post_status  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'post_status', array( 'publish' ) );
		$force_option = \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );

		if ( ! $force_option && empty( $post_ids ) ) {
			WP_CLI::confirm( esc_html__( 'Are you sure you want to generate timestamps for all posts?', 'timestamps' ), $assoc_args );
		}

		/**
		 * Fires before starting the command.
		 *
		 * Useful for deregistering filters/actions that occur during a query request.
		 *
		 * @hook timestamps_wp_cli_pre_generate
		 * @param {array} $args Positional CLI args.
		 * @param {array} $assoc_args Associative CLI args.
		 *
		 * @since 1.8.0
		 */
		do_action( 'timestamps_wp_cli_pre_generate', $args, $assoc_args );

		Utility::timer_start();

		// Defaults.
		$index_args = array(
			'post_type'   => $post_type,
			'post_status' => $post_status,
		);

		// Post type.
		if ( ! empty( $assoc_args['post_type'] ) ) {
			$post_type = explode( ',', $assoc_args['post_type'] );
			$post_type = array_map( 'trim', $post_type );

			$index_args['post_type'] = $post_type;
		}

		// Post status.
		if ( ! empty( $assoc_args['post_status'] ) ) {
			$post_status = explode( ',', $assoc_args['post_status'] );
			$post_status = array_map( 'trim', $post_status );

			$index_args['post_status'] = $post_status;
		}

		// Post IDs.
		if ( ! empty( $assoc_args['post_ids'] ) ) {
			$post_ids = explode( ',', str_replace( ' ', '', $assoc_args['post_ids'] ) );
			$post_ids = array_map( 'absint', $post_ids );

			$index_args['post__in'] = $post_ids;
		}

		WP_CLI::line();

		$query = new \WP_Query( $index_args );

		$found_posts = $query->found_posts;

		// Progress bar.
		$this->progress_bar = WP_CLI\Utils\make_progress_bar(
			sprintf( __( 'Generating timestamps for %d objects:', 'timestamps' ), $found_posts ),
			$found_posts
		);

		// Loop through the posts in the query.
		while ( $query->have_posts() ) {
			$query->the_post();

			// Generate timestamp for the post.
			\SDCOM_Timestamps\TimestampHelper::factory()->generate_timestamp_for_post();

			// Increment the progress bar.
			$this->progress_bar->tick();

            usleep( 500 );

            // Avoid running out of memory.
            $this->stop_the_insanity();
		}

		$sync_time_in_ms = Utility::timer_stop();

		/**
		 * Fires after executing the command.
		 *
		 * @hook timestamps_wp_cli_after_generate
		 * @param {array} $args Positional CLI args.
		 * @param {array} $assoc_args Associative CLI args.
		 *
		 * @since 1.8.0
		 */
		do_action( 'timestamps_wp_cli_after_generate', $args, $assoc_args );

		WP_CLI::log( WP_CLI::colorize( '%Y' . esc_html__( 'Total time elapsed: ', 'timestamps' ) . '%N' . Utility::timer_format( $sync_time_in_ms ) ) );

		WP_CLI::success( esc_html__( 'Done!', 'timestamps' ) );
	}

	/**
	 * Resets some values to reduce memory footprint.
     * 
     * @since 1.8.0
	 */
	protected function stop_the_insanity() {
		global $wpdb, $wp_object_cache, $wp_actions;

		$wpdb->queries = [];

		/*
		 * Runtime flushing was introduced in WordPress 6.0 and will flush only the
		 * in-memory cache for persistent object caches.
		 */
		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		} else {

			/*
			 * In the case where we're not using an external object cache, we need to call flush on the default
			 * WordPress object cache class to clear the values from the cache property.
			 */
			if ( ! wp_using_ext_object_cache() ) {
				wp_cache_flush();
			}
		}

		if ( is_object( $wp_object_cache ) ) {
			$wp_object_cache->group_ops      = [];
			$wp_object_cache->stats          = [];
			$wp_object_cache->memcache_debug = [];

			// Make sure this is a public property, before trying to clear it.
			try {
				$cache_property = new \ReflectionProperty( $wp_object_cache, 'cache' );
				if ( $cache_property->isPublic() ) {
					$wp_object_cache->cache = [];
				}
				unset( $cache_property );
			} catch ( \ReflectionException $e ) {
				// No need to catch.
			}

			if ( is_callable( $wp_object_cache, '__remoteset' ) ) {
				call_user_func( [ $wp_object_cache, '__remoteset' ] );
			}
		}

		// Prevent wp_actions from growing out of control.
		// phpcs:disable
		$wp_actions = $this->temporary_wp_actions;
		// phpcs:enable

        /*
         * It's high memory consuming as WP_Query instance holds all query results inside itself
         * and in theory $wp_filter will not stop growing until Out Of Memory exception occurs.
         */
		remove_filter( 'get_term_metadata', [ wp_metadata_lazyloader(), 'lazyload_term_meta' ] );

		/**
		 * Fires after reducing the memory footprint.
		 *
		 * @since 1.8.0
		 * @hook timestamps_stop_the_insanity
		 */
		do_action( 'timestamps_stop_the_insanity' );
	}
}
