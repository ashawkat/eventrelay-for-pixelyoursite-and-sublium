<?php
/**
 * Dashboard view.
 *
 * @package PYS_Sublium_Bridge
 *
 * @var array<string,mixed> $settings Settings.
 * @var array<int,array<string,mixed>> $entries Log rows newest first.
 * @var array<int,array<string,mixed>> $groups Entries grouped by order (current page).
 * @var array<string,int> $stats Counts.
 * @var bool $pys_ok PixelYourSite active.
 * @var bool $sub_ok Sublium active.
 * @var array<string,mixed>|null $inspect Order inspection result.
 * @var string $filter Active activity filter.
 * @var array<string,mixed> $pagination Activity pagination.
 */

defined( 'ABSPATH' ) || exit;

$pys_sublium_bridge_option_key = PYS_Sublium_Bridge_Plugin::OPTION_KEY;
$pys_sublium_bridge_filter     = isset( $filter ) ? $filter : 'all';
$pys_sublium_bridge_paging     = isset( $pagination ) && is_array( $pagination ) ? $pagination : array();
$pys_sublium_bridge_filters    = array(
	'all'     => __( 'All', 'pys-sublium-bridge' ),
	'allowed' => __( 'Purchases', 'pys-sublium-bridge' ),
	'sent'    => __( 'First-time', 'pys-sublium-bridge' ),
	'blocked' => __( 'Blocked', 'pys-sublium-bridge' ),
);
?>
<div class="wrap pysb-dash">
	<div class="pysb-hero">
		<div class="pysb-hero__text">
			<p class="pysb-kicker"><?php esc_html_e( 'PixelYourSite × Sublium', 'pys-sublium-bridge' ); ?></p>
			<h1><?php esc_html_e( 'Tracking dashboard', 'pys-sublium-bridge' ); ?></h1>
			<p class="pysb-lead">
				<?php esc_html_e( 'See what Purchase and FirstTimeBuyer events this bridge sent or blocked, and keep renewal ad data clean.', 'pys-sublium-bridge' ); ?>
			</p>
		</div>
		<div class="pysb-status">
			<span class="pysb-pill <?php echo $pys_ok ? 'is-on' : 'is-off'; ?>">
				<?php echo $pys_ok ? esc_html__( 'PixelYourSite connected', 'pys-sublium-bridge' ) : esc_html__( 'PixelYourSite missing', 'pys-sublium-bridge' ); ?>
			</span>
			<span class="pysb-pill <?php echo $sub_ok ? 'is-on' : 'is-off'; ?>">
				<?php echo $sub_ok ? esc_html__( 'Sublium detected', 'pys-sublium-bridge' ) : esc_html__( 'Sublium not detected', 'pys-sublium-bridge' ); ?>
			</span>
			<span class="pysb-pill <?php echo ! empty( $settings['logging_enabled'] ) ? 'is-on' : 'is-off'; ?>">
				<?php echo ! empty( $settings['logging_enabled'] ) ? esc_html__( 'Logging on', 'pys-sublium-bridge' ) : esc_html__( 'Logging off', 'pys-sublium-bridge' ); ?>
			</span>
		</div>
	</div>

	<div class="pysb-stats">
		<div class="pysb-stat">
			<span class="pysb-stat__label"><?php esc_html_e( 'Purchases sent', 'pys-sublium-bridge' ); ?></span>
			<strong class="pysb-stat__value"><?php echo esc_html( (string) $stats['purchases_sent'] ); ?></strong>
			<span class="pysb-stat__hint"><?php esc_html_e( 'First sales and non-renewals', 'pys-sublium-bridge' ); ?></span>
		</div>
		<div class="pysb-stat pysb-stat--block">
			<span class="pysb-stat__label"><?php esc_html_e( 'Renewals blocked', 'pys-sublium-bridge' ); ?></span>
			<strong class="pysb-stat__value"><?php echo esc_html( (string) $stats['renewals_blocked'] ); ?></strong>
			<span class="pysb-stat__hint"><?php esc_html_e( 'Kept out of ad Purchase data', 'pys-sublium-bridge' ); ?></span>
		</div>
		<div class="pysb-stat pysb-stat--first">
			<span class="pysb-stat__label"><?php esc_html_e( 'FirstTimeBuyer sent', 'pys-sublium-bridge' ); ?></span>
			<strong class="pysb-stat__value"><?php echo esc_html( (string) $stats['first_time_sent'] ); ?></strong>
			<span class="pysb-stat__hint"><?php esc_html_e( 'Alongside first-sale Purchase', 'pys-sublium-bridge' ); ?></span>
		</div>
		<div class="pysb-stat">
			<span class="pysb-stat__label"><?php esc_html_e( 'Events today', 'pys-sublium-bridge' ); ?></span>
			<strong class="pysb-stat__value"><?php echo esc_html( (string) $stats['today'] ); ?></strong>
			<span class="pysb-stat__hint"><?php esc_html_e( 'All send / block decisions', 'pys-sublium-bridge' ); ?></span>
		</div>
	</div>

	<div class="pysb-grid">
		<section class="pysb-card pysb-card--feed">
			<div class="pysb-card__head">
				<div>
					<h2><?php esc_html_e( 'Activity', 'pys-sublium-bridge' ); ?></h2>
					<p><?php esc_html_e( 'Grouped by order, newest first. Filter to see only what you care about.', 'pys-sublium-bridge' ); ?></p>
				</div>
				<form method="post" class="pysb-clear">
					<?php wp_nonce_field( 'pys_sublium_bridge_clear_log' ); ?>
					<input type="hidden" name="pys_sublium_bridge_clear_log" value="1" />
					<button type="submit" class="button"><?php esc_html_e( 'Clear log', 'pys-sublium-bridge' ); ?></button>
				</form>
			</div>

			<div class="pysb-filters" role="tablist">
				<?php foreach ( $pys_sublium_bridge_filters as $pys_sublium_bridge_filter_key => $pys_sublium_bridge_filter_label ) : ?>
					<?php
					$pys_sublium_bridge_filter_url = PYS_Sublium_Bridge_Admin::dashboard_url(
						array(
							'pys_sublium_bridge_filter' => 'all' === $pys_sublium_bridge_filter_key ? null : $pys_sublium_bridge_filter_key,
							'pysb_paged'                => null,
						)
					);
					?>
					<a
						class="pysb-filter<?php echo $pys_sublium_bridge_filter === $pys_sublium_bridge_filter_key ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( $pys_sublium_bridge_filter_url ); ?>#pysb-activity"
						role="tab"
						aria-selected="<?php echo $pys_sublium_bridge_filter === $pys_sublium_bridge_filter_key ? 'true' : 'false'; ?>"
					><?php echo esc_html( $pys_sublium_bridge_filter_label ); ?></a>
				<?php endforeach; ?>
			</div>

			<?php if ( empty( $groups ) ) : ?>
				<div class="pysb-empty" id="pysb-activity">
					<?php if ( 'all' === $pys_sublium_bridge_filter ) : ?>
						<strong><?php esc_html_e( 'Nothing logged yet', 'pys-sublium-bridge' ); ?></strong>
						<p><?php esc_html_e( 'Place a first-time order or wait for a Sublium renewal. This feed will show exactly what was sent to Meta, Google, TikTok, and OpenAI.', 'pys-sublium-bridge' ); ?></p>
					<?php else : ?>
						<strong><?php esc_html_e( 'No orders match this filter.', 'pys-sublium-bridge' ); ?></strong>
						<p><?php esc_html_e( 'Try All, or wait for a matching Purchase or renewal.', 'pys-sublium-bridge' ); ?></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<ul class="pysb-feed" id="pysb-activity">
					<?php foreach ( $groups as $pys_sublium_bridge_group ) : ?>
						<?php
						$pys_sublium_bridge_order_id = isset( $pys_sublium_bridge_group['order_id'] ) ? (int) $pys_sublium_bridge_group['order_id'] : 0;
						$pys_sublium_bridge_actions  = ! empty( $pys_sublium_bridge_group['actions'] ) ? array_keys( $pys_sublium_bridge_group['actions'] ) : array();
						$pys_sublium_bridge_count    = count( $pys_sublium_bridge_group['entries'] );
						?>
						<li class="pysb-group" data-actions="<?php echo esc_attr( implode( ' ', $pys_sublium_bridge_actions ) ); ?>">
							<div class="pysb-group__head">
								<div class="pysb-group__main">
									<div class="pysb-group__title">
										<?php if ( $pys_sublium_bridge_order_id ) : ?>
											<a class="pysb-order" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $pys_sublium_bridge_order_id ) ); ?>">
												#<?php echo esc_html( (string) $pys_sublium_bridge_order_id ); ?>
											</a>
										<?php else : ?>
											<span class="pysb-order"><?php esc_html_e( 'Unknown order', 'pys-sublium-bridge' ); ?></span>
										<?php endif; ?>
										<?php foreach ( $pys_sublium_bridge_actions as $pys_sublium_bridge_action ) : ?>
											<span class="pysb-badge pysb-badge--<?php echo esc_attr( $pys_sublium_bridge_action ); ?>"><?php echo esc_html( PYS_Sublium_Bridge_Admin::action_label( $pys_sublium_bridge_action ) ); ?></span>
										<?php endforeach; ?>
									</div>
									<p class="pysb-group__summary"><?php echo esc_html( PYS_Sublium_Bridge_Admin::group_summary( $pys_sublium_bridge_group ) ); ?></p>
								</div>
								<div class="pysb-group__side">
									<span class="pysb-group__count">
										<?php
										printf(
											/* translators: %d: number of destinations */
											esc_html( _n( '%d destination', '%d destinations', $pys_sublium_bridge_count, 'pys-sublium-bridge' ) ),
											(int) $pys_sublium_bridge_count
										);
										?>
									</span>
									<time datetime="<?php echo esc_attr( $pys_sublium_bridge_group['time'] ?? '' ); ?>" title="<?php echo esc_attr( $pys_sublium_bridge_group['time'] ?? '' ); ?>">
										<?php echo esc_html( PYS_Sublium_Bridge_Admin::time_ago( $pys_sublium_bridge_group['time'] ?? '' ) ); ?>
									</time>
								</div>
							</div>
							<ul class="pysb-group__events">
								<?php foreach ( $pys_sublium_bridge_group['entries'] as $pys_sublium_bridge_entry ) : ?>
									<?php
									$pys_sublium_bridge_row_action = isset( $pys_sublium_bridge_entry['action'] ) ? $pys_sublium_bridge_entry['action'] : '';
									$pys_sublium_bridge_channel    = isset( $pys_sublium_bridge_entry['channel'] ) ? $pys_sublium_bridge_entry['channel'] : '';
									$pys_sublium_bridge_platform   = isset( $pys_sublium_bridge_entry['platform'] ) ? $pys_sublium_bridge_entry['platform'] : '';
									$pys_sublium_bridge_event      = isset( $pys_sublium_bridge_entry['event'] ) ? $pys_sublium_bridge_entry['event'] : '';
									?>
									<li class="pysb-row" data-action="<?php echo esc_attr( $pys_sublium_bridge_row_action ); ?>">
										<div class="pysb-row__meta">
											<?php if ( $pys_sublium_bridge_event ) : ?>
												<span class="pysb-chip"><?php echo esc_html( $pys_sublium_bridge_event ); ?></span>
											<?php endif; ?>
											<?php if ( $pys_sublium_bridge_channel ) : ?>
												<span class="pysb-chip"><?php echo esc_html( $pys_sublium_bridge_channel ); ?></span>
											<?php endif; ?>
											<?php if ( $pys_sublium_bridge_platform ) : ?>
												<span class="pysb-chip pysb-chip--dest"><?php echo esc_html( $pys_sublium_bridge_platform ); ?></span>
											<?php endif; ?>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( ! empty( $pys_sublium_bridge_paging['total'] ) ) : ?>
					<nav class="pysb-pager" aria-label="<?php esc_attr_e( 'Activity pages', 'pys-sublium-bridge' ); ?>">
						<p class="pysb-pager__meta">
							<?php
							printf(
								/* translators: 1: first order on page, 2: last order on page, 3: total matching orders */
								esc_html__( 'Showing %1$s–%2$s of %3$s orders', 'pys-sublium-bridge' ),
								esc_html( (string) $pys_sublium_bridge_paging['from'] ),
								esc_html( (string) $pys_sublium_bridge_paging['to'] ),
								esc_html( (string) $pys_sublium_bridge_paging['total'] )
							);
							?>
						</p>
						<?php echo wp_kses_post( PYS_Sublium_Bridge_Admin::pagination_html( $pys_sublium_bridge_paging ) ); ?>
					</nav>
				<?php endif; ?>
			<?php endif; ?>
		</section>

		<aside class="pysb-side">
			<section class="pysb-card">
				<div class="pysb-card__head">
					<div>
						<h2><?php esc_html_e( 'Settings', 'pys-sublium-bridge' ); ?></h2>
						<p><?php esc_html_e( 'These controls drive both this dashboard and PixelYourSite.', 'pys-sublium-bridge' ); ?></p>
					</div>
				</div>

				<form method="post" action="options.php" class="pysb-settings">
					<?php settings_fields( 'pys_sublium_bridge' ); ?>

					<label class="pysb-toggle">
						<input type="checkbox" name="<?php echo esc_attr( $pys_sublium_bridge_option_key ); ?>[exclude_auto_renewals]" value="1" <?php checked( ! empty( $settings['exclude_auto_renewals'] ) ); ?> />
						<span>
							<strong><?php esc_html_e( 'Block automatic renewals', 'pys-sublium-bridge' ); ?></strong>
							<span><?php esc_html_e( 'Do not send Purchase when Sublium charges a scheduled renewal.', 'pys-sublium-bridge' ); ?></span>
						</span>
					</label>

					<label class="pysb-toggle">
						<input type="checkbox" name="<?php echo esc_attr( $pys_sublium_bridge_option_key ); ?>[exclude_manual_renewals]" value="1" <?php checked( ! empty( $settings['exclude_manual_renewals'] ) ); ?> />
						<span>
							<strong><?php esc_html_e( 'Block manual / early renewals', 'pys-sublium-bridge' ); ?></strong>
							<span><?php esc_html_e( 'Do not send Purchase for customer-initiated or manual renewals.', 'pys-sublium-bridge' ); ?></span>
						</span>
					</label>

					<label class="pysb-toggle">
						<input type="checkbox" name="<?php echo esc_attr( $pys_sublium_bridge_option_key ); ?>[send_first_time_buyer]" value="1" <?php checked( ! empty( $settings['send_first_time_buyer'] ) ); ?> />
						<span>
							<strong><?php esc_html_e( 'Send FirstTimeBuyer on first sale', 'pys-sublium-bridge' ); ?></strong>
							<span><?php esc_html_e( 'Fires PixelYourSite’s FirstTimeBuyer event with Purchase. Never on renewals.', 'pys-sublium-bridge' ); ?></span>
						</span>
					</label>

					<label class="pysb-toggle">
						<input type="checkbox" name="<?php echo esc_attr( $pys_sublium_bridge_option_key ); ?>[logging_enabled]" value="1" <?php checked( ! empty( $settings['logging_enabled'] ) ); ?> />
						<span>
							<strong><?php esc_html_e( 'Keep this activity log', 'pys-sublium-bridge' ); ?></strong>
							<span><?php esc_html_e( 'Last 150 decisions. Also written to WooCommerce → Status → Logs.', 'pys-sublium-bridge' ); ?></span>
						</span>
					</label>

					<?php submit_button( __( 'Save settings', 'pys-sublium-bridge' ) ); ?>
				</form>
			</section>

			<section class="pysb-card">
				<div class="pysb-card__head">
					<div>
						<h2><?php esc_html_e( 'Check an order', 'pys-sublium-bridge' ); ?></h2>
						<p><?php esc_html_e( 'Paste a WooCommerce order ID you think is a renewal. This tells you whether Purchase will be blocked.', 'pys-sublium-bridge' ); ?></p>
					</div>
				</div>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="pysb-inspect">
					<input type="hidden" name="page" value="pys-sublium-bridge" />
					<?php if ( 'all' !== $pys_sublium_bridge_filter ) : ?>
						<input type="hidden" name="pys_sublium_bridge_filter" value="<?php echo esc_attr( $pys_sublium_bridge_filter ); ?>" />
					<?php endif; ?>
					<label class="screen-reader-text" for="pys-sublium-bridge-order"><?php esc_html_e( 'Order ID', 'pys-sublium-bridge' ); ?></label>
					<input id="pys-sublium-bridge-order" type="number" min="1" name="pys_sublium_bridge_order" value="<?php echo $inspect && ! empty( $inspect['order_id'] ) ? esc_attr( (string) $inspect['order_id'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'Order ID', 'pys-sublium-bridge' ); ?>" />
					<button type="submit" class="button"><?php esc_html_e( 'Check', 'pys-sublium-bridge' ); ?></button>
				</form>
				<?php if ( is_array( $inspect ) ) : ?>
					<div class="pysb-inspect-result pysb-inspect-result--<?php echo esc_attr( $inspect['status'] ); ?>">
						<strong><?php echo esc_html( $inspect['headline'] ); ?></strong>
						<?php if ( ! empty( $inspect['detail'] ) ) : ?>
							<p><?php echo esc_html( $inspect['detail'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $inspect['facts'] ) ) : ?>
							<ul>
								<?php foreach ( $inspect['facts'] as $pys_sublium_bridge_label => $pys_sublium_bridge_value ) : ?>
									<li>
										<span><?php echo esc_html( $pys_sublium_bridge_label ); ?></span>
										<?php echo esc_html( (string) $pys_sublium_bridge_value ); ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</section>

			<section class="pysb-card pysb-card--help">
				<h2><?php esc_html_e( 'What you should see', 'pys-sublium-bridge' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'First paid order: Purchase sent, then FirstTimeBuyer sent.', 'pys-sublium-bridge' ); ?></li>
					<li><?php esc_html_e( 'Sublium renewal: Purchase blocked. No FirstTimeBuyer.', 'pys-sublium-bridge' ); ?></li>
					<li><?php esc_html_e( 'Repeat one-time customer: Purchase sent, no FirstTimeBuyer.', 'pys-sublium-bridge' ); ?></li>
				</ol>
			</section>
		</aside>
	</div>
</div>
