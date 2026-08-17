<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Gloskin_Site_Core_Shop_Discovery {
	const PER_PAGE = 12;
	const MAX_PRICE = 999999999.99;
	use Gloskin_Site_Core_Shop_Discovery_Route_Trait;
	use Gloskin_Site_Core_Shop_Discovery_Rest_Trait;
	use Gloskin_Site_Core_Shop_Discovery_Query_Trait;
	use Gloskin_Site_Core_Shop_Discovery_Normalize_Trait;
}
