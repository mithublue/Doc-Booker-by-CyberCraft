<?php
/**
 * Plugin Name:       Doc Booker by CyberCraft
 * Description:       Appointment management essentials for doctors and patients.
 * Version:           1.0.0
 * Author:            CyberCraft
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       doc-booker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Doc_Booker' ) ) {
	class Doc_Booker {
		const VERSION = '1.0.0';
		const OPTION_DEPARTMENTS = 'db_departments';
		const OPTION_TIME_SLOTS = 'db_time_slots';
		const ROLE_DOCTOR = 'db_doctor';
		const ROLE_PATIENT = 'db_patient';

		/**
		 * @var Doc_Booker_Shortcode
		 */
		private $shortcode;

		/**
		 * @var Doc_Booker_Ajax
		 */
		private $ajax;

		public function __construct() {
			add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
			add_action( 'admin_init', [ $this, 'handle_departments_form' ] );
			add_action( 'admin_init', [ $this, 'handle_timeslots_form' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

			add_action( 'show_user_profile', [ $this, 'render_doctor_profile_fields' ] );
			add_action( 'edit_user_profile', [ $this, 'render_doctor_profile_fields' ] );
			add_action( 'personal_options_update', [ $this, 'save_doctor_profile_fields' ] );
			add_action( 'edit_user_profile_update', [ $this, 'save_doctor_profile_fields' ] );

			require_once self::plugin_path() . 'includes/class-doc-booker-shortcode.php';
			require_once self::plugin_path() . 'includes/class-doc-booker-ajax.php';

			$this->shortcode = new Doc_Booker_Shortcode();
			$this->ajax      = new Doc_Booker_Ajax( $this->shortcode );
		}

		public static function activate() {
			add_role( self::ROLE_DOCTOR, __( 'Doctor', 'doc-booker' ), [ 'read' => true ] );
			add_role( self::ROLE_PATIENT, __( 'Patient', 'doc-booker' ), [ 'read' => true ] );

			$current_departments = get_option( self::OPTION_DEPARTMENTS, null );
			if ( null === $current_departments || ! is_array( $current_departments ) ) {
				add_option( self::OPTION_DEPARTMENTS, [] );
			}

			$current_timeslots = get_option( self::OPTION_TIME_SLOTS, null );
			if ( null === $current_timeslots || ! is_array( $current_timeslots ) ) {
				add_option(
					self::OPTION_TIME_SLOTS,
					[
						'office_days' => [],
						'time_slots'  => [],
					]
				);
			}
		}

		public static function deactivate() {
			remove_role( self::ROLE_DOCTOR );
			remove_role( self::ROLE_PATIENT );
		}

		public function register_admin_menu() {
			add_menu_page(
				__( 'Doc Booker', 'doc-booker' ),
				__( 'Doc Booker', 'doc-booker' ),
				'manage_options',
				'doc-booker',
				[ $this, 'render_dashboard_page' ],
				'dashicons-calendar-alt',
				32
			);

			add_submenu_page(
				'doc-booker',
				__( 'Time Slots', 'doc-booker' ),
				__( 'Time Slots', 'doc-booker' ),
				'manage_options',
				'doc-booker-timeslots',
				[ $this, 'render_timeslots_page' ]
			);

			add_submenu_page(
				'doc-booker',
				__( 'Appointments', 'doc-booker' ),
				__( 'Appointments', 'doc-booker' ),
				'manage_options',
				'doc-booker-appointments',
				[ $this, 'render_appointments_page' ]
			);

			add_submenu_page(
				'doc-booker',
				__( 'Departments', 'doc-booker' ),
				__( 'Departments', 'doc-booker' ),
				'manage_options',
				'doc-booker-departments',
				[ $this, 'render_departments_page' ]
			);

			add_submenu_page(
				'doc-booker',
				__( 'Patients', 'doc-booker' ),
				__( 'Patients', 'doc-booker' ),
				'manage_options',
				'doc-booker-patients',
				[ $this, 'render_patients_page' ]
			);
		}

		public function enqueue_assets( $hook ) {
			$screen = get_current_screen();

			if ( ! $screen || false === strpos( $screen->id, 'doc-booker' ) ) {
				return;
			}

			wp_enqueue_style(
				'doc-booker-admin',
				plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
				[],
				self::VERSION
			);

			wp_enqueue_script(
				'doc-booker-admin',
				plugin_dir_url( __FILE__ ) . 'assets/js/admin.js',
				[ 'jquery' ],
				self::VERSION,
				true
			);

			wp_localize_script(
				'doc-booker-admin',
				'DocBookerDepartments',
				[
					'i18n' => [
						'newPlaceholder'  => __( 'Department name', 'doc-booker' ),
						'newDescription' => __( 'Short description', 'doc-booker' ),
					],
				]
			);
		}

		public function handle_departments_form() {
			if ( ! isset( $_POST['doc_booker_departments_nonce'] ) ) {
				return;
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			check_admin_referer( 'doc_booker_save_departments', 'doc_booker_departments_nonce' );

			$names        = isset( $_POST['db_department_name'] ) ? (array) wp_unslash( $_POST['db_department_name'] ) : [];
			$descriptions = isset( $_POST['db_department_description'] ) ? (array) wp_unslash( $_POST['db_department_description'] ) : [];

			$departments = [];

			foreach ( $names as $index => $name ) {
				$name = sanitize_text_field( $name );

				if ( '' === $name ) {
					continue;
				}

				$description = isset( $descriptions[ $index ] ) ? sanitize_textarea_field( $descriptions[ $index ] ) : '';
				$departments[ $name ] = [
					'name'        => $name,
					'description' => $description,
				];
			}

			update_option( self::OPTION_DEPARTMENTS, $departments );

			add_settings_error( 'doc_booker_departments', 'departments_saved', __( 'Departments saved successfully.', 'doc-booker' ), 'updated' );
		}

		public function handle_timeslots_form() {
			if ( ! isset( $_POST['doc_booker_timeslots_nonce'] ) ) {
				return;
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			check_admin_referer( 'doc_booker_save_timeslots', 'doc_booker_timeslots_nonce' );

			$week_days   = array_keys( $this->get_week_days() );
			$office_days = isset( $_POST['db_office_days'] ) ? (array) wp_unslash( $_POST['db_office_days'] ) : [];
			$office_days = array_map( 'sanitize_text_field', $office_days );
			$office_days = array_unique( $office_days );
			$office_days = array_values( array_intersect( $week_days, $office_days ) );

			$starts = isset( $_POST['db_time_slot_start'] ) ? (array) wp_unslash( $_POST['db_time_slot_start'] ) : [];
			$ends   = isset( $_POST['db_time_slot_end'] ) ? (array) wp_unslash( $_POST['db_time_slot_end'] ) : [];

			$time_slots = [];

			foreach ( $starts as $index => $start ) {
				$start = sanitize_text_field( $start );
				$end   = isset( $ends[ $index ] ) ? sanitize_text_field( $ends[ $index ] ) : '';

				if ( '' === $start || '' === $end ) {
					continue;
				}

				if ( ! $this->is_valid_time_format( $start ) || ! $this->is_valid_time_format( $end ) ) {
					continue;
				}

				if ( strtotime( $start ) >= strtotime( $end ) ) {
					continue;
				}

				$time_slots[] = [
					'start' => $start,
					'end'   => $end,
				];
			}

			update_option(
				self::OPTION_TIME_SLOTS,
				[
					'office_days' => $office_days,
					'time_slots'  => $time_slots,
				]
			);

			add_settings_error( 'doc_booker_timeslots', 'timeslots_saved', __( 'Time slots saved successfully.', 'doc-booker' ), 'updated' );
		}

		public function render_dashboard_page() {
			$this->render_placeholder_page(
				doc_booker_get_hero_content(
					__( 'Doc Booker Overview', 'doc-booker' ),
					__( 'Welcome to Doc Booker – manage medical appointments, time slots, departments, and patient profiles with ease.', 'doc-booker' )
				)
			);
		}

		public function render_timeslots_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$config = get_option( self::OPTION_TIME_SLOTS, [] );
			$config = is_array( $config ) ? $config : [];

			$office_days = isset( $config['office_days'] ) && is_array( $config['office_days'] ) ? $config['office_days'] : [];
			$time_slots  = isset( $config['time_slots'] ) && is_array( $config['time_slots'] ) ? $config['time_slots'] : [];

			if ( empty( $time_slots ) ) {
				$time_slots[] = [
					'start' => '',
					'end'   => '',
				];
			}

			$week_days = $this->get_week_days();

			settings_errors( 'doc_booker_timeslots' );
			?>
			<div class="wrap doc-booker-wrap">
				<h1 class="wp-heading-inline"><?php esc_html_e( 'Time Slots', 'doc-booker' ); ?></h1>
				<p class="doc-booker-page-subtitle"><?php esc_html_e( 'Define active office days and repeating appointment windows for every doctor.', 'doc-booker' ); ?></p>

				<form method="post" class="doc-booker-card">
					<?php wp_nonce_field( 'doc_booker_save_timeslots', 'doc_booker_timeslots_nonce' ); ?>

					<div class="doc-booker-card__header">
						<div>
							<h2><?php esc_html_e( 'Office Schedule', 'doc-booker' ); ?></h2>
							<p><?php esc_html_e( 'Select the days your clinic operates and craft beautiful time slot presets that apply to every active day.', 'doc-booker' ); ?></p>
						</div>
					</div>

					<div class="doc-booker-card__body">
						<section class="doc-booker-section">
							<h3><?php esc_html_e( 'Office Days', 'doc-booker' ); ?></h3>
							<p class="description"><?php esc_html_e( 'Toggle the days that should accept appointments.', 'doc-booker' ); ?></p>
							<div class="doc-booker-days-grid">
								<?php foreach ( $week_days as $key => $label ) : ?>
									<?php $is_active = in_array( $key, $office_days, true ); ?>
									<label class="doc-booker-day-card <?php echo $is_active ? 'is-active' : ''; ?>">
										<input type="checkbox" name="db_office_days[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $is_active ); ?> />
										<span class="doc-booker-day-card__title"><?php echo esc_html( $label ); ?></span>
										<span class="doc-booker-day-card__status" data-active-text="<?php esc_attr_e( 'Active', 'doc-booker' ); ?>" data-inactive-text="<?php esc_attr_e( 'Off day', 'doc-booker' ); ?>"><?php echo $is_active ? esc_html__( 'Active', 'doc-booker' ) : esc_html__( 'Off day', 'doc-booker' ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</section>

						<section class="doc-booker-section">
							<h3><?php esc_html_e( 'Repeating Time Slots', 'doc-booker' ); ?></h3>
							<p class="description"><?php esc_html_e( 'These slots are applied to every active office day. You can add as many windows as you need.', 'doc-booker' ); ?></p>

							<table class="doc-booker-table" id="doc-booker-time-slots-table">
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Start Time', 'doc-booker' ); ?></th>
										<th scope="col"><?php esc_html_e( 'End Time', 'doc-booker' ); ?></th>
										<th scope="col" class="doc-booker-table__actions"><?php esc_html_e( 'Actions', 'doc-booker' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $time_slots as $slot ) : ?>
										<tr class="doc-booker-time-slot-row">
											<td>
												<input type="time" class="regular-text" name="db_time_slot_start[]" value="<?php echo esc_attr( $slot['start'] ?? '' ); ?>" required />
											</td>
											<td>
												<input type="time" class="regular-text" name="db_time_slot_end[]" value="<?php echo esc_attr( $slot['end'] ?? '' ); ?>" required />
											</td>
											<td class="doc-booker-table__actions">
												<button type="button" class="button button-link-delete doc-booker-remove-slot" aria-label="<?php esc_attr_e( 'Remove time slot', 'doc-booker' ); ?>">&times;</button>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
							<button type="button" id="doc-booker-add-time-slot" class="button button-secondary doc-booker-add-row">
								<span class="dashicons dashicons-plus"></span>
								<?php esc_html_e( 'Add Time Slot', 'doc-booker' ); ?>
							</button>
						</section>
					</div>

					<div class="doc-booker-card__footer">
						<button type="submit" class="button button-primary button-hero">
							<?php esc_html_e( 'Save Time Slots', 'doc-booker' ); ?>
						</button>
					</div>
				</form>

				<template id="doc-booker-time-slot-template">
					<tr class="doc-booker-time-slot-row">
						<td>
							<input type="time" class="regular-text" name="db_time_slot_start[]" required />
						</td>
						<td>
							<input type="time" class="regular-text" name="db_time_slot_end[]" required />
						</td>
						<td class="doc-booker-table__actions">
							<button type="button" class="button button-link-delete doc-booker-remove-slot" aria-label="<?php esc_attr_e( 'Remove time slot', 'doc-booker' ); ?>">&times;</button>
						</td>
					</tr>
				</template>
			</div>
			<?php
		}

		public function render_appointments_page() {
			$this->render_placeholder_page(
				doc_booker_get_hero_content(
					__( 'Appointments', 'doc-booker' ),
					__( 'Monitor, approve, or reschedule booked appointments. (Feature coming soon)', 'doc-booker' )
				)
			);
		}

		public function render_patients_page() {
			$this->render_placeholder_page(
				doc_booker_get_hero_content(
					__( 'Patients', 'doc-booker' ),
					__( 'Centralized access to all registered patients. (Feature coming soon)', 'doc-booker' )
				)
			);
		}

		public function render_departments_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$departments = get_option( self::OPTION_DEPARTMENTS, [] );

			settings_errors( 'doc_booker_departments' );
			?>
			<div class="wrap doc-booker-wrap">
				<h1 class="wp-heading-inline"><?php esc_html_e( 'Departments', 'doc-booker' ); ?></h1>
				<p class="doc-booker-page-subtitle"><?php esc_html_e( 'Create stunning department profiles that doctors can join.', 'doc-booker' ); ?></p>

				<form method="post" class="doc-booker-card">
					<?php wp_nonce_field( 'doc_booker_save_departments', 'doc_booker_departments_nonce' ); ?>

					<div class="doc-booker-card__header">
						<div>
							<h2><?php esc_html_e( 'Department Directory', 'doc-booker' ); ?></h2>
							<p><?php esc_html_e( 'Add or update specialties, describe services, and keep your practice organised.', 'doc-booker' ); ?></p>
						</div>
					</div>

					<div class="doc-booker-card__body">
						<table class="doc-booker-table" id="doc-booker-departments-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Department Name', 'doc-booker' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Description', 'doc-booker' ); ?></th>
									<th scope="col" class="doc-booker-table__actions"><?php esc_html_e( 'Actions', 'doc-booker' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( ! empty( $departments ) ) : ?>
									<?php foreach ( $departments as $key => $department ) : ?>
										<tr class="doc-booker-department-row">
											<td>
												<input type="text" class="regular-text" name="db_department_name[]" value="<?php echo esc_attr( $department['name'] ); ?>" placeholder="<?php esc_attr_e( 'Cardiology', 'doc-booker' ); ?>" required />
											</td>
											<td>
												<textarea class="widefat" name="db_department_description[]" rows="2" placeholder="<?php esc_attr_e( 'Heart health and cardiovascular treatments.', 'doc-booker' ); ?>"><?php echo esc_textarea( $department['description'] ); ?></textarea>
											</td>
											<td class="doc-booker-table__actions">
												<button type="button" class="button button-link-delete doc-booker-remove-row" aria-label="<?php esc_attr_e( 'Remove department', 'doc-booker' ); ?>">&times;</button>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr class="doc-booker-department-row">
										<td>
											<input type="text" class="regular-text" name="db_department_name[]" placeholder="<?php esc_attr_e( 'Cardiology', 'doc-booker' ); ?>" required />
										</td>
										<td>
											<textarea class="widefat" name="db_department_description[]" rows="2" placeholder="<?php esc_attr_e( 'Heart health and cardiovascular treatments.', 'doc-booker' ); ?>"></textarea>
										</td>
										<td class="doc-booker-table__actions">
											<button type="button" class="button button-link-delete doc-booker-remove-row" aria-label="<?php esc_attr_e( 'Remove department', 'doc-booker' ); ?>">&times;</button>
										</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
						<button type="button" id="doc-booker-add-department" class="button button-secondary doc-booker-add-row">
							<span class="dashicons dashicons-plus"></span>
							<?php esc_html_e( 'Add Department', 'doc-booker' ); ?>
						</button>
					</div>

					<div class="doc-booker-card__footer">
						<button type="submit" class="button button-primary button-hero">
							<?php esc_html_e( 'Save Departments', 'doc-booker' ); ?>
						</button>
					</div>
				</form>

				<template id="doc-booker-department-template">
					<tr class="doc-booker-department-row">
						<td>
							<input type="text" class="regular-text" name="db_department_name[]" placeholder="<?php esc_attr_e( 'Dermatology', 'doc-booker' ); ?>" required />
						</td>
						<td>
							<textarea class="widefat" name="db_department_description[]" rows="2" placeholder="<?php esc_attr_e( 'Skin, hair, and nail care.', 'doc-booker' ); ?>"></textarea>
						</td>
						<td class="doc-booker-table__actions">
							<button type="button" class="button button-link-delete doc-booker-remove-row" aria-label="<?php esc_attr_e( 'Remove department', 'doc-booker' ); ?>">&times;</button>
						</td>
					</tr>
				</template>
			</div>
			<?php
		}

		public function render_doctor_profile_fields( $user ) {
			if ( ! ( $user instanceof WP_User ) ) {
				return;
			}

			if ( ! in_array( self::ROLE_DOCTOR, (array) $user->roles, true ) ) {
				return;
			}

			$departments = get_option( self::OPTION_DEPARTMENTS, [] );
			$current    = get_user_meta( $user->ID, 'db_doctor_department', true );
			?>
			<h2 class="doc-booker-profile-title"><?php esc_html_e( 'Doctor Details', 'doc-booker' ); ?></h2>
			<table class="form-table doc-booker-profile-table">
				<tr>
					<th><label for="db_doctor_department"><?php esc_html_e( 'Department', 'doc-booker' ); ?></label></th>
					<td>
						<?php if ( empty( $departments ) ) : ?>
							<p class="description"><?php esc_html_e( 'No departments available yet. Create one under Doc Booker → Departments.', 'doc-booker' ); ?></p>
						<?php else : ?>
							<select name="db_doctor_department" id="db_doctor_department" class="regular-text">
								<option value=""><?php esc_html_e( 'Select a department', 'doc-booker' ); ?></option>
								<?php foreach ( $departments as $key => $department ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>>
										<?php echo esc_html( $department['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Assign this doctor to their primary department.', 'doc-booker' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php
		}

		public function save_doctor_profile_fields( $user_id ) {
			if ( ! current_user_can( 'edit_user', $user_id ) ) {
				return;
			}

			$user = get_user_by( 'id', $user_id );

			if ( ! $user || ! in_array( self::ROLE_DOCTOR, (array) $user->roles, true ) ) {
				delete_user_meta( $user_id, 'db_doctor_department' );
				return;
			}

			$departments = get_option( self::OPTION_DEPARTMENTS, [] );
			$selected    = isset( $_POST['db_doctor_department'] ) ? sanitize_text_field( wp_unslash( $_POST['db_doctor_department'] ) ) : '';

			if ( $selected && isset( $departments[ $selected ] ) ) {
				update_user_meta( $user_id, 'db_doctor_department', $selected );
			} else {
				delete_user_meta( $user_id, 'db_doctor_department' );
			}
		}

		private function render_placeholder_page( $inner_html ) {
			?>
			<div class="wrap doc-booker-wrap">
				<?php echo $inner_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php
		}

		private function get_week_days() {
			return [
				'monday'    => __( 'Monday', 'doc-booker' ),
				'tuesday'   => __( 'Tuesday', 'doc-booker' ),
				'wednesday' => __( 'Wednesday', 'doc-booker' ),
				'thursday'  => __( 'Thursday', 'doc-booker' ),
				'friday'    => __( 'Friday', 'doc-booker' ),
				'saturday'  => __( 'Saturday', 'doc-booker' ),
				'sunday'    => __( 'Sunday', 'doc-booker' ),
			];
		}

		private function is_valid_time_format( $time ) {
			return is_string( $time ) && 1 === preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time );
		}

		public static function get_departments() {
			$departments = get_option( self::OPTION_DEPARTMENTS, [] );

			if ( ! is_array( $departments ) ) {
				return [];
			}

			return $departments;
		}

		public static function plugin_url() {
			return plugin_dir_url( __FILE__ );
		}

		public static function plugin_path() {
			return plugin_dir_path( __FILE__ );
		}

		public static function get_name_letter( $name ) {
			$name = trim( (string) $name );
			if ( '' === $name ) {
				return '';
			}

			$normalized = remove_accents( $name );
			$letter     = strtoupper( mb_substr( $normalized, 0, 1 ) );

			return ctype_alpha( $letter ) ? $letter : '#';
		}
	}

	function doc_booker_get_hero_content( $title, $description ) {
		ob_start();
		?>
		<div class="doc-booker-card doc-booker-card--hero">
			<div class="doc-booker-card__header">
				<h1><?php echo esc_html( $title ); ?></h1>
				<p><?php echo esc_html( $description ); ?></p>
			</div>
			<div class="doc-booker-card__body">
				<ul class="doc-booker-feature-list">
					<li><span class="dashicons dashicons-yes"></span><?php esc_html_e( 'Unified admin dashboard with elegant UI.', 'doc-booker' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span><?php esc_html_e( 'Doctor & patient roles out of the box.', 'doc-booker' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span><?php esc_html_e( 'Department management with beautiful controls.', 'doc-booker' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	$doc_booker = new Doc_Booker();

	register_activation_hook( __FILE__, [ 'Doc_Booker', 'activate' ] );
	register_deactivation_hook( __FILE__, [ 'Doc_Booker', 'deactivate' ] );
}
