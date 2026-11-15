<?php
/**
 * Classic widget: a short list of menu items for sidebars and footers.
 * (Block-based widget areas can use the Menu Items List block instead.)
 *
 * @package SidcraftRestaurant
 */

namespace SidcraftRestaurant;

defined( 'ABSPATH' ) || exit;

/**
 * Class Widget
 */
class Widget extends \WP_Widget {

	/**
	 * Register the widget.
	 */
	public static function register() {
		register_widget( __CLASS__ );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'sidrest_items',
			__( 'Restaurant Menu Items', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			array(
				'description'                 => __( 'A short list of dishes or drinks: a section, a category, specials or the newest items.', 'sidcraft-restaurant-for-sidcraft-commerce' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	protected function defaults() {
		return array(
			'title'            => '',
			'source'           => 'latest',
			'section'          => '',
			'category'         => '',
			'badge'            => 'special',
			'limit'            => 5,
			'show_images'      => 1,
			'show_prices'      => 1,
			'show_description' => 0,
		);
	}

	/**
	 * Front end.
	 *
	 * @param array $args     Widget area args.
	 * @param array $instance Settings.
	 */
	public function widget( $args, $instance ) {
		$instance = array_merge( $this->defaults(), (array) $instance );
		$unit     = Units::get( 'items' );
		$html     = $unit ? $unit->render( $instance ) : '';
		if ( '' === $html ) {
			return;
		}
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme markup.
		if ( '' !== $instance['title'] ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ) ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme markup around an escaped title.
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the Unit.
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme markup.
	}

	/**
	 * Settings form.
	 *
	 * @param array $instance Settings.
	 * @return string
	 */
	public function form( $instance ) {
		$instance = array_merge( $this->defaults(), (array) $instance );
		$sections = get_posts(
			array(
				'post_type'      => Post_Types::SECTION,
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin form.
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$terms    = all_terms( Post_Types::CATEGORY );
		$sources  = array(
			'latest'   => __( 'Newest items', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'section'  => __( 'Items in a section', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'category' => __( 'Items in a category', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'badge'    => __( 'Items with a badge', 'sidcraft-restaurant-for-sidcraft-commerce' ),
			'promo'    => __( 'Items on promotion now', 'sidcraft-restaurant-for-sidcraft-commerce' ),
		);
		?>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>"></p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'source' ) ); ?>"><?php esc_html_e( 'Show', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'source' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'source' ) ); ?>">
				<?php foreach ( $sources as $value => $text ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $instance['source'], $value ); ?>><?php echo esc_html( $text ); ?></option>
				<?php endforeach; ?>
			</select></p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'section' ) ); ?>"><?php esc_html_e( 'Section', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'section' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'section' ) ); ?>">
				<option value=""><?php esc_html_e( '— Choose —', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></option>
				<?php foreach ( $sections as $section ) : ?>
					<option value="<?php echo esc_attr( $section->ID ); ?>" <?php selected( (string) $instance['section'], (string) $section->ID ); ?>><?php echo esc_html( $section->post_title ); ?></option>
				<?php endforeach; ?>
			</select></p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'category' ) ); ?>"><?php esc_html_e( 'Category', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'category' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'category' ) ); ?>">
				<option value=""><?php esc_html_e( '— Any —', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></option>
				<?php foreach ( is_array( $terms ) ? $terms : array() as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $instance['category'], $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select></p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'badge' ) ); ?>"><?php esc_html_e( 'Badge', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'badge' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'badge' ) ); ?>">
				<?php foreach ( badge_options() as $value => $text ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $instance['badge'], $value ); ?>><?php echo esc_html( $text ); ?></option>
				<?php endforeach; ?>
			</select></p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( 'Number of items', 'sidcraft-restaurant-for-sidcraft-commerce' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="number" min="1" max="30" value="<?php echo esc_attr( $instance['limit'] ); ?>"></p>
		<?php foreach ( array( 'show_images' => __( 'Photos', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'show_prices' => __( 'Prices', 'sidcraft-restaurant-for-sidcraft-commerce' ), 'show_description' => __( 'Descriptions', 'sidcraft-restaurant-for-sidcraft-commerce' ) ) as $key => $text ) : ?>
			<p><label><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" value="1" <?php checked( ! empty( $instance[ $key ] ) ); ?>> <?php echo esc_html( $text ); ?></label></p>
		<?php endforeach; ?>
		<?php
		return '';
	}

	/**
	 * Save settings.
	 *
	 * @param array $new_instance New.
	 * @param array $old_instance Old.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP_Widget signature.
		$source = sanitize_key( $new_instance['source'] ?? 'latest' );
		return array(
			'title'            => sanitize_text_field( $new_instance['title'] ?? '' ),
			'source'           => in_array( $source, array( 'latest', 'section', 'category', 'badge', 'promo' ), true ) ? $source : 'latest',
			'section'          => (string) absint( $new_instance['section'] ?? 0 ),
			'category'         => sanitize_title( $new_instance['category'] ?? '' ),
			'badge'            => sanitize_key( $new_instance['badge'] ?? 'special' ),
			'limit'            => max( 1, min( 30, absint( $new_instance['limit'] ?? 5 ) ) ),
			'show_images'      => empty( $new_instance['show_images'] ) ? 0 : 1,
			'show_prices'      => empty( $new_instance['show_prices'] ) ? 0 : 1,
			'show_description' => empty( $new_instance['show_description'] ) ? 0 : 1,
		);
	}
}
