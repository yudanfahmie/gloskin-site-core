<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
trait Gloskin_Site_Core_Doctor_Importer_State_Trait {
	/** @var Gloskin_Site_Core_Doctor_Bundle */
	private $bundle;

	/** @var string */
	private $plugin_file;

	/** @param string $plugin_file Main plugin file. */
	public function __construct( $plugin_file ) {
		$this->plugin_file = (string) $plugin_file;
		$this->bundle      = new Gloskin_Site_Core_Doctor_Bundle( $plugin_file );
	}

	/** @return array<string,mixed> */
	public function state() {
		$state = get_option( self::STATE_OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		return array_merge(
			array(
				'status'        => 'pending',
				'index'         => 0,
				'expected'      => Gloskin_Site_Core_Doctor_Bundle::EXPECTED_DOCTORS,
				'imported_ids'  => array(),
				'last_error'    => '',
				'consumed_at'   => 0,
				'cleanup'       => 'pending',
				'cleanup_error' => '',
			),
			$state
		);
	}

	/**
	 * Pure package validation before any roster mutation. Package A reuses the
	 * canonical roster loader. When the importer is owned by the loaded Final
	 * Migration, packages B/C and roster<->photo exact alias compatibility are
	 * also validated before the importer lock or any upsert is reached.
	 *
	 * @return array{manifest:array<string,mixed>,doctors:array<int,array<string,string>>}
	 */
	public function validate_bundle() {
		$payload = $this->bundle->load(); // A. gloskin-doctors-v1.
		if ( class_exists( 'Gloskin_Site_Core_Revision_20260819_Final_Migration', false ) ) {
			require_once __DIR__ . '/class-gloskin-site-core-final-package-validator.php';
			( new Gloskin_Site_Core_Final_Package_Validator( $this->plugin_file ) )->validate_after_roster_bundle( $payload ); // B then C + exact cross-package compatibility.
		}
		return $payload;
	}

	/** @return bool */
	public function should_show_menu() {
		$state = $this->state();
		return 'consumed' !== $state['status'];
	}

	/**
	 * Explicit Start/Continue. Exactly one doctor is checkpointed per call.
	 *
	 * @param string $mode start|continue.
	 * @return array<string,mixed>
	 * @throws RuntimeException Import failure.
	 */
	public function advance( $mode ) {
		$mode = sanitize_key( $mode );
		if ( ! in_array( $mode, array( 'start', 'continue' ), true ) ) {
			throw new RuntimeException( __( 'Mode doctor migration tidak valid.', 'gloskin-site-core' ) );
		}
		$state = $this->state();
		if ( 'consumed' === $state['status'] ) {
			return $state;
		}
		if ( 'start' === $mode && absint( $state['index'] ) > 0 ) {
			throw new RuntimeException( __( 'Doctor migration sudah dimulai; gunakan Continue.', 'gloskin-site-core' ) );
		}

		$payload = $this->validate_bundle(); // Pure A -> B -> C validation before lock/upsert.
		$token   = $this->acquire_lock();
		try {
			$state = $this->state();
			if ( 'consumed' === $state['status'] ) {
				return $state;
			}
			$index = absint( $state['index'] );
			if ( $index >= count( $payload['doctors'] ) ) {
				return $this->finalize( $state, $payload );
			}

			$doctor_id = $this->upsert_doctor( $payload['doctors'][ $index ] );
			$imported  = isset( $state['imported_ids'] ) && is_array( $state['imported_ids'] ) ? array_map( 'absint', $state['imported_ids'] ) : array();
			$imported[] = $doctor_id;
			$state['status']       = 'running';
			$state['index']        = $index + 1;
			$state['imported_ids'] = array_values( array_unique( $imported ) );
			$state['last_error']   = '';
			if ( $state['index'] >= count( $payload['doctors'] ) ) {
				$state['status'] = 'verifying';
			}
			$this->save_state( $state );
			return $state;
		} catch ( Throwable $error ) {
			$state               = $this->state();
			$state['status']     = 'failed';
			$state['last_error'] = mb_substr( sanitize_text_field( $error->getMessage() ), 0, 500 );
			$this->save_state( $state );
			throw $error;
		} finally {
			$this->release_lock( $token );
		}
	}
}
