<?php
/** Global Gloskin header. @package GloskinSiteCore */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$navigation = isset( $gloskin_context['navigation'] ) && is_array( $gloskin_context['navigation'] ) ? $gloskin_context['navigation'] : array();
if ( ! function_exists( 'gloskin_ui1_render_nav_tree' ) ) {
	function gloskin_ui1_render_nav_tree( $items, $scope ) {
		echo '<ul class="gloskin-ui1-nav__list">';
		foreach ( $items as $index => $item ) {
			$children = isset( $item['children'] ) && is_array( $item['children'] ) ? $item['children'] : array();
			$label = isset( $item['label'] ) ? (string) $item['label'] : '';
			$url = isset( $item['url'] ) ? (string) $item['url'] : '';
			$active = ! empty( $item['active'] );
			$submenu = 'gloskin-submenu-' . sanitize_html_class( $scope ) . '-' . absint( $index );
			echo '<li class="gloskin-ui1-nav__item' . ( $active ? ' is-active' : '' ) . '"><div class="gloskin-ui1-nav__row">';
			echo '<a class="gloskin-ui1-nav__link" href="' . esc_url( $url ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
			if ( $children ) {
				echo '<button class="gloskin-ui1-nav__toggle" type="button" data-gloskin-submenu-toggle aria-expanded="false" aria-controls="' . esc_attr( $submenu ) . '">';
				echo '<span class="screen-reader-text">' . esc_html( sprintf( __( 'Buka submenu %s', 'gloskin-site-core' ), $label ) ) . '</span>';
				echo '<svg class="gloskin-ui1-nav__chevron" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4 6.25 8 10l4-3.75" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
			}
			echo '</div>';
			if ( $children ) {
				echo '<div class="gloskin-ui1-nav__submenu" id="' . esc_attr( $submenu ) . '" hidden>';
				gloskin_ui1_render_nav_tree( $children, $scope . '-' . $index );
				echo '</div>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}
}
?>
<a class="gloskin-ui1-skip-link" href="#gloskin-main"><?php echo esc_html__( 'Lewati ke konten utama', 'gloskin-site-core' ); ?></a>
<header class="gloskin-ui1-header">
	<div class="gloskin-ui1-container gloskin-ui1-header__inner">
		<a class="gloskin-ui1-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr__( 'Beranda Gloskin', 'gloskin-site-core' ); ?>">Gloskin</a>
		<nav class="gloskin-ui1-nav gloskin-ui1-nav--desktop" aria-label="<?php echo esc_attr__( 'Navigasi utama', 'gloskin-site-core' ); ?>"><?php gloskin_ui1_render_nav_tree( $navigation, 'desktop' ); ?></nav>
		<div class="gloskin-ui1-header__actions">
			<a class="gloskin-ui1-button gloskin-ui1-button--ghost gloskin-ui1-header__contact" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html__( 'Hubungi Kami', 'gloskin-site-core' ); ?></a>
			<button class="gloskin-ui1-drawer-toggle" type="button" data-gloskin-drawer-open aria-expanded="false" aria-controls="gloskin-mobile-drawer"><span class="screen-reader-text"><?php echo esc_html__( 'Buka navigasi', 'gloskin-site-core' ); ?></span><span aria-hidden="true">☰</span></button>
		</div>
	</div>
</header>
<?php require __DIR__ . '/mobile-drawer.php'; ?>
