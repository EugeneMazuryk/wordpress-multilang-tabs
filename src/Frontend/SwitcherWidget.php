<?php
namespace WebLorem\MultilangTabs\Frontend;

defined( 'ABSPATH' ) || exit;

final class SwitcherWidget extends \WP_Widget {

	public function __construct() {
		parent::__construct(
			'wl_mlt_switcher',
			__( 'Language switcher', 'weblorem-multilang-tabs' ),
			array( 'description' => __( 'Links to the other languages of the current page.', 'weblorem-multilang-tabs' ) )
		);
	}

	public function widget( $args, $instance ): void {
		$html = Switcher::render( $this->options( (array) $instance ) );

		if ( '' === $html ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function form( $instance ): void {
		$o = $this->options( (array) $instance );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>"><?php esc_html_e( 'Style', 'weblorem-multilang-tabs' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'style' ) ); ?>">
				<option value="list" <?php selected( $o['style'], 'list' ); ?>><?php esc_html_e( 'List', 'weblorem-multilang-tabs' ); ?></option>
				<option value="dropdown" <?php selected( $o['style'], 'dropdown' ); ?>><?php esc_html_e( 'Dropdown', 'weblorem-multilang-tabs' ); ?></option>
			</select>
		</p>
		<?php foreach ( array(
			'show_flags'        => __( 'Show flags', 'weblorem-multilang-tabs' ),
			'show_names'        => __( 'Show names', 'weblorem-multilang-tabs' ),
			'hide_untranslated' => __( 'Hide untranslated languages', 'weblorem-multilang-tabs' ),
		) as $key => $label ) : ?>
			<p>
				<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" value="1" <?php checked( $o[ $key ] ); ?>>
				<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $label ); ?></label>
			</p>
		<?php endforeach;
	}

	public function update( $new, $old ): array {
		return array(
			'style'             => 'dropdown' === ( $new['style'] ?? '' ) ? 'dropdown' : 'list',
			'show_flags'        => ! empty( $new['show_flags'] ) ? 1 : 0,
			'show_names'        => ! empty( $new['show_names'] ) ? 1 : 0,
			'hide_untranslated' => ! empty( $new['hide_untranslated'] ) ? 1 : 0,
			'saved'             => 1,
		);
	}

	private function options( array $instance ): array {
		$defaults = Switcher::defaults();

		// A fresh widget has no saved flags yet: use the defaults, not "all unchecked".
		if ( empty( $instance['saved'] ) ) {
			return $defaults;
		}

		return array(
			'style'             => $instance['style'] ?? 'list',
			'show_flags'        => ! empty( $instance['show_flags'] ),
			'show_names'        => ! empty( $instance['show_names'] ),
			'hide_untranslated' => ! empty( $instance['hide_untranslated'] ),
		);
	}
}
