<?php
/**
 * Shortcode renderer for the Doc Booker doctor directory.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Doc_Booker_Shortcode {
	const SHORTCODE      = 'doc_booker_directory';
	const DEFAULT_LETTER = 'all';

	public function __construct() {
		add_shortcode( self::SHORTCODE, [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
	}

	public function register_assets() {
		wp_register_style(
			'doc-booker-directory',
			Doc_Booker::plugin_url() . 'assets/css/frontend.css',
			[],
			Doc_Booker::VERSION
		);

		wp_register_script(
			'doc-booker-directory',
			Doc_Booker::plugin_url() . 'assets/js/frontend.js',
			[ 'jquery' ],
			Doc_Booker::VERSION,
			true
		);
	}

	public function render( $atts = [], $content = '' ) {
		$this->register_assets();

		wp_enqueue_style( 'doc-booker-directory' );
		wp_enqueue_script( 'doc-booker-directory' );

		$filters = [
			'department'   => '',
			'name'         => '',
			'letter'       => self::DEFAULT_LETTER,
			'date'         => '',
			'availability' => '',
		];

		$data         = $this->get_directory_data( $filters );
		$results_html = $this->render_directory_groups( $data['groups'] );

		$letters = range( 'A', 'Z' );
		wp_localize_script(
			'doc-booker-directory',
			'DocBookerDirectory',
			[
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( Doc_Booker_Ajax::NONCE ),
				'action'         => Doc_Booker_Ajax::ACTION,
				'defaultFilters' => $filters,
				'letters'        => $letters,
				'i18n'           => [
					'allDepartments' => __( 'All Departments', 'doc-booker' ),
					'apply'          => __( 'Apply Filter', 'doc-booker' ),
					'noResults'      => __( 'No doctors found for the selected filters.', 'doc-booker' ),
					'error'          => __( 'Something went wrong. Please try again.', 'doc-booker' ),
				],
			]
		);

		$departments = Doc_Booker::get_departments();
		uasort(
			$departments,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'] ?? '', $b['name'] ?? '' );
			}
		);

		$view_path = Doc_Booker::plugin_path() . 'views/doctor-directory.php';

		if ( ! file_exists( $view_path ) ) {
			return '';
		}

		ob_start();

		include $view_path;

		return ob_get_clean();
	}

	public function get_directory_data( $filters = [] ) {
		$defaults = [
			'department'   => '',
			'name'         => '',
			'letter'       => self::DEFAULT_LETTER,
			'date'         => '',
			'availability' => '',
		];

		$filters = wp_parse_args( $filters, $defaults );

		$departments = Doc_Booker::get_departments();
		$doctors     = get_users(
			[
				'role'    => Doc_Booker::ROLE_DOCTOR,
				'orderby' => 'display_name',
				'order'   => 'ASC',
			]
		);

		$groups        = [];
		$total_doctors = 0;

		foreach ( $doctors as $doctor ) {
			/** @var WP_User $doctor */

			$department_key = get_user_meta( $doctor->ID, 'db_doctor_department', true );
			$department     = $departments[ $department_key ] ?? null;
			$department_name = $department['name'] ?? __( 'Unassigned Department', 'doc-booker' );
			$department_desc = $department['description'] ?? '';

			if ( $filters['department'] && $department_key !== $filters['department'] ) {
				continue;
			}

			if ( $filters['name'] && false === stripos( $doctor->display_name, $filters['name'] ) ) {
				continue;
			}

			$department_letter = Doc_Booker::get_name_letter( $department_name );

			if ( $filters['letter'] && self::DEFAULT_LETTER !== strtolower( $filters['letter'] ) ) {
				if ( strtolower( $department_letter ) !== strtolower( $filters['letter'] ) ) {
					continue;
				}
			}

			$group_key = $department_key ?: 'unassigned';

			if ( ! isset( $groups[ $group_key ] ) ) {
				$groups[ $group_key ] = [
					'name'        => $department_name,
					'description' => $department_desc,
					'letter'      => strtolower( $department_letter ),
					'doctors'     => [],
				];
			}

			$groups[ $group_key ]['doctors'][] = [
				'id'       => $doctor->ID,
				'name'     => $doctor->display_name,
				'email'    => $doctor->user_email,
				'url'      => get_author_posts_url( $doctor->ID ),
				'avatar'   => get_avatar( $doctor->ID, 128, '', $doctor->display_name, [ 'class' => 'doc-booker-directory__avatar-img' ] ),
				'letter'   => $department_letter,
				'raw_name' => $doctor->display_name,
			];

			++$total_doctors;
		}

		foreach ( $groups as &$group ) {
			usort(
				$group['doctors'],
				static function ( $a, $b ) {
					return strcasecmp( $a['name'], $b['name'] );
				}
			);
		}
		unset( $group );

		uasort(
			$groups,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return [
			'groups' => $groups,
			'total'  => $total_doctors,
		];
	}

	public function render_directory_groups( $groups ) {
		ob_start();

		if ( empty( $groups ) ) {
			?>
			<div class="doc-booker-directory__empty">
				<p><?php esc_html_e( 'No doctors found for the selected filters.', 'doc-booker' ); ?></p>
			</div>
			<?php
			return ob_get_clean();
		}

		foreach ( $groups as $group ) {
			?>
			<section class="doc-booker-directory__group" data-letter="<?php echo esc_attr( $group['letter'] ); ?>">
				<header class="doc-booker-directory__group-header">
					<h2><?php echo esc_html( $group['name'] ); ?></h2>
					<?php if ( ! empty( $group['description'] ) ) : ?>
						<p class="doc-booker-directory__group-description"><?php echo esc_html( $group['description'] ); ?></p>
					<?php endif; ?>
				</header>
				<div class="doc-booker-directory__cards">
					<?php foreach ( $group['doctors'] as $doctor ) : ?>
						<article class="doc-booker-directory__card">
							<div class="doc-booker-directory__avatar">
								<?php echo $doctor['avatar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
							<div class="doc-booker-directory__card-body">
								<h3 class="doc-booker-directory__card-title"><?php echo esc_html( $doctor['name'] ); ?></h3>
								<ul class="doc-booker-directory__meta">
									<?php if ( ! empty( $doctor['email'] ) ) : ?>
										<li><a href="mailto:<?php echo esc_attr( $doctor['email'] ); ?>"><?php echo esc_html( $doctor['email'] ); ?></a></li>
									<?php endif; ?>
									<?php if ( ! empty( $doctor['url'] ) ) : ?>
										<li><a href="<?php echo esc_url( $doctor['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View profile', 'doc-booker' ); ?></a></li>
									<?php endif; ?>
								</ul>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
			<?php
		}

		return ob_get_clean();
	}
}
