<?php
/**
 * Lightweight companion English translation registry, storage and admin console.
 *
 * Canonical Indonesian content is never mutated. English values stay in the
 * existing companion meta; freshness state records which canonical source each
 * saved value was generated or edited against.
 *
 * @package GloskinSiteCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gloskin_Site_Core_Translation {
	const POST_META_KEY       = '_gloskin_translation_en';
	const TERM_META_KEY       = '_gloskin_translation_en';
	const POST_STATE_META_KEY = '_gloskin_translation_en_state';
	const TERM_STATE_META_KEY = '_gloskin_translation_en_state';
	const INTERFACE_OPTION    = 'gloskin_translation_en_interface';
	const BOOTSTRAP_OPTION    = 'gloskin_translation_en_state_bootstrap';
	const ADMIN_SLUG          = 'gloskin-translation';
	const CAPABILITY          = 'manage_options';
	const AJAX_SAVE           = 'gloskin_translation_save';
	const NONCE_ACTION        = 'gloskin_translation_save';

	/**
	 * Request-local immutable caches. Built once per request; never persisted.
	 * Eliminates per-text-node and per-filter-call array reconstruction and
	 * redundant get_option() calls — root cause of the EN memory exhaustion.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $registry_cache = null;
	/** @var array<string,array{source:string,en:string}>|null */
	private static $interface_registry_cache = null;
	/** @var array<string,string>|null */
	private static $interface_translations_cache = null;
	/**
	 * One canonical O(1) source-text → resolved-EN lookup shared by both
	 * transport owners (gettext filter and HTML output buffer).
	 *
	 * @var array<string,string>|null
	 */
	private static $interface_lookup_cache = null;

	/** @var string */
	private $plugin_file;

	/** @var string */
	private $version;

	/** @var string */
	private $admin_hook = '';

	/** @param string $plugin_file Main plugin file. @param string $version Runtime version. */
	public function __construct( $plugin_file, $version ) {
		$this->plugin_file = (string) $plugin_file;
		$this->version     = (string) $version;
	}

	/** Register the one translation administration/save owner. */
	public function register_admin() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 30 );
		add_action( 'admin_init', array( $this, 'bootstrap_legacy_freshness' ), 20 );
		add_action( 'wp_ajax_' . self::AJAX_SAVE, array( $this, 'ajax_save' ) );
	}

	/** @return array<string,mixed> */
	public static function registry() {
		if ( null !== self::$registry_cache ) { return self::$registry_cache; }
		$base = array( 'post_title' => 'Title', 'post_excerpt' => 'Excerpt', 'post_content' => 'Content' );
		$page_meta = array(
			'gloskin_hero_heading' => array( 'label' => 'Hero heading', 'rich' => false ),
			'gloskin_hero_copy' => array( 'label' => 'Hero copy', 'rich' => false ),
			'gloskin_hero_cta_label' => array( 'label' => 'Hero CTA label', 'rich' => false ),
			'gloskin_why_heading' => array( 'label' => 'Why Gloskin heading', 'rich' => false ),
			'gloskin_why_lead' => array( 'label' => 'Why Gloskin lead', 'rich' => false ),
			'gloskin_why_primary_title' => array( 'label' => 'Why Gloskin primary title', 'rich' => false ),
			'gloskin_why_primary_copy' => array( 'label' => 'Why Gloskin primary copy', 'rich' => false ),
			'gloskin_about_vision' => array( 'label' => 'Vision', 'rich' => true ),
			'gloskin_about_mission' => array( 'label' => 'Mission', 'rich' => true ),
			'gloskin_about_values' => array( 'label' => 'Values', 'rich' => true ),
			'gloskin_about_founder_role' => array( 'label' => 'Founder role', 'rich' => false ),
			'gloskin_about_founder_story' => array( 'label' => 'Founder story', 'rich' => true ),
		);
		self::$registry_cache = array(
			'post_types' => array(
				'page' => array( 'label' => 'Page', 'fields' => $base, 'meta' => $page_meta ),
				Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE => array(
					'label' => 'Treatment', 'fields' => $base,
					'meta' => array(
						'gloskin_summary' => array( 'label' => 'Summary', 'rich' => false ),
						'gloskin_benefits' => array( 'label' => 'Benefits', 'rich' => true ),
						'gloskin_contraindications' => array( 'label' => 'Contraindications', 'rich' => true ),
					),
				),
				Gloskin_Site_Core_Content_Service::CLINIC_POST_TYPE => array(
					'label' => 'Clinic', 'fields' => $base,
					'meta' => array(
						'gloskin_address' => array( 'label' => 'Address', 'rich' => false ),
						'gloskin_whatsapp_message' => array( 'label' => 'WhatsApp message', 'rich' => false ),
						'gloskin_operating_hours' => array( 'label' => 'Operating hours', 'rich' => false ),
						'gloskin_short_location' => array( 'label' => 'Short location', 'rich' => false ),
					),
				),
				Gloskin_Site_Core_Content_Service::DOCTOR_POST_TYPE => array(
					'label' => 'Doctor', 'fields' => $base,
					'meta' => array(
						'gloskin_degree_title' => array( 'label' => 'Degree', 'rich' => false ),
						'gloskin_specialization' => array( 'label' => 'Specialization', 'rich' => false ),
					),
				),
				Gloskin_Site_Core_Content_Service::PROMO_POST_TYPE => array(
					'label' => 'Promo', 'fields' => $base,
					'meta' => array(
						'gloskin_promo_eyebrow' => array( 'label' => 'Eyebrow', 'rich' => false ),
						'gloskin_promo_summary' => array( 'label' => 'Summary', 'rich' => false ),
						'gloskin_promo_cta_label' => array( 'label' => 'CTA label', 'rich' => false ),
					),
				),
				Gloskin_Site_Core_Content_Service::TESTIMONIAL_POST_TYPE => array(
					'label' => 'Testimonial', 'fields' => $base,
					'meta' => array(
						'gloskin_testimonial_attribution' => array( 'label' => 'Attribution', 'rich' => false ),
						'gloskin_testimonial_subtitle' => array( 'label' => 'Subtitle', 'rich' => false ),
						'gloskin_testimonial_source_note' => array( 'label' => 'Source note', 'rich' => false ),
					),
				),
				Gloskin_Site_Core_Content_Service::ACHIEVEMENT_POST_TYPE => array(
					'label' => 'Achievement', 'fields' => $base,
					'meta' => array( 'gloskin_achievement_issuer' => array( 'label' => 'Issuer', 'rich' => false ) ),
				),
				'product' => array( 'label' => 'Product', 'fields' => $base, 'meta' => array() ),
				Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE => array(
					'label' => 'Consultation', 'fields' => array( 'post_title' => 'Question' ), 'meta' => array(),
				),
			),
			'taxonomies' => array(
				'product_cat' => array( 'label' => 'Product category', 'fields' => array( 'name' => 'Name', 'description' => 'Description' ) ),
				Gloskin_Site_Core_Content_Service::CONCERN_TAXONOMY => array( 'label' => 'Concern', 'fields' => array( 'name' => 'Name', 'description' => 'Description' ) ),
				Gloskin_Site_Core_Content_Service::CONSULTATION_TAXONOMY => array( 'label' => 'Consultation path', 'fields' => array( 'name' => 'Name', 'description' => 'Description' ) ),
			),
			'interface' => self::interface_registry(),
		);
		return self::$registry_cache;
	}

	/** Small Gloskin-owned frontend interface registry. Built once per request. */
	public static function interface_registry() {
		if ( null !== self::$interface_registry_cache ) { return self::$interface_registry_cache; }
		$pairs = array(
			'home' => array( 'Beranda', 'Home' ),
			'about' => array( 'Tentang Kami', 'About Us' ),
			'treatments' => array( 'Perawatan', 'Treatments' ),
			'primary_treatment' => array( 'Treatment', 'Treatments' ),
			'promos' => array( 'Promo', 'Promotions' ),
			'clinics' => array( 'Klinik', 'Clinics' ),
			'doctors' => array( 'Dokter', 'Doctors' ),
			'contact' => array( 'Hubungi Kami', 'Contact Us' ),
			'featured_treatment' => array( 'Treatment Unggulan', 'Featured Treatments' ),
			'limited_promo' => array( 'Promo Terbatas', 'Limited Promotion' ),
			'promo_poster' => array( 'Promo Poster', 'Promotion Posters' ),
			'why_gloskin' => array( 'Kenapa Memilih GLOSKIN', 'Why Choose GLOSKIN' ),
			'why_discovery' => array( 'Temukan pilihan perawatan berdasarkan keluhan dan kondisi kulit — bukan label generik.', 'Explore treatment options based on concerns and skin condition rather than generic labels.' ),
			'why_ecosystem' => array( 'Perawatan klinik dan produk skincare Gloskin dirancang dalam satu ekosistem yang saling melengkapi.', 'Gloskin clinic treatments and skincare products are designed as one complementary ecosystem.' ),
			'why_doctors' => array( 'Tim dokter Gloskin tersedia di jaringan klinik untuk konsultasi dan perencanaan perawatan.', 'Gloskin doctors are available across the clinic network for consultation and treatment planning.' ),
			'testimonials' => array( 'Testimoni', 'Testimonials' ),
			'certificates' => array( 'Piagam', 'Certificates' ),
			'about_gloskin' => array( 'Tentang GLOSKIN', 'About GLOSKIN' ),
			'principles' => array( 'Visi · Misi · Nilai', 'Vision · Mission · Values' ),
			'choose_focus' => array( 'Pilih fokus utama Anda', 'Choose your main focus' ),
			'view_all_products' => array( 'Lihat Semua Produk', 'View All Products' ),
			'all_products' => array( 'Semua Produk', 'All Products' ),
			'search' => array( 'Pencarian', 'Search' ),
			'price' => array( 'Harga', 'Price' ),
			'category' => array( 'Kategori', 'Category' ),
			'story' => array( 'Cerita Gloskin', 'Gloskin Story' ),
			'founder' => array( 'Pendiri', 'Founder' ),
			'vision' => array( 'Visi', 'Vision' ),
			'mission' => array( 'Misi', 'Mission' ),
			'values' => array( 'Nilai', 'Values' ),
			'doctor_team' => array( 'Tim Dokter', 'Doctor Team' ),
			'clinic_network' => array( 'Jaringan Klinik', 'Clinic Network' ),
			'next_step' => array( 'Langkah Berikutnya', 'Next Step' ),
			'choose_clinic' => array( 'Pilih Klinik', 'Choose Clinic' ),
			'skip_main' => array( 'Lewati ke konten utama', 'Skip to main content' ),
			'home_label' => array( 'Beranda Gloskin', 'Gloskin Home' ),
			'primary_navigation' => array( 'Navigasi utama', 'Primary navigation' ),
			'search_action' => array( 'Cari', 'Search' ),
			'my_account' => array( 'Akun saya', 'My account' ),
			'sign_in' => array( 'Masuk', 'Sign in' ),
			'favorite_products' => array( 'Produk favorit', 'Favorite products' ),
			'favorite_products_title' => array( 'Produk Favorit', 'Favorite Products' ),
			'favorite_products_count' => array( '%d produk favorit', '%d favorite products' ),
			'cart_items_count' => array( '%d item di keranjang', '%d items in cart' ),
			'cart' => array( 'Keranjang', 'Cart' ),
			'choose_language' => array( 'Pilih bahasa', 'Choose language' ),
			'indonesian_language' => array( 'Bahasa Indonesia', 'Indonesian' ),
			'open_navigation' => array( 'Buka navigasi', 'Open navigation' ),
			'close_navigation' => array( 'Tutup navigasi', 'Close navigation' ),
			'mobile_navigation' => array( 'Navigasi seluler', 'Mobile navigation' ),
			'open_submenu' => array( 'Buka submenu %s', 'Open %s submenu' ),
			'gloskin_search' => array( 'Pencarian Gloskin', 'Gloskin Search' ),
			'search_placeholder' => array( 'Cari perawatan, klinik, dokter, atau produk', 'Search treatments, clinics, doctors, or products' ),
			'clear_search' => array( 'Hapus pencarian', 'Clear search' ),
			'close_search' => array( 'Tutup pencarian', 'Close search' ),
			'cancel' => array( 'Batal', 'Cancel' ),
			'search_ellipsis' => array( 'Cari...', 'Search...' ),
			'close_cart' => array( 'Tutup keranjang', 'Close cart' ),
			'close_favorites' => array( 'Tutup favorit', 'Close favorites' ),
			'contact_gloskin' => array( 'Hubungi Gloskin', 'Contact Gloskin' ),
			'open_page' => array( 'Buka halaman', 'Open page' ),
			'scroll_next' => array( 'Gulir ke konten berikutnya', 'Scroll to the next section' ),
			'promo_navigation' => array( 'Navigasi promo', 'Promotion navigation' ),
			'promo_previous' => array( 'Promo sebelumnya', 'Previous promotion' ),
			'promo_next' => array( 'Promo berikutnya', 'Next promotion' ),
			'promo_choose' => array( 'Pilih promo', 'Choose promotion' ),
			'promo_number' => array( 'Promo %d', 'Promotion %d' ),
			'promo_position' => array( 'Promo %1$d dari %2$d', 'Promotion %1$d of %2$d' ),
			'promo_gloskin' => array( 'Promo Gloskin', 'Gloskin Promotion' ),
			'view_promo' => array( 'Lihat Promo', 'View Promotion' ),
			'promo_unavailable' => array( 'Informasi promo belum tersedia.', 'Promotion information is not available yet.' ),
			'open_promo_page' => array( 'Buka halaman Promo', 'Open Promotions page' ),
			'promo_media' => array( 'Media promo', 'Promotion media' ),
			'campaign' => array( 'Kampanye', 'Campaign' ),
			'choose_promo_poster' => array( 'Pilih poster promo', 'Choose promotion poster' ),
			'promo_poster_position' => array( 'Poster %1$d: %2$s', 'Poster %1$d: %2$s' ),
			'view_detail' => array( 'Lihat Detail', 'View Details' ),
			'view_detail_named' => array( 'Lihat detail %s', 'View details for %s' ),
			'view_product' => array( 'Lihat Produk', 'View Product' ),
			'add_favorite' => array( 'Simpan %s ke favorit', 'Save %s to favorites' ),
			'remove_favorite' => array( 'Hapus %s dari favorit', 'Remove %s from favorites' ),
			'skincare_category_nav' => array( 'Kategori skincare', 'Skincare categories' ),
			'choose_variant' => array( 'Pilih Varian', 'Choose Variant' ),
			'close_variant_picker' => array( 'Tutup pilih varian', 'Close variant selector' ),
			'loading' => array( 'Memuat…', 'Loading…' ),
			'treatment_finder_title' => array( 'Temukan Perawatan yang Tepat', 'Find the Right Treatment' ),
			'treatment_finder_copy' => array( 'Pilih fokus dan keluhan yang ingin Anda eksplorasi sebelum melanjutkan ke detail perawatan.', 'Choose the focus and concerns you want to explore before viewing treatment details.' ),
			'choose_treatment_focus' => array( 'Pilih fokus perawatan', 'Choose treatment focus' ),
			'concern_prompt' => array( 'Apa yang paling ingin Anda perbaiki?', 'What would you most like to improve?' ),
			'multi_concern' => array( 'Anda dapat memilih lebih dari satu keluhan.', 'You can select more than one concern.' ),
			'find_right_treatment' => array( 'Cari Perawatan yang Tepat', 'Find the Right Treatment' ),
			'treatment_recommendations' => array( 'Rekomendasi Perawatan', 'Treatment Recommendations' ),
			'no_matching_treatment_products' => array( 'Belum ada produk yang cocok dengan keluhan pilihan Anda. Hubungi kami untuk konsultasi lebih lanjut.', 'No products match your selected concerns yet. Contact us for further consultation.' ),
			'consultation' => array( 'Konsultasi', 'Consultation' ),
			'site_info_prepare' => array( 'Informasi di situs membantu menyiapkan pertanyaan sebelum konsultasi.', 'Website information can help you prepare questions before a consultation.' ),
			'site_info_guide' => array( 'Gunakan informasi ini sebagai panduan awal, lalu pilih klinik atau hubungi Gloskin untuk melanjutkan konsultasi melalui kanal yang tersedia.', 'Use this information as an initial guide, then choose a clinic or contact Gloskin to continue through an available consultation channel.' ),
			'benefits' => array( 'Manfaat', 'Benefits' ),
			'contraindications' => array( 'Kontraindikasi', 'Contraindications' ),
			'consider' => array( 'Untuk dipertimbangkan', 'Things to Consider' ),
			'use_info_before_clinic' => array( 'Gunakan informasi yang tersedia sebagai bahan sebelum berbicara dengan klinik.', 'Use the available information as a reference before speaking with the clinic.' ),
			'note_questions' => array( 'Catat pertanyaan yang ingin Anda bahas dan gunakan kanal konsultasi Gloskin untuk mendapatkan informasi lebih lanjut sesuai kebutuhan Anda.', 'Note the questions you want to discuss and use Gloskin consultation channels for information relevant to your needs.' ),
			'open_contact' => array( 'Buka Kontak', 'Open Contact' ),
			'related_clinics' => array( 'Klinik Terkait', 'Related Clinics' ),
			'related_clinics_copy' => array( 'Lihat lokasi Gloskin yang terkait dengan perawatan ini.', 'View Gloskin locations related to this treatment.' ),
			'related_doctors' => array( 'Dokter Terkait', 'Related Doctors' ),
			'other_treatments' => array( 'Informasi Perawatan Lain', 'Other Treatment Information' ),
			'other_treatments_copy' => array( 'Buka halaman lain bila Anda masih membandingkan informasi yang tersedia.', 'Open another page if you are still comparing the available information.' ),
			'discuss_questions' => array( 'Bicarakan pertanyaan yang belum terjawab melalui kanal Gloskin.', 'Discuss unanswered questions through Gloskin channels.' ),
			'use_consultation_path' => array( 'Gunakan jalur konsultasi yang tersedia untuk perawatan ini, atau lanjutkan ke halaman kontak.', 'Use the consultation path available for this treatment, or continue to the contact page.' ),
			'continue_consultation' => array( 'Lanjutkan Konsultasi', 'Continue Consultation' ),
			'all_treatments' => array( 'Semua Perawatan', 'All Treatments' ),
			'treatment_band_copy' => array( 'Temukan pilihan perawatan yang relevan dan diskusikan dengan dokter Gloskin saat konsultasi.', 'Explore relevant treatment options and discuss them with a Gloskin doctor during consultation.' ),
			'explore_solutions' => array( 'Jelajahi Solusi', 'Explore Solutions' ),
			'shop_gloskin' => array( 'BELANJA GLOSKIN', 'SHOP GLOSKIN' ),
			'complete_skincare' => array( 'Lengkapi rutinitas skincare Anda.', 'Complete your skincare routine.' ),
			'explore_collection' => array( 'Jelajahi seluruh koleksi, lihat detail produk, harga, dan pilihan yang tersedia di halaman Belanja.', 'Explore the full collection, product details, prices, and available options on the Shop page.' ),
			'available_products' => array( 'Produk yang Tersedia', 'Available Products' ),
			'available_products_copy' => array( 'Lihat detail, harga, dan cara membeli setiap produk.', 'View details, prices, and how to buy each product.' ),
			'filter_products_category' => array( 'Filter produk berdasarkan kategori', 'Filter products by category' ),
			'all' => array( 'Semua', 'All' ),
			'skincare_categories' => array( 'Kategori Skincare', 'Skincare Categories' ),
			'skincare_categories_copy' => array( 'Jelajahi halaman kategori untuk produk detail dan konteks perawatan per kebutuhan kulit.', 'Explore category pages for product details and skincare context by skin need.' ),
			'ask_more' => array( 'Ingin bertanya lebih lanjut?', 'Want to ask more?' ),
			'choose_location_contact' => array( 'Pilih lokasi Gloskin dan lihat kanal kontak yang tersedia.', 'Choose a Gloskin location and view the available contact channels.' ),
			'view_clinic' => array( 'Lihat Klinik', 'View Clinic' ),
			'category_products' => array( 'Produk dalam Kategori Ini', 'Products in This Category' ),
			'category_products_copy' => array( 'Buka produk untuk melihat detail dan cara membelinya.', 'Open a product to view details and how to buy it.' ),
			'catalog_unavailable' => array( 'Katalog produk belum tersedia', 'Product catalog is not available yet' ),
			'catalog_category_unavailable' => array( 'Katalog produk belum tersedia untuk kategori ini.', 'The product catalog is not available for this category yet.' ),
			'back_skincare' => array( 'Kembali ke Skincare', 'Back to Skincare' ),
			'category_empty' => array( 'Belum ada produk pada kategori ini', 'No products in this category yet' ),
			'category_empty_copy' => array( 'Coba kategori skincare lain atau lihat seluruh katalog yang tersedia.', 'Try another skincare category or view the full available catalog.' ),
			'other_categories' => array( 'Kategori Lain untuk Dilihat', 'Other Categories to Explore' ),
			'other_categories_copy' => array( 'Lihat kategori skincare lain yang tersedia.', 'View other available skincare categories.' ),
			'skincare_label' => array( 'Skincare', 'Skincare' ),
			'continue_categories' => array( 'Lanjutkan ke kategori lain atau lihat seluruh produk.', 'Continue to another category or view all products.' ),
			'choose_next_path' => array( 'Pilih jalur yang paling sesuai dengan apa yang ingin Anda lihat berikutnya.', 'Choose the path that best matches what you want to view next.' ),
			'all_categories' => array( 'Semua Kategori', 'All Categories' ),
			'open_shop' => array( 'Buka Belanja', 'Open Shop' ),
			'price_unavailable' => array( 'Harga belum tersedia', 'Price unavailable' ),
			'product_filters' => array( 'Penyaring produk', 'Product filters' ),
			'search_products' => array( 'Cari produk', 'Search products' ),
			'shop_search_placeholder' => array( 'Cari produk, SKU, atau kebutuhan kulit…', 'Search products, SKU, or skin needs…' ),
			'minimum_price' => array( 'Harga minimum', 'Minimum price' ),
			'maximum_price' => array( 'Harga maksimum', 'Maximum price' ),
			'reset_price' => array( 'Reset harga', 'Reset price' ),
			'product_categories' => array( 'Kategori produk', 'Product categories' ),
			'clear_all_filters' => array( 'Hapus semua filter', 'Clear all filters' ),
			'product_count' => array( '%d produk', '%d products' ),
			'product_pagination' => array( 'Navigasi halaman produk', 'Product page navigation' ),
			'previous_product_page' => array( 'Halaman produk sebelumnya', 'Previous product page' ),
			'product_page_number' => array( 'Halaman produk %d', 'Product page %d' ),
			'next_product_page' => array( 'Halaman produk berikutnya', 'Next product page' ),
			'shop_unavailable' => array( 'Belanja belum tersedia', 'Shop is not available yet' ),
			'shop_catalog_unavailable' => array( 'Katalog produk belum tersedia pada situs ini.', 'The product catalog is not available on this site yet.' ),
			'view_skincare' => array( 'Lihat Skincare', 'View Skincare' ),
			'product_search_empty' => array( 'Produk tidak ditemukan untuk "%s"', 'No products found for "%s"' ),
			'product_filter_empty' => array( 'Produk tidak ditemukan dengan filter ini', 'No products found with these filters' ),
			'broaden_search' => array( 'Coba kata lain atau perluas rentang harga.', 'Try another term or broaden the price range.' ),
			'reset_search' => array( 'Reset pencarian', 'Reset search' ),
			'no_products' => array( 'Belum ada produk yang dapat ditampilkan', 'No products are available to display yet' ),
			'products_appear' => array( 'Produk akan tampil di sini setelah item tersedia dalam katalog.', 'Products will appear here once items are available in the catalog.' ),
			'clinic_map' => array( 'Peta %s', '%s map' ),
			'clinic_gloskin' => array( 'Klinik Gloskin', 'Gloskin Clinic' ),
			'clinic_branch_info' => array( 'Lihat informasi cabang dan kanal kontak yang tersedia.', 'View branch information and available contact channels.' ),
			'clinic_info' => array( 'Informasi Klinik', 'Clinic Information' ),
			'address' => array( 'Alamat', 'Address' ),
			'phone' => array( 'Telepon', 'Phone' ),
			'operating_hours' => array( 'Jam Operasional', 'Operating Hours' ),
			'whatsapp_contact' => array( 'Hubungi via WhatsApp', 'Contact via WhatsApp' ),
			'open_map' => array( 'Buka Peta', 'Open Map' ),
			'branch_info' => array( 'Informasi Cabang', 'Branch Information' ),
			'branch_details_missing' => array( 'Detail cabang belum tersedia untuk ditampilkan.', 'Branch details are not available to display yet.' ),
			'branch_details_help' => array( 'Anda dapat kembali ke jaringan klinik atau menggunakan halaman kontak untuk mendapatkan informasi lebih lanjut.', 'You can return to the clinic network or use the contact page for more information.' ),
			'all_clinics' => array( 'Semua Klinik', 'All Clinics' ),
			'doctors_here' => array( 'Dokter di Klinik Ini', 'Doctors at This Clinic' ),
			'related_treatments' => array( 'Perawatan Terkait', 'Related Treatments' ),
			'contact_branch' => array( 'Hubungi Cabang', 'Contact Branch' ),
			'available_channel' => array( 'Gunakan kanal yang tersedia untuk melanjutkan.', 'Use an available channel to continue.' ),
			'branch_whatsapp_help' => array( 'Hubungi cabang melalui WhatsApp bila tersedia, atau gunakan halaman kontak Gloskin untuk pertanyaan lebih lanjut.', 'Contact the branch through WhatsApp when available, or use the Gloskin contact page for further questions.' ),
			'clinic_whatsapp' => array( 'WhatsApp Klinik', 'Clinic WhatsApp' ),
			'choose_branch' => array( 'Pilih cabang Gloskin yang ingin Anda lihat lebih dekat.', 'Choose the Gloskin branch you want to explore.' ),
			'branch_detail_scope' => array( 'Buka halaman cabang untuk melihat alamat, jam operasional, peta, galeri, dan kanal kontak yang tersedia.', 'Open a branch page to view its address, operating hours, map, gallery, and available contact channels.' ),
			'choose_gloskin_location' => array( 'Pilih Lokasi Gloskin', 'Choose a Gloskin Location' ),
			'branch_contact_copy' => array( 'Buka halaman cabang untuk melihat informasi lokasi dan kontak yang tersedia.', 'Open a branch page to view available location and contact information.' ),
			'no_clinics' => array( 'Belum ada klinik yang dapat ditampilkan', 'No clinics are available to display yet' ),
			'clinics_appear' => array( 'Informasi lokasi akan tampil di sini setelah data klinik dipublikasikan.', 'Location information will appear here after clinic data is published.' ),
			'available_doctors' => array( 'Profil Dokter yang Tersedia', 'Available Doctor Profiles' ),
			'doctor_profile_location_copy' => array( 'Buka profil untuk melihat informasi profesional dan lokasi praktik.', 'Open a profile to view professional information and practice locations.' ),
			'treatment_info' => array( 'Informasi Perawatan', 'Treatment Information' ),
			'contact_label' => array( 'Kontak', 'Contact' ),
			'branch_decided' => array( 'Sudah menentukan cabang yang ingin dihubungi?', 'Have you decided which branch to contact?' ),
			'branch_continue' => array( 'Gunakan detail cabang atau halaman kontak untuk melanjutkan melalui kanal yang tersedia.', 'Use branch details or the contact page to continue through an available channel.' ),
			'view_treatments' => array( 'Lihat Perawatan', 'View Treatments' ),
			'contact_location_title' => array( 'Pilih Lokasi yang Ingin Dihubungi', 'Choose a Location to Contact' ),
			'contact_location_copy' => array( 'Buka detail cabang untuk melihat kanal kontak yang sudah tersedia.', 'Open branch details to view the contact channels already available.' ),
			'no_clinic_details' => array( 'Belum ada detail klinik yang dapat ditampilkan', 'No clinic details are available to display yet' ),
			'contact_form_fallback' => array( 'Anda tetap dapat menggunakan formulir kontak di bawah bila tersedia.', 'You can still use the contact form below when available.' ),
			'send_message' => array( 'Kirim Pesan', 'Send a Message' ),
			'send_message_copy' => array( 'Gunakan formulir ini untuk mengirim pertanyaan kepada Gloskin.', 'Use this form to send a question to Gloskin.' ),
			'doctor_gloskin' => array( 'Dokter Gloskin', 'Gloskin Doctor' ),
			'profile' => array( 'Profil', 'Profile' ),
			'credentials' => array( 'Kredensial', 'Credentials' ),
			'schedule' => array( 'Jadwal', 'Schedule' ),
			'professional_profile' => array( 'Profil Profesional', 'Professional Profile' ),
			'details_missing' => array( 'Detail tambahan belum tersedia untuk ditampilkan.', 'Additional details are not available to display yet.' ),
			'doctor_sparse_help' => array( 'Anda tetap dapat melihat jaringan klinik atau menggunakan halaman kontak untuk menentukan langkah berikutnya.', 'You can still view the clinic network or use the contact page to decide your next step.' ),
			'practice_locations' => array( 'Lokasi Praktik', 'Practice Locations' ),
			'practice_locations_copy' => array( 'Lihat cabang tempat dokter ini berpraktik.', 'View the branches where this doctor practices.' ),
			'related_treatment_profile_copy' => array( 'Lihat informasi perawatan yang terkait dengan profil ini.', 'View treatment information related to this profile.' ),
			'consultation_available_path' => array( 'Lanjutkan melalui jalur konsultasi yang tersedia.', 'Continue through an available consultation path.' ),
			'doctor_consult_help' => array( 'Buka tujuan konsultasi pada profil ini atau gunakan halaman kontak Gloskin untuk informasi lebih lanjut.', 'Open the consultation destination on this profile or use the Gloskin contact page for more information.' ),
			'doctor_intro' => array( 'Kenali dokter Gloskin melalui profil yang tersedia.', 'Get to know Gloskin doctors through the available profiles.' ),
			'doctor_intro_copy' => array( 'Buka profil untuk melihat gelar, spesialisasi, lokasi praktik, dan informasi profesional yang tersedia.', 'Open a profile to view degrees, specialization, practice locations, and available professional information.' ),
			'view_clinic_network' => array( 'Lihat Jaringan Klinik', 'View Clinic Network' ),
			'doctor_profiles' => array( 'Profil Dokter', 'Doctor Profiles' ),
			'no_doctor_profiles' => array( 'Belum ada profil dokter yang dapat ditampilkan', 'No doctor profiles are available to display yet' ),
			'doctor_profiles_appear' => array( 'Profil dokter akan tampil di sini setelah dipublikasikan.', 'Doctor profiles will appear here after they are published.' ),
			'location' => array( 'Lokasi', 'Location' ),
			'choose_clinic_first' => array( 'Pilih klinik lebih dulu', 'Choose a clinic first' ),
			'location_priority' => array( 'Buka jaringan klinik bila lokasi menjadi pertimbangan utama Anda.', 'Open the clinic network if location is your main consideration.' ),
			'open_clinic' => array( 'Buka Klinik', 'Open Clinic' ),
			'prepare_questions' => array( 'Siapkan pertanyaan untuk tim klinik', 'Prepare questions for the clinic team' ),
			'contact_questions' => array( 'Gunakan halaman kontak untuk menanyakan jadwal, lokasi, atau informasi lain yang Anda perlukan.', 'Use the contact page to ask about schedules, locations, or other information you need.' ),
			'choose_profile_or_location' => array( 'Pilih jalur berdasarkan profil atau lokasi yang tersedia.', 'Choose a path based on an available profile or location.' ),
			'open_branch_or_contact' => array( 'Buka cabang yang relevan atau hubungi Gloskin untuk informasi lebih lanjut.', 'Open a relevant branch or contact Gloskin for more information.' ),
			'insight_pagination' => array( 'Navigasi halaman insight', 'Insight page navigation' ),
			'no_articles' => array( 'Belum ada artikel yang dipublikasikan', 'No articles have been published yet' ),
			'articles_appear' => array( 'Artikel dan pembaruan Gloskin akan tampil di sini setelah tersedia.', 'Gloskin articles and updates will appear here when available.' ),
			'open_treatments' => array( 'Buka Perawatan', 'Open Treatments' ),
			'article_unavailable' => array( 'Artikel tidak tersedia', 'Article unavailable' ),
			'article_not_displayed' => array( 'Artikel ini belum dapat ditampilkan.', 'This article cannot be displayed yet.' ),
			'back_insight' => array( 'Kembali ke Insight', 'Back to Insights' ),
			'continue_reading' => array( 'Lanjut membaca', 'Continue reading' ),
			'related_articles' => array( 'Artikel terkait', 'Related Articles' ),
			'read_article' => array( 'Baca artikel', 'Read article' ),
			'footer_consult_title' => array( 'Pilih klinik Gloskin terdekat dan mulai konsultasi.', 'Choose your nearest Gloskin clinic and start a consultation.' ),
			'footer_consult_copy' => array( 'Temukan cabang yang sesuai dengan lokasi Anda, lalu hubungi tim Gloskin untuk informasi jadwal dan konsultasi yang tersedia.', 'Find a clinic that suits your location, then contact the Gloskin team for available schedules and consultations.' ),
			'footer_brand_copy' => array( 'Gloskin adalah klinik estetika, anti-aging, dan perawatan rambut yang mengedepankan konsultasi dan penanganan dokter di setiap kliniknya.', 'Gloskin is an aesthetics, anti-aging, and hair-care clinic focused on consultation and doctor-led care at every clinic.' ),
			'services' => array( 'Layanan', 'Services' ),
			'shop' => array( 'Belanja', 'Shop' ),
			'information' => array( 'Informasi', 'Information' ),
			'about_short' => array( 'Tentang', 'About' ),
			'insight' => array( 'Insight', 'Insights' ),
			'contact_short' => array( 'Kontak', 'Contact' ),
			'not_found_title' => array( 'Halaman Gloskin ini tidak tersedia', 'This Gloskin page is unavailable' ),
			'not_found_copy' => array( 'Gunakan navigasi utama untuk melanjutkan ke halaman lain.', 'Use the primary navigation to continue to another page.' ),
			'back_home' => array( 'Kembali ke Beranda', 'Back to Home' ),
			'not_found_live_title' => array( 'Halaman ini tidak ditemukan', 'Page not found' ),
			'not_found_live_copy' => array( 'Tautan mungkin sudah berubah atau alamatnya belum tepat. Anda tetap dapat melanjutkan dari halaman utama atau membaca Insight terbaru.', 'The link may have changed or the address may be incorrect. You can continue from the home page or read the latest Insights.' ),
			'open_insight' => array( 'Buka Insight', 'Open Insights' ),
			'page_choices' => array( 'Pilihan halaman Gloskin', 'Gloskin page options' ),
		);
		$out = array();
		foreach ( $pairs as $key => $pair ) { $out[ $key ] = array( 'source' => $pair[0], 'en' => $pair[1] ); }
		self::$interface_registry_cache = $out;
		return self::$interface_registry_cache;
	}

	/**
	 * One canonical O(1) source-text → resolved EN lookup, built once per request.
	 *
	 * Both transport owners — the gettext filter (Language::translate_interface)
	 * and the HTML output buffer (Language_Projection::translate_text_segment) —
	 * delegate here so there is exactly one resolver and one build per request.
	 * O(1) associative lookup per visible string replaces O(n) foreach scans.
	 *
	 * @return array<string,string> Map: canonical Indonesian source → resolved EN value.
	 */
	public static function interface_lookup() {
		if ( null !== self::$interface_lookup_cache ) { return self::$interface_lookup_cache; }
		$registry = self::interface_registry();
		$saved    = self::interface_translations();
		self::$interface_lookup_cache = array();
		foreach ( $registry as $key => $entry ) {
			$source = (string) $entry['source'];
			self::$interface_lookup_cache[ $source ] = isset( $saved[ $key ] ) && '' !== trim( (string) $saved[ $key ] )
				? (string) $saved[ $key ]
				: (string) $entry['en'];
		}
		return self::$interface_lookup_cache;
	}

	/** @return void */
	public function register_menu() {
		$hook = add_submenu_page( Gloskin_Site_Core_Content_Service::ADMIN_MENU_SLUG, __( 'Translation', 'gloskin-site-core' ), __( 'Translation', 'gloskin-site-core' ), self::CAPABILITY, self::ADMIN_SLUG, array( $this, 'render_admin_page' ) );
		if ( is_string( $hook ) ) { $this->admin_hook = $hook; }
	}

	/** @param string $hook Current admin hook. */
	public function enqueue_admin_assets( $hook ) {
		if ( '' === $this->admin_hook || $hook !== $this->admin_hook ) { return; }
		$base = plugin_dir_url( $this->plugin_file );
		wp_enqueue_style( 'gloskin-translation-admin', $base . 'assets/css/gloskin-translation-admin.css', array(), $this->version );
		wp_enqueue_script( 'gloskin-translation-admin', $base . 'assets/js/gloskin-translation-admin.js', array(), $this->version, true );
		wp_localize_script( 'gloskin-translation-admin', 'GloskinTranslationAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( self::NONCE_ACTION ),
			'action' => self::AJAX_SAVE,
			'workerUrl' => $base . 'assets/js/gloskin-translation-worker.js?ver=' . rawurlencode( $this->version ),
			'records' => $this->records(),
			'protectedTerms' => $this->protected_terms(),
		) );
	}

	/** @return void */
	public function render_admin_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'You are not allowed to manage translations.', 'gloskin-site-core' ), '', array( 'response' => 403 ) ); }
		?>
		<div class="wrap gloskin-translation" data-gloskin-translation-root>
			<h1><?php echo esc_html__( 'Translation', 'gloskin-site-core' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'Fresh English is served only while it matches the current Indonesian source. Generated stale fields sync automatically here; manual and legacy fields wait for review.', 'gloskin-site-core' ); ?></p>
			<div class="gloskin-translation__controls">
				<input type="search" data-translation-search placeholder="<?php echo esc_attr__( 'Search…', 'gloskin-site-core' ); ?>">
				<select data-translation-type><option value=""><?php echo esc_html__( 'All types', 'gloskin-site-core' ); ?></option></select>
				<label><input type="checkbox" data-translation-missing> <?php echo esc_html__( 'Needs sync', 'gloskin-site-core' ); ?></label>
				<button type="button" class="button button-primary" data-translation-generate><?php echo esc_html__( 'Sync Missing & Changed', 'gloskin-site-core' ); ?></button>
			</div>
			<p class="gloskin-translation__status" data-translation-status role="status" aria-live="polite"></p>
			<table class="widefat striped gloskin-translation__table"><thead><tr><th><?php echo esc_html__( 'Type', 'gloskin-site-core' ); ?></th><th><?php echo esc_html__( 'Record', 'gloskin-site-core' ); ?></th><th><?php echo esc_html__( 'Fresh EN', 'gloskin-site-core' ); ?></th></tr></thead><tbody data-translation-rows></tbody></table>
			<section class="gloskin-translation__editor" data-translation-editor hidden></section>
		</div>
		<?php
	}

	/** Normalize line endings only, then hash the exact canonical source. */
	public static function source_hash( $source ) {
		$normalized = str_replace( array( "\r\n", "\r" ), "\n", (string) $source );
		return hash( 'sha256', $normalized );
	}

	/** @return array<string,string> */
	public static function post_translations( $post_id ) { $value = get_post_meta( absint( $post_id ), self::POST_META_KEY, true ); return is_array( $value ) ? array_map( 'strval', $value ) : array(); }
	/** @return array<string,string> */
	public static function term_translations( $term_id ) { $value = get_term_meta( absint( $term_id ), self::TERM_META_KEY, true ); return is_array( $value ) ? array_map( 'strval', $value ) : array(); }
	/** @return array<string,string> */
	public static function interface_translations() {
		if ( null !== self::$interface_translations_cache ) { return self::$interface_translations_cache; }
		$value = get_option( self::INTERFACE_OPTION, array() );
		self::$interface_translations_cache = is_array( $value ) ? array_map( 'strval', $value ) : array();
		return self::$interface_translations_cache;
	}
	/** @return array<string,array{source_hash:string,origin:string}> */
	public static function post_translation_state( $post_id ) { $value = get_post_meta( absint( $post_id ), self::POST_STATE_META_KEY, true ); return is_array( $value ) ? $value : array(); }
	/** @return array<string,array{source_hash:string,origin:string}> */
	public static function term_translation_state( $term_id ) { $value = get_term_meta( absint( $term_id ), self::TERM_STATE_META_KEY, true ); return is_array( $value ) ? $value : array(); }

	/** Fresh saved post value, otherwise canonical Indonesian source. */
	public static function fresh_post_value( $post_id, $field, $source ) {
		$saved = self::post_translations( $post_id );
		if ( ! isset( $saved[ $field ] ) || '' === trim( (string) $saved[ $field ] ) ) { return (string) $source; }
		$state = self::post_translation_state( $post_id );
		if ( ! isset( $state[ $field ]['source_hash'] ) || ! hash_equals( (string) $state[ $field ]['source_hash'], self::source_hash( $source ) ) ) { return (string) $source; }
		return (string) $saved[ $field ];
	}

	/** Fresh saved term value, otherwise canonical Indonesian source. */
	public static function fresh_term_value( $term_id, $field, $source ) {
		$saved = self::term_translations( $term_id );
		if ( ! isset( $saved[ $field ] ) || '' === trim( (string) $saved[ $field ] ) ) { return (string) $source; }
		$state = self::term_translation_state( $term_id );
		if ( ! isset( $state[ $field ]['source_hash'] ) || ! hash_equals( (string) $state[ $field ]['source_hash'], self::source_hash( $source ) ) ) { return (string) $source; }
		return (string) $saved[ $field ];
	}

	/** @return array{status:string,origin:string} */
	private static function freshness( $entity, $entity_id, $field, $source, $en ) {
		if ( '' === trim( (string) $en ) ) { return array( 'status' => 'missing', 'origin' => '' ); }
		$state = 'term' === $entity ? self::term_translation_state( $entity_id ) : self::post_translation_state( $entity_id );
		$entry = isset( $state[ $field ] ) && is_array( $state[ $field ] ) ? $state[ $field ] : array();
		$origin = isset( $entry['origin'] ) && in_array( $entry['origin'], array( 'generated', 'manual', 'legacy' ), true ) ? (string) $entry['origin'] : 'legacy';
		$fresh = isset( $entry['source_hash'] ) && hash_equals( (string) $entry['source_hash'], self::source_hash( $source ) );
		return array( 'status' => $fresh ? 'fresh' : 'stale', 'origin' => $origin );
	}

	/** One-time state bootstrap for existing translations; values are never changed. */
	public function bootstrap_legacy_freshness() {
		if ( ! current_user_can( self::CAPABILITY ) || '1' === (string) get_option( self::BOOTSTRAP_OPTION, '' ) ) { return; }
		$registry = self::registry();
		foreach ( $registry['post_types'] as $post_type => $definition ) {
			if ( ! post_type_exists( $post_type ) ) { continue; }
			$ids = get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true ) );
			foreach ( $ids as $post_id ) { $this->bootstrap_post_state( absint( $post_id ), $definition ); }
		}
		foreach ( $registry['taxonomies'] as $taxonomy => $definition ) {
			if ( ! taxonomy_exists( $taxonomy ) ) { continue; }
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
			if ( is_wp_error( $terms ) ) { continue; }
			foreach ( $terms as $term ) { $this->bootstrap_term_state( $term, $definition ); }
		}
		update_option( self::BOOTSTRAP_OPTION, '1', false );
	}

	/** @param int $post_id Post ID. @param array<string,mixed> $definition Definition. */
	private function bootstrap_post_state( $post_id, $definition ) {
		$post = get_post( $post_id ); if ( ! $post ) { return; }
		$saved = self::post_translations( $post_id ); if ( ! $saved ) { return; }
		$state = self::post_translation_state( $post_id ); $changed = false;
		foreach ( $definition['fields'] as $field => $label ) { unset( $label ); $source = isset( $post->$field ) ? (string) $post->$field : ''; $changed = $this->bootstrap_state_field( $state, $saved, $field, $source ) || $changed; }
		foreach ( $definition['meta'] as $field => $meta_definition ) { unset( $meta_definition ); $source = (string) get_post_meta( $post_id, $field, true ); $changed = $this->bootstrap_state_field( $state, $saved, $field, $source ) || $changed; }
		if ( Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE === $post->post_type ) {
			$answers = get_post_meta( $post_id, Gloskin_Site_Core_Content_Service::ANSWER_META_KEY, true );
			if ( is_array( $answers ) ) { foreach ( $answers as $index => $answer ) { if ( ! is_array( $answer ) || ! isset( $answer['label'] ) ) { continue; } $field = 'answer_label_' . absint( $index ); $changed = $this->bootstrap_state_field( $state, $saved, $field, (string) $answer['label'] ) || $changed; } }
		}
		if ( $changed ) { update_post_meta( $post_id, self::POST_STATE_META_KEY, $state ); }
	}

	/** @param WP_Term $term Term. @param array<string,mixed> $definition Definition. */
	private function bootstrap_term_state( $term, $definition ) {
		$saved = self::term_translations( $term->term_id ); if ( ! $saved ) { return; }
		$state = self::term_translation_state( $term->term_id ); $changed = false;
		foreach ( $definition['fields'] as $field => $label ) { unset( $label ); $source = isset( $term->$field ) ? (string) $term->$field : ''; $changed = $this->bootstrap_state_field( $state, $saved, $field, $source ) || $changed; }
		if ( $changed ) { update_term_meta( $term->term_id, self::TERM_STATE_META_KEY, $state ); }
	}

	/** @param array<string,mixed> $state State. @param array<string,string> $saved Saved values. */
	private function bootstrap_state_field( &$state, $saved, $field, $source ) {
		if ( ! isset( $saved[ $field ] ) || '' === trim( (string) $saved[ $field ] ) || isset( $state[ $field ]['source_hash'] ) ) { return false; }
		$state[ $field ] = array( 'source_hash' => self::source_hash( $source ), 'origin' => 'legacy' ); return true;
	}

	/** Discover dynamic records while field definitions remain explicit. */
	private function records() {
		$registry = self::registry(); $records = array();
		foreach ( $registry['post_types'] as $post_type => $definition ) {
			if ( ! post_type_exists( $post_type ) ) { continue; }
			$posts = get_posts( array( 'post_type' => $post_type, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) );
			foreach ( $posts as $post ) {
				$fields = array(); $saved = self::post_translations( $post->ID );
				foreach ( $definition['fields'] as $field => $label ) { $source = isset( $post->$field ) ? (string) $post->$field : ''; if ( '' === trim( wp_strip_all_tags( $source ) ) ) { continue; } $fields[] = $this->field_payload( 'post', $post->ID, $field, $label, $source, isset( $saved[ $field ] ) ? $saved[ $field ] : '', 'post_content' === $field ); }
				foreach ( $definition['meta'] as $field => $meta_definition ) { $source = (string) get_post_meta( $post->ID, $field, true ); if ( '' === trim( wp_strip_all_tags( $source ) ) ) { continue; } $fields[] = $this->field_payload( 'post', $post->ID, $field, $meta_definition['label'], $source, isset( $saved[ $field ] ) ? $saved[ $field ] : '', ! empty( $meta_definition['rich'] ) ); }
				if ( Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE === $post_type ) { $answers = get_post_meta( $post->ID, Gloskin_Site_Core_Content_Service::ANSWER_META_KEY, true ); if ( is_array( $answers ) ) { foreach ( $answers as $index => $answer ) { if ( ! is_array( $answer ) || empty( $answer['label'] ) ) { continue; } $key = 'answer_label_' . absint( $index ); $fields[] = $this->field_payload( 'post', $post->ID, $key, sprintf( 'Answer %d', absint( $index ) + 1 ), (string) $answer['label'], isset( $saved[ $key ] ) ? $saved[ $key ] : '', false ); } } }
				if ( $fields ) { $records[] = $this->record_payload( 'post', $post->ID, $definition['label'], (string) $post->post_title, $fields ); }
			}
		}
		foreach ( $registry['taxonomies'] as $taxonomy => $definition ) {
			if ( ! taxonomy_exists( $taxonomy ) ) { continue; }
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ); if ( is_wp_error( $terms ) ) { continue; }
			foreach ( $terms as $term ) { $saved = self::term_translations( $term->term_id ); $fields = array(); foreach ( $definition['fields'] as $field => $label ) { $source = isset( $term->$field ) ? (string) $term->$field : ''; if ( '' === trim( wp_strip_all_tags( $source ) ) ) { continue; } $fields[] = $this->field_payload( 'term', $term->term_id, $field, $label, $source, isset( $saved[ $field ] ) ? $saved[ $field ] : '', 'description' === $field ); } if ( $fields ) { $records[] = $this->record_payload( 'term', $term->term_id, $definition['label'], $term->name, $fields, $taxonomy ); } }
		}
		$saved_interface = self::interface_translations();
		foreach ( $registry['interface'] as $key => $entry ) { $en = isset( $saved_interface[ $key ] ) && '' !== trim( (string) $saved_interface[ $key ] ) ? (string) $saved_interface[ $key ] : (string) $entry['en']; $records[] = $this->record_payload( 'interface', $key, 'Interface', $entry['source'], array( array( 'key' => 'text', 'label' => 'Text', 'source' => $entry['source'], 'en' => $en, 'rich' => false, 'status' => 'fresh', 'origin' => 'generated' ) ) ); }
		return $records;
	}

	/** @return array<string,string> */
	private function protected_terms() {
		$terms = array( 'Gloskin', 'Skinvive', 'Botox' );
		foreach ( array( Gloskin_Site_Core_Content_Service::TREATMENT_POST_TYPE, 'product' ) as $type ) { if ( ! post_type_exists( $type ) ) { continue; } $names = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true ) ); foreach ( $names as $id ) { $name = get_post_field( 'post_title', $id, 'raw' ); if ( is_string( $name ) && '' !== trim( $name ) ) { $terms[] = trim( $name ); } } }
		return array_values( array_unique( $terms ) );
	}

	/** One save endpoint: manual and generated saves both refresh source_hash. */
	public function ajax_save() {
		if ( ! current_user_can( self::CAPABILITY ) ) { wp_send_json_error( array( 'message' => 'Forbidden.' ), 403 ); }
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$entity = isset( $_POST['entity'] ) ? sanitize_key( wp_unslash( $_POST['entity'] ) ) : '';
		$field = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$id_raw = isset( $_POST['entity_id'] ) ? wp_unslash( $_POST['entity_id'] ) : '';
		$value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';
		$origin = isset( $_POST['origin'] ) && 'generated' === sanitize_key( wp_unslash( $_POST['origin'] ) ) ? 'generated' : 'manual';
		if ( ! is_string( $value ) || ! $this->field_is_allowed( $entity, $id_raw, $field ) ) { wp_send_json_error( array( 'message' => 'Invalid translation target.' ), 400 ); }
		$value = $this->sanitize_translation( $entity, $field, $value );
		$source = $this->current_source( $entity, $id_raw, $field );
		if ( null === $source ) { wp_send_json_error( array( 'message' => 'Translation source is unavailable.' ), 400 ); }
		if ( 'post' === $entity ) {
			$id = absint( $id_raw ); $translations = self::post_translations( $id ); $translations[ $field ] = $value; update_post_meta( $id, self::POST_META_KEY, $translations );
			$state = self::post_translation_state( $id ); $state[ $field ] = array( 'source_hash' => self::source_hash( $source ), 'origin' => $origin ); update_post_meta( $id, self::POST_STATE_META_KEY, $state );
		} elseif ( 'term' === $entity ) {
			$id = absint( $id_raw ); $translations = self::term_translations( $id ); $translations[ $field ] = $value; update_term_meta( $id, self::TERM_META_KEY, $translations );
			$state = self::term_translation_state( $id ); $state[ $field ] = array( 'source_hash' => self::source_hash( $source ), 'origin' => $origin ); update_term_meta( $id, self::TERM_STATE_META_KEY, $state );
		} else {
			$key = sanitize_key( (string) $id_raw ); $translations = self::interface_translations(); $translations[ $key ] = $value; update_option( self::INTERFACE_OPTION, $translations, false );
			// Reset request-local caches so a same-request re-read reflects the new value.
			self::$interface_translations_cache = null;
			self::$interface_lookup_cache = null;
		}
		wp_send_json_success( array( 'value' => $value, 'status' => 'fresh', 'origin' => $origin ) );
	}

	/** @return bool */
	private function field_is_allowed( $entity, $id_raw, $field ) {
		$registry = self::registry();
		if ( 'post' === $entity ) { $id = absint( $id_raw ); $post = $id ? get_post( $id ) : null; if ( ! $post || ! isset( $registry['post_types'][ $post->post_type ] ) ) { return false; } $definition = $registry['post_types'][ $post->post_type ]; if ( isset( $definition['fields'][ $field ] ) || isset( $definition['meta'][ $field ] ) ) { return true; } if ( Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE === $post->post_type && 0 === strpos( $field, 'answer_label_' ) ) { $index = absint( substr( $field, strlen( 'answer_label_' ) ) ); $answers = get_post_meta( $id, Gloskin_Site_Core_Content_Service::ANSWER_META_KEY, true ); return is_array( $answers ) && isset( $answers[ $index ]['label'] ); } return false; }
		if ( 'term' === $entity ) { $id = absint( $id_raw ); $term = $id ? get_term( $id ) : null; return $term && ! is_wp_error( $term ) && isset( $registry['taxonomies'][ $term->taxonomy ]['fields'][ $field ] ); }
		return 'interface' === $entity && 'text' === $field && isset( $registry['interface'][ sanitize_key( (string) $id_raw ) ] );
	}

	/** @return string|null */
	private function current_source( $entity, $id_raw, $field ) {
		if ( 'post' === $entity ) { $post = get_post( absint( $id_raw ) ); if ( ! $post ) { return null; } $registry = self::registry(); $definition = isset( $registry['post_types'][ $post->post_type ] ) ? $registry['post_types'][ $post->post_type ] : null; if ( ! $definition ) { return null; } if ( isset( $definition['fields'][ $field ] ) ) { return isset( $post->$field ) ? (string) $post->$field : ''; } if ( isset( $definition['meta'][ $field ] ) ) { return (string) get_post_meta( $post->ID, $field, true ); } if ( Gloskin_Site_Core_Content_Service::QUESTION_POST_TYPE === $post->post_type && 0 === strpos( $field, 'answer_label_' ) ) { $answers = get_post_meta( $post->ID, Gloskin_Site_Core_Content_Service::ANSWER_META_KEY, true ); $index = absint( substr( $field, strlen( 'answer_label_' ) ) ); return is_array( $answers ) && isset( $answers[ $index ]['label'] ) ? (string) $answers[ $index ]['label'] : null; } return null; }
		if ( 'term' === $entity ) { $term = get_term( absint( $id_raw ) ); return $term && ! is_wp_error( $term ) && isset( $term->$field ) ? (string) $term->$field : null; }
		if ( 'interface' === $entity ) { $key = sanitize_key( (string) $id_raw ); $registry = self::interface_registry(); return isset( $registry[ $key ]['source'] ) ? (string) $registry[ $key ]['source'] : null; }
		return null;
	}

	/** @return string */
	private function sanitize_translation( $entity, $field, $value ) {
		unset( $entity );
		$registry = self::registry();
		if ( 'post_content' === $field || 'description' === $field || in_array( $field, array( 'gloskin_benefits', 'gloskin_contraindications', 'gloskin_about_vision', 'gloskin_about_mission', 'gloskin_about_values', 'gloskin_about_founder_story' ), true ) ) { return wp_kses_post( $value ); }
		if ( 'post_excerpt' === $field || false !== strpos( $field, 'summary' ) || false !== strpos( $field, 'source_note' ) || false !== strpos( $field, 'address' ) || false !== strpos( $field, 'hours' ) || false !== strpos( $field, 'copy' ) || false !== strpos( $field, 'lead' ) ) { return sanitize_textarea_field( $value ); }
		return sanitize_text_field( $value );
	}

	/** @return array<string,mixed> */
	private function field_payload( $entity, $entity_id, $key, $label, $source, $en, $rich ) {
		$freshness = self::freshness( $entity, $entity_id, $key, $source, $en );
		return array( 'key' => (string) $key, 'label' => (string) $label, 'source' => (string) $source, 'en' => (string) $en, 'rich' => (bool) $rich, 'status' => $freshness['status'], 'origin' => $freshness['origin'] );
	}

	/** @return array<string,mixed> */
	private function record_payload( $entity, $entity_id, $type, $label, $fields, $taxonomy = '' ) {
		$fresh = 0; foreach ( $fields as $field ) { if ( 'fresh' === (string) $field['status'] ) { ++$fresh; } }
		return array( 'key' => $entity . ':' . (string) $entity_id . ( $taxonomy ? ':' . $taxonomy : '' ), 'entity' => $entity, 'entityId' => (string) $entity_id, 'taxonomy' => (string) $taxonomy, 'type' => (string) $type, 'label' => (string) $label, 'filled' => $fresh, 'total' => count( $fields ), 'fields' => array_values( $fields ) );
	}
}
