<?php
/**
 * Doctor directory shortcode view.
 *
 * @var array  $departments
 * @var array  $filters
 * @var array  $letters
 * @var string $results_html
 * @var int    $total
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="doc-booker-directory" data-error-message="<?php echo esc_attr( $filters['error_message'] ?? __( 'Something went wrong. Please try again.', 'doc-booker' ) ); ?>">
	<form class="doc-booker-directory__form" novalidate>
		<div class="doc-booker-directory__filters">
			<div class="doc-booker-directory__field">
				<label for="doc-booker-filter-department"><?php esc_html_e( 'Search by Department', 'doc-booker' ); ?></label>
				<select id="doc-booker-filter-department" name="department">
					<option value=""><?php esc_html_e( 'All Departments', 'doc-booker' ); ?></option>
					<?php foreach ( $departments as $key => $department ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $department['name'] ?? $key ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="doc-booker-directory__field">
				<label for="doc-booker-filter-name"><?php esc_html_e( 'Search by Name', 'doc-booker' ); ?></label>
				<input type="text" id="doc-booker-filter-name" name="name" placeholder="<?php esc_attr_e( 'Type doctor name', 'doc-booker' ); ?>" />
			</div>

			<div class="doc-booker-directory__field">
				<label for="doc-booker-filter-date"><?php esc_html_e( 'Search by Date & Time', 'doc-booker' ); ?></label>
				<input type="date" id="doc-booker-filter-date" name="date" />
			</div>

			<div class="doc-booker-directory__field">
				<label for="doc-booker-filter-availability"><?php esc_html_e( 'Search by Availability', 'doc-booker' ); ?></label>
				<select id="doc-booker-filter-availability" name="availability">
					<option value=""><?php esc_html_e( 'Please Select', 'doc-booker' ); ?></option>
					<option value="both"><?php esc_html_e( 'Both', 'doc-booker' ); ?></option>
					<option value="online"><?php esc_html_e( 'Online', 'doc-booker' ); ?></option>
					<option value="in-hub"><?php esc_html_e( 'In Hub', 'doc-booker' ); ?></option>
				</select>
			</div>
		</div>

		<div class="doc-booker-directory__actions">
			<button type="submit" class="doc-booker-directory__apply button button-primary">
				<?php esc_html_e( 'Apply Filter', 'doc-booker' ); ?>
			</button>
		</div>
	</form>

	<div class="doc-booker-directory__letter-filter" role="navigation" aria-label="<?php esc_attr_e( 'Filter by department initial', 'doc-booker' ); ?>">
		<button type="button" class="doc-booker-directory__letter is-active" data-letter="all"><?php esc_html_e( 'All', 'doc-booker' ); ?></button>
		<?php foreach ( $letters as $letter ) : ?>
			<button type="button" class="doc-booker-directory__letter" data-letter="<?php echo esc_attr( strtolower( $letter ) ); ?>"><?php echo esc_html( strtoupper( $letter ) ); ?></button>
		<?php endforeach; ?>
	</div>

	<div class="doc-booker-directory__summary">
		<p class="doc-booker-directory__counter">
			<?php printf( esc_html__( '%s doctors found', 'doc-booker' ), '<span id="doc-booker-directory-count">' . intval( $total ) . '</span>' ); ?>
		</p>
		<div class="doc-booker-directory__notice" id="doc-booker-directory-notice" hidden></div>
	</div>

	<div class="doc-booker-directory__results" id="doc-booker-directory-results">
		<?php echo $results_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</div>
