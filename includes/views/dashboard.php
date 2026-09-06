<?php
/**
 * Dashboard view.
 *
 * @package EventRelay
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

$eventrelay_option_key = EventRelay_Plugin::OPTION_KEY;
$eventrelay_filter     = isset( $filter ) ? $filter : 'all';
$eventrelay_paging     = isset( $pagination ) && is_array( $pagination ) ? $pagination : array();
$eventrelay_filters    = array(
	'all'     => __( 'All', 'eventrelay-for-pixelyoursite-and-sublium' ),
	'allowed' => __( 'Purchases', 'eventrelay-for-pixelyoursite-and-sublium' ),
	'sent'    => __( 'First-time', 'eventrelay-for-pixelyoursite-and-sublium' ),
	'blocked' => __( 'Blocked', 'eventrelay-for-pixelyoursite-and-sublium' ),
);
?>
<div class="wrap pysb-dash">
	<div class="pysb-hero">
		<div class="pysb-hero__text">
			<p class="pysb-kicker"><?php esc_html_e( 'EventRelay by Betatech', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></p>
			<h1><?php esc_html_e( 'Tracking dashboard', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></h1>
			<p class="pysb-lead">
				<?php esc_html_e( 'See what Purchase and FirstTimeBuyer events this bridge sent or blocked, and keep renewal ad data clean.', 'eventrelay-for-pixelyoursite-and-sublium' ); ?>
			</p>
		</div>
		<div class="pysb-status">
			<span class="pysb-pill <?php echo $pys_ok ? 'is-on' : 'is-off'; ?>">
				<?php echo $pys_ok ? esc_html__( 'PixelYourSite connected', 'eventrelay-for-pixelyoursite-and-sublium' ) : esc_html__( 'PixelYourSite missing', 'eventrelay-for-pixelyoursite-and-sublium' ); ?>
			</span>
			<span class="pysb-pill <?php echo $sub_ok ? 'is-on' : 'is-off'; ?>">
				<?php echo $sub_ok ? esc_html__( 'Sublium detected', 'eventrelay-for-pixelyoursite-and-sublium' ) : esc_html__( 'Sublium not detected', 'eventrelay-for-pixelyoursite-and-sublium' ); ?>
			</span>
			<span class="pysb-pill <?php echo ! empty( $settings['logging_enabled'] ) ? 'is-on' : 'is-off'; ?>">
				<?php echo ! empty( $settings['logging_enabled'] ) ? esc_html__( 'Logging on', 'eventrelay-for-pixelyoursite-and-sublium' ) : esc_html__( 'Logging off', 'eventrelay-for-pixelyoursite-and-sublium' ); ?>
			</span>
		</div>
	</div>

	<div class="pysb-stats">
		<div class="pysb-stat">
			<span class="pysb-stat__label"><?php esc_html_e( 'Purchases sent', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></span>
			<strong class="pysb-stat__value"><?php echo esc_html( (string) $stats['purchases_sent'] ); ?></strong>
			<span class="pysb-stat__hint"><?php esc_html_e( 'First sales and non-renewals', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></span>
		</div>
		<div class="pysb-stat pysb-stat--block">
			<span class="pysb-stat__label"><?php esc_html_e( 'Renewals blocked', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></span>
			<strong class="pysb-stat__value"><?php echo esc_html( (string) $stats['renewals_blocked'] ); ?></strong>
			<span class="pysb-stat__hint"><?php esc_html_e( 'Kept out of ad Purchase data', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></span>
		</div>
		<div class="pysb-stat pysb-stat--first">
			<span class="pysb-stat__label"><?php esc_html_e( 'FirstTimeBuyer sent', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></span>
			<strong class="pysb-stat__value"><?php echo esc_html( (string) $stats['first_time_sent'] ); ?></strong>
			<span class="pysb-stat__hint"><?php esc_html_e( 'Alongside first-sale Purchase', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></span>
		</div>
		<div class="pysb-stat">
			<span class="pysb-stat__label"><?php esc_html_e( 'Events today', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></span>
			<strong class="pysb-stat__value"><?php echo esc_html( (string) $stats['today'] ); ?></strong>
			<span class="pysb-stat__hint"><?php esc_html_e( 'All send / block decisions', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></span>
		</div>
	</div>

	<div class="pysb-grid">
		<section class="pysb-card pysb-card--feed">
			<div class="pysb-card__head">
				<div>
					<h2><?php esc_html_e( 'Activity', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></h2>
					<p><?php esc_html_e( 'Grouped by order, newest first. Filter to see only what you care about.', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></p>
				</div>
				<form method="post" class="pysb-clear">
					<?php wp_nonce_field( 'eventrelay_clear_log' ); ?>
					<input type="hidden" name="eventrelay_clear_log" value="1" />
					<button type="submit" class="button"><?php esc_html_e( 'Clear log', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></button>
				</form>
			</div>

			<div class="pysb-filters" role="tablist">
				<?php foreach ( $eventrelay_filters as $eventrelay_filter_key => $eventrelay_filter_label ) : ?>
					<?php
					$eventrelay_filter_url = EventRelay_Admin::dashboard_url(
						array(
							'eventrelay_filter' => 'all' === $eventrelay_filter_key ? null : $eventrelay_filter_key,
							'pysb_paged'                => null,
						)
					);
					?>
					<a
						class="pysb-filter<?php echo $eventrelay_filter === $eventrelay_filter_key ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( $eventrelay_filter_url ); ?>#pysb-activity"
						role="tab"
						aria-selected="<?php echo $eventrelay_filter === $eventrelay_filter_key ? 'true' : 'false'; ?>"
					><?php echo esc_html( $eventrelay_filter_label ); ?></a>
				<?php endforeach; ?>
			</div>

			<?php if ( empty( $groups ) ) : ?>
				<div class="pysb-empty" id="pysb-activity">
					<?php if ( 'all' === $eventrelay_filter ) : ?>
						<strong><?php esc_html_e( 'Nothing logged yet', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></strong>
						<p><?php esc_html_e( 'Place a first-time order or wait for a Sublium renewal. This feed will show exactly what was sent to Meta, Google, TikTok, and OpenAI.', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></p>
					<?php else : ?>
						<strong><?php esc_html_e( 'No orders match this filter.', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></strong>
						<p><?php esc_html_e( 'Try All, or wait for a matching Purchase or renewal.', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<ul class="pysb-feed" id="pysb-activity">
					<?php foreach ( $groups as $eventrelay_group ) : ?>
						<?php
						$eventrelay_order_id = isset( $eventrelay_group['order_id'] ) ? (int) $eventrelay_group['order_id'] : 0;
						$eventrelay_actions  = ! empty( $eventrelay_group['actions'] ) ? array_keys( $eventrelay_group['actions'] ) : array();
						$eventrelay_count    = count( $eventrelay_group['entries'] );
						?>
						<li class="pysb-group" data-actions="<?php echo esc_attr( implode( ' ', $eventrelay_actions ) ); ?>">
							<div class="pysb-group__head">
								<div class="pysb-group__main">
									<div class="pysb-group__title">
										<?php if ( $eventrelay_order_id ) : ?>
											<a class="pysb-order" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $eventrelay_order_id ) ); ?>">
												#<?php echo esc_html( (string) $eventrelay_order_id ); ?>
											</a>
										<?php else : ?>
											<span class="pysb-order"><?php esc_html_e( 'Unknown order', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></span>
										<?php endif; ?>
										<?php foreach ( $eventrelay_actions as $eventrelay_action ) : ?>
											<span class="pysb-badge pysb-badge--<?php echo esc_attr( $eventrelay_action ); ?>"><?php echo esc_html( EventRelay_Admin::action_label( $eventrelay_action ) ); ?></span>
										<?php endforeach; ?>
									</div>
									<p class="pysb-group__summary"><?php echo esc_html( EventRelay_Admin::group_summary( $eventrelay_group ) ); ?></p>
								</div>
								<div class="pysb-group__side">
									<span class="pysb-group__count">
										<?php
										printf(
											/* translators: %d: number of destinations */
											esc_html( _n( '%d destination', '%d destinations', $eventrelay_count, 'eventrelay-for-pixelyoursite-and-sublium' ) ),
											(int) $eventrelay_count
										);
										?>
									</span>
									<time datetime="<?php echo esc_attr( $eventrelay_group['time'] ?? '' ); ?>" title="<?php echo esc_attr( $eventrelay_group['time'] ?? '' ); ?>">
										<?php echo esc_html( EventRelay_Admin::time_ago( $eventrelay_group['time'] ?? '' ) ); ?>
									</time>
								</div>
							</div>
							<ul class="pysb-group__events">
								<?php foreach ( $eventrelay_group['entries'] as $eventrelay_entry ) : ?>
									<?php
									$eventrelay_row_action = isset( $eventrelay_entry['action'] ) ? $eventrelay_entry['action'] : '';
									$eventrelay_channel    = isset( $eventrelay_entry['channel'] ) ? $eventrelay_entry['channel'] : '';
									$eventrelay_platform   = isset( $eventrelay_entry['platform'] ) ? $eventrelay_entry['platform'] : '';
									$eventrelay_event      = isset( $eventrelay_entry['event'] ) ? $eventrelay_entry['event'] : '';
									?>
									<li class="pysb-row" data-action="<?php echo esc_attr( $eventrelay_row_action ); ?>">
										<div class="pysb-row__meta">
											<?php if ( $eventrelay_event ) : ?>
												<span class="pysb-chip"><?php echo esc_html( $eventrelay_event ); ?></span>
											<?php endif; ?>
											<?php if ( $eventrelay_channel ) : ?>
												<span class="pysb-chip"><?php echo esc_html( $eventrelay_channel ); ?></span>
											<?php endif; ?>
											<?php if ( $eventrelay_platform ) : ?>
												<span class="pysb-chip pysb-chip--dest"><?php echo esc_html( $eventrelay_platform ); ?></span>
											<?php endif; ?>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( ! empty( $eventrelay_paging['total'] ) ) : ?>
					<nav class="pysb-pager" aria-label="<?php esc_attr_e( 'Activity pages', 'eventrelay-for-pixelyoursite-and-sublium' ); ?>">
						<p class="pysb-pager__meta">
							<?php
							printf(
								/* translators: 1: first order on page, 2: last order on page, 3: total matching orders */
								esc_html__( 'Showing %1$s–%2$s of %3$s orders', 'eventrelay-for-pixelyoursite-and-sublium' ),
								esc_html( (string) $eventrelay_paging['from'] ),
								esc_html( (string) $eventrelay_paging['to'] ),
								esc_html( (string) $eventrelay_paging['total'] )
							);
							?>
						</p>
						<?php echo wp_kses_post( EventRelay_Admin::pagination_html( $eventrelay_paging ) ); ?>
					</nav>
				<?php endif; ?>
			<?php endif; ?>
		</section>

		<aside class="pysb-side">
			<section class="pysb-card">
				<div class="pysb-card__head">
					<div>
						<h2><?php esc_html_e( 'Settings', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></h2>
						<p><?php esc_html_e( 'These controls drive both this dashboard and PixelYourSite.', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></p>
					</div>
				</div>

				<form method="post" action="options.php" class="pysb-settings">
					<?php settings_fields( 'eventrelay' ); ?>

					<label class="pysb-toggle">
						<input type="checkbox" name="<?php echo esc_attr( $eventrelay_option_key ); ?>[exclude_auto_renewals]" value="1" <?php checked( ! empty( $settings['exclude_auto_renewals'] ) ); ?> />
						<span>
							<strong><?php esc_html_e( 'Block automatic renewals', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></strong>
							<span><?php esc_html_e( 'Do not send Purchase when Sublium charges a scheduled renewal.', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></span>
						</span>
					</label>

					<label class="pysb-toggle">
						<input type="checkbox" name="<?php echo esc_attr( $eventrelay_option_key ); ?>[exclude_manual_renewals]" value="1" <?php checked( ! empty( $settings['exclude_manual_renewals'] ) ); ?> />
						<span>
							<strong><?php esc_html_e( 'Block manual / early renewals', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></strong>
							<span><?php esc_html_e( 'Do not send Purchase for customer-initiated or manual renewals.', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></span>
						</span>
					</label>

					<label class="pysb-toggle">
						<input type="checkbox" name="<?php echo esc_attr( $eventrelay_option_key ); ?>[send_first_time_buyer]" value="1" <?php checked( ! empty( $settings['send_first_time_buyer'] ) ); ?> />
						<span>
							<strong><?php esc_html_e( 'Send FirstTimeBuyer on first sale', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></strong>
							<span><?php esc_html_e( 'Fires PixelYourSite’s FirstTimeBuyer event with Purchase. Never on renewals.', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></span>
						</span>
					</label>

					<label class="pysb-toggle">
						<input type="checkbox" name="<?php echo esc_attr( $eventrelay_option_key ); ?>[logging_enabled]" value="1" <?php checked( ! empty( $settings['logging_enabled'] ) ); ?> />
						<span>
							<strong><?php esc_html_e( 'Keep this activity log', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></strong>
							<span><?php esc_html_e( 'Last 150 decisions. Also written to WooCommerce → Status → Logs.', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></span>
						</span>
					</label>

					<?php submit_button( __( 'Save settings', 'eventrelay-for-pixelyoursite-and-sublium' ) ); ?>
				</form>
			</section>

			<section class="pysb-card">
				<div class="pysb-card__head">
					<div>
						<h2><?php esc_html_e( 'Check an order', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></h2>
						<p><?php esc_html_e( 'Paste a WooCommerce order ID you think is a renewal. This tells you whether Purchase will be blocked.', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></p>
					</div>
				</div>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="pysb-inspect">
					<input type="hidden" name="page" value="eventrelay" />
					<?php if ( 'all' !== $eventrelay_filter ) : ?>
						<input type="hidden" name="eventrelay_filter" value="<?php echo esc_attr( $eventrelay_filter ); ?>" />
					<?php endif; ?>
					<label class="screen-reader-text" for="eventrelay-order"><?php esc_html_e( 'Order ID', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></label>
					<input id="eventrelay-order" type="number" min="1" name="eventrelay_order" value="<?php echo $inspect && ! empty( $inspect['order_id'] ) ? esc_attr( (string) $inspect['order_id'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'Order ID', 'eventrelay-for-pixelyoursite-and-sublium' ); ?>" />
					<button type="submit" class="button"><?php esc_html_e( 'Check', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></button>
				</form>
				<?php if ( is_array( $inspect ) ) : ?>
					<div class="pysb-inspect-result pysb-inspect-result--<?php echo esc_attr( $inspect['status'] ); ?>">
						<strong><?php echo esc_html( $inspect['headline'] ); ?></strong>
						<?php if ( ! empty( $inspect['detail'] ) ) : ?>
							<p><?php echo esc_html( $inspect['detail'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $inspect['facts'] ) ) : ?>
							<ul>
								<?php foreach ( $inspect['facts'] as $eventrelay_label => $eventrelay_value ) : ?>
									<li>
										<span><?php echo esc_html( $eventrelay_label ); ?></span>
										<?php echo esc_html( (string) $eventrelay_value ); ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</section>

			<section class="pysb-card pysb-card--help">
				<h2><?php esc_html_e( 'What you should see', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'First paid order: Purchase sent, then FirstTimeBuyer sent.', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></li>
					<li><?php esc_html_e( 'Sublium renewal: Purchase blocked. No FirstTimeBuyer.', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></li>
					<li><?php esc_html_e( 'Repeat one-time customer: Purchase sent, no FirstTimeBuyer.', 'eventrelay-for-pixelyoursite-and-sublium' ); ?></li>
				</ol>
			</section>
		</aside>
	</div>
</div>
