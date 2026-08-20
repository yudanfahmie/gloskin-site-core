<?php
/**
 * Small reusable Gloskin UI v1 composition primitives.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'gloskin_ui1_render_pathway_grid' ) ) {
	/**
	 * Render a compact cross-discovery grid without owning destination data.
	 *
	 * @param array<int,array<string,string>> $items Pathway items.
	 * @return void
	 */
	function gloskin_ui1_render_pathway_grid( $items ) {
		$items = array_values( array_filter( (array) $items, static function ( $item ) {
			return is_array( $item ) && ! empty( $item['title'] ) && ! empty( $item['url'] );
		} ) );
		if ( ! $items ) {
			return;
		}
		?>
		<div class="gloskin-ui1-pathway-grid" data-gloskin-composition="pathways">
			<?php foreach ( array_slice( $items, 0, 3 ) as $item ) : ?>
				<a class="gloskin-ui1-pathway-card" href="<?php echo esc_url( (string) $item['url'] ); ?>">
					<?php if ( ! empty( $item['eyebrow'] ) ) : ?><span class="gloskin-ui1-eyebrow"><?php echo esc_html( (string) $item['eyebrow'] ); ?></span><?php endif; ?>
					<strong><?php echo esc_html( (string) $item['title'] ); ?></strong>
					<?php if ( ! empty( $item['copy'] ) ) : ?><span><?php echo esc_html( (string) $item['copy'] ); ?></span><?php endif; ?>
					<span class="gloskin-ui1-pathway-card__action"><?php echo esc_html( ! empty( $item['label'] ) ? (string) $item['label'] : __( 'Buka halaman', 'gloskin-site-core' ) ); ?><span aria-hidden="true"> →</span></span>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'gloskin_ui1_render_closing_cta' ) ) {
	/**
	 * Render a consistent closing next-step band.
	 *
	 * @param string $eyebrow Eyebrow.
	 * @param string $title Heading.
	 * @param string $copy Supporting copy.
	 * @param string $primary_label Primary label.
	 * @param string $primary_url Primary URL.
	 * @param string $secondary_label Optional secondary label.
	 * @param string $secondary_url Optional secondary URL.
	 * @return void
	 */
	function gloskin_ui1_render_closing_cta( $eyebrow, $title, $copy, $primary_label, $primary_url, $secondary_label = '', $secondary_url = '' ) {
		?>
		<div class="gloskin-ui1-closing-cta" data-gloskin-composition="closing-cta">
			<div class="gloskin-ui1-closing-cta__copy">
				<?php if ( '' !== trim( $eyebrow ) ) : ?><p class="gloskin-ui1-eyebrow"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
				<h2><?php echo esc_html( $title ); ?></h2>
				<?php if ( '' !== trim( $copy ) ) : ?><p><?php echo esc_html( $copy ); ?></p><?php endif; ?>
			</div>
			<div class="gloskin-ui1-closing-cta__actions">
				<?php if ( '' !== trim( $primary_label ) && '' !== trim( $primary_url ) ) : ?><a class="gloskin-ui1-button gloskin-ui1-button--light" href="<?php echo esc_url( $primary_url ); ?>"><?php echo esc_html( $primary_label ); ?></a><?php endif; ?>
				<?php if ( '' !== trim( $secondary_label ) && '' !== trim( $secondary_url ) ) : ?><a class="gloskin-ui1-button gloskin-ui1-button--ghost gloskin-ui1-button--on-dark" href="<?php echo esc_url( $secondary_url ); ?>"><?php echo esc_html( $secondary_label ); ?></a><?php endif; ?>
			</div>
		</div>
		<?php
	}
}
