<?php
/**
 * Plugin Name:  LiteSpeed Cron Intervals
 * Description:  Custom run intervals for LiteSpeed Cache cron tasks. Survives LSCWP re-registration.
 * Version:      1.0.0
 * Requires PHP: 7.0
 * License:      GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

final class LSCCI {
	const OPT    = 'lscci_intervals';
	const PREFIX = 'lscci_every_';
	const MIN    = 30;

    // Per-hook minimum intervals (seconds). QUIC.cloud allows 1 optm request per 5 min.
	const HOOK_MIN = [
		'litespeed_task_imgoptm_req' => 300,
	];

	const HOOKS = [
		'litespeed_task_ucss'         => 'Unique CSS (UCSS)',
		'litespeed_task_ccss'         => 'Critical CSS (CCSS)',
		'litespeed_task_vpi'          => 'Viewport Images (VPI)',
		'litespeed_task_lqip'         => 'LQIP Placeholder',
		'litespeed_task_optimax'      => 'OptimaX',
		'litespeed_task_avatar'       => 'Gravatar Cache',
		'litespeed_task_imgoptm_req'  => 'Image Optm – Auto Request',
		'litespeed_task_imgoptm_pull' => 'Image Optm – Pull',
		'litespeed_task_crawler'      => 'Crawler',
		'litespeed_task_guest_sync'   => 'Guest Mode IP/UA Sync',
	];

	const PRESETS = [ 60, 120, 300, 600, 900, 1800, 3600, 21600, 43200, 86400 ];

	public static function boot() {
		add_filter( 'cron_schedules', [ __CLASS__, 'schedules' ] );
		add_filter( 'schedule_event', [ __CLASS__, 'override_event' ], 99 );
		add_action( 'admin_init', [ __CLASS__, 'sync' ], 99 );
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_post_lscci_save', [ __CLASS__, 'save' ] );
	}

    public static function min_for( $hook ) {
		return isset( self::HOOK_MIN[ $hook ] ) ? self::HOOK_MIN[ $hook ] : self::MIN;
	}

	public static function map() {
		$m = get_option( self::OPT, [] );
		if ( ! is_array( $m ) ) return [];
		$out = [];
		foreach ( array_intersect_key( array_map( 'absint', $m ), self::HOOKS ) as $hook => $sec ) {
			if ( $sec ) $out[ $hook ] = max( self::min_for( $hook ), $sec );
		}
		return $out;
	}

	public static function sname( $sec ) {
		return self::PREFIX . (int) $sec;
	}

	public static function human( $sec ) {
		if ( $sec % 3600 === 0 ) return ( $sec / 3600 ) . ' hour(s)';
		if ( $sec % 60 === 0 )   return ( $sec / 60 ) . ' min';
		return $sec . ' sec';
	}

	// Register our custom intervals.
	public static function schedules( $s ) {
		foreach ( array_unique( array_filter( self::map() ) ) as $sec ) {
			$s[ self::sname( $sec ) ] = [ 'interval' => $sec, 'display' => 'LSCCI: every ' . self::human( $sec ) ];
		}
		return $s;
	}

	// Intercept every (re)schedule of mapped hooks, incl. LSCWP's own wp_schedule_event().
	public static function override_event( $event ) {
		if ( ! $event || empty( $event->schedule ) || ! isset( self::HOOKS[ $event->hook ] ) ) {
			return $event;
		}
		$map = self::map();
		if ( ! empty( $map[ $event->hook ] ) ) {
			$event->schedule = self::sname( $map[ $event->hook ] );
			$event->interval = $map[ $event->hook ];
		}
		return $event;
	}

	// Move existing events to the chosen schedule, or hand back to LSCWP default.
	public static function sync() {
		$map = self::map();
		foreach ( array_keys( self::HOOKS ) as $hook ) {
			$e = wp_get_scheduled_event( $hook );
			if ( ! $e || empty( $e->schedule ) ) continue;
			$sec = isset( $map[ $hook ] ) ? $map[ $hook ] : 0;

			if ( $sec && $e->schedule !== self::sname( $sec ) ) {
				wp_clear_scheduled_hook( $hook );
				wp_schedule_event( time() + $sec, self::sname( $sec ), $hook );
			} elseif ( ! $sec && 0 === strpos( $e->schedule, self::PREFIX ) ) {
				wp_clear_scheduled_hook( $hook ); // LSCWP re-adds its default on next load
			}
		}
	}

	public static function cleanup() {
		foreach ( array_keys( self::HOOKS ) as $hook ) {
			$e = wp_get_scheduled_event( $hook );
			if ( $e && ! empty( $e->schedule ) && 0 === strpos( $e->schedule, self::PREFIX ) ) {
				wp_clear_scheduled_hook( $hook );
			}
		}
	}

	/* ---------- Admin UI ---------- */

	public static function menu() {
		add_options_page( 'LiteSpeed Cron Intervals', 'LiteSpeed Cron', 'manage_options', 'lscci', [ __CLASS__, 'page' ] );
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', 403 );
		check_admin_referer( 'lscci_save' );

		$in  = isset( $_POST['lscci'] ) && is_array( $_POST['lscci'] ) ? wp_unslash( $_POST['lscci'] ) : [];
		$out = [];
		foreach ( array_keys( self::HOOKS ) as $hook ) {
			$sel = isset( $in[ $hook ]['sel'] ) ? sanitize_text_field( $in[ $hook ]['sel'] ) : '';
			$sec = ( 'custom' === $sel ) ? absint( isset( $in[ $hook ]['custom'] ) ? $in[ $hook ]['custom'] : 0 ) : absint( $sel );
            if ( $sec ) $out[ $hook ] = max( self::min_for( $hook ), $sec );
		}
		update_option( self::OPT, $out, false );
		self::sync();

		wp_safe_redirect( add_query_arg( [ 'page' => 'lscci', 'updated' => 1 ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$map = self::map();
		?>
		<div class="wrap">
			<h1>LiteSpeed Cron Intervals</h1>
			<?php if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) : ?>
				<div class="notice notice-warning"><p>WP-Cron is traffic-triggered. For reliable short intervals, set <code>DISABLE_WP_CRON</code> and use a real server cron hitting <code>wp-cron.php</code> every minute.</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="lscci_save">
				<?php wp_nonce_field( 'lscci_save' ); ?>
				<table class="widefat striped" style="max-width:1000px">
					<thead><tr><th>Task</th><th>Hook</th><th>Current schedule</th><th>Next run</th><th>Interval</th></tr></thead>
					<tbody>
					<?php foreach ( self::HOOKS as $hook => $label ) :
						$e      = wp_get_scheduled_event( $hook );
						$cur    = isset( $map[ $hook ] ) ? $map[ $hook ] : 0;
						$custom = $cur && ! in_array( $cur, self::PRESETS, true );
						?>
						<tr>
                            <td><strong><?php echo esc_html( $label ); ?></strong>
								<?php if ( isset( self::HOOK_MIN[ $hook ] ) ) : ?>
									<br><small>Min: <?php echo esc_html( self::human( self::HOOK_MIN[ $hook ] ) ); ?> (QUIC.cloud rate limit)</small>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( $hook ); ?></code></td>
							<td><?php echo $e ? esc_html( $e->schedule ?: 'single' ) : '<em>not scheduled</em>'; ?></td>
							<td><?php echo $e ? esc_html( human_time_diff( time(), $e->timestamp ) . ( $e->timestamp < time() ? ' ago (due)' : '' ) ) : '—'; ?></td>
							<td>
								<select name="lscci[<?php echo esc_attr( $hook ); ?>][sel]" onchange="this.nextElementSibling.style.display=this.value==='custom'?'inline-block':'none'">
									<option value="">LSCWP default</option>
                                    <?php foreach ( self::PRESETS as $p ) : if ( $p < self::min_for( $hook ) ) continue; ?>
										<option value="<?php echo (int) $p; ?>" <?php selected( $cur, $p ); ?>><?php echo esc_html( self::human( $p ) ); ?></option>
									<?php endforeach; ?>
									<option value="custom" <?php selected( $custom ); ?>>Custom (seconds)</option>
								</select>
								<input type="number" min="<?php echo (int) self::min_for( $hook ); ?>" step="1" class="small-text"
									name="lscci[<?php echo esc_attr( $hook ); ?>][custom]"
									value="<?php echo $custom ? (int) $cur : ''; ?>"
									style="display:<?php echo $custom ? 'inline-block' : 'none'; ?>">
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">"Not scheduled" means the feature is off in LiteSpeed Cache or it is waiting on a QUIC.cloud <code>try_later</code> timeout. Your interval is applied automatically when LSCWP schedules it again.</p>
				<?php submit_button( 'Save Intervals' ); ?>
			</form>
		</div>
		<?php
	}
}

LSCCI::boot();
register_deactivation_hook( __FILE__, [ 'LSCCI', 'cleanup' ] );
register_uninstall_hook( __FILE__, 'lscci_uninstall' );
function lscci_uninstall() {
	delete_option( LSCCI::OPT );
}
