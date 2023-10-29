<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package     Featured_Image_With_URL
 * @subpackage  Featured_Image_With_URL/admin
 * @copyright   Copyright (c) 2018, Harikrut Technolab
 * @since       1.0.0
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The admin-specific functionality of the plugin.
 *
 * @package     Featured_Image_With_URL
 * @subpackage  Featured_Image_With_URL/admin
 */
class Featured_Image_With_URL_Admin {

	public $image_meta_url = '_harikrutfiwu_url';
	public $image_meta_alt = '_harikrutfiwu_alt';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( is_admin() ){
			add_action( 'add_meta_boxes', array( $this, 'harikrutfiwu_add_metabox' ), 10, 2 );
			add_action( 'save_post', array( $this, 'harikrutfiwu_save_image_url_data' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles') );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts') );
			add_action( 'admin_menu', array( $this, 'harikrutfiwu_add_options_page' ) );
			add_action( 'admin_init', array( $this, 'harikrutfiwu_settings_init' ) );
			// Add & Save Product Variation Featured image by URL.
			add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'harikrutfiwu_add_product_variation_image_selector' ), 10, 3 );
			add_action( 'woocommerce_save_product_variation', array( $this, 'harikrutfiwu_save_product_variation_image' ), 10, 2 );
		}
	}

	/**
	 * Add Meta box for Featured Image with URL.
	 *
	 * @since 1.0
	 * @return void
	 */
	function harikrutfiwu_add_metabox( $post_type, $post ) {
		
		$options = get_option( HARIKRUTFIWU_OPTIONS );
		$disabled_posttypes = isset( $options['disabled_posttypes'] ) ? $options['disabled_posttypes']  : array();
		if( in_array( $post_type, $disabled_posttypes ) ){
			return;
		}

		add_meta_box( 'harikrutfiwu_metabox',
						__('Featured Image with URL', 'featured-image-with-url' ), 
						array( $this, 'harikrutfiwu_render_metabox' ),
						$this->harikrutfiwu_get_posttypes(),
						'side',
						'low'
					);

		add_meta_box( 'harikrutfiwu_wcgallary_metabox',
						__('Product gallery by URLs', 'featured-image-with-url' ), 
						array( $this, 'harikrutfiwu_render_wcgallary_metabox' ),
						'product',
						'side',
						'low'
					);

	}

	/**
	 * Render Meta box for Featured Image with URL.
	 *
	 * @since 1.0
	 * @return void
	 */
	function harikrutfiwu_render_metabox(  $post ) {
		
		$image_meta = $this->harikrutfiwu_get_image_meta(  $post->ID );

		// Include Metabox Template.
		include HARIKRUTFIWU_PLUGIN_DIR .'templates/harikrutfiwu-metabox.php';

	}

	/**
	 * Render Meta box for Product gallary by URLs
	 *
	 * @since 1.0
	 * @return void
	 */
	function harikrutfiwu_render_wcgallary_metabox(  $post ) {
		
		// Include WC Gallary Metabox Template.
		include HARIKRUTFIWU_PLUGIN_DIR .'templates/harikrutfiwu-wcgallary-metabox.php';

	}

	/**
	 * Load Admin Styles.
	 *
	 * Enqueues the required admin styles.
	 *
	 * @since 1.0
	 * @param string $hook Page hook
	 * @return void
	 */
	function enqueue_admin_styles( $hook ) {
		
		$css_dir = HARIKRUTFIWU_PLUGIN_URL . 'assets/css/';
	 	wp_enqueue_style('harikrutfiwu-admin', $css_dir . 'featured-image-with-url-admin.css', array(), '1.0.0', "" );
		
	}

	/**
	 * Load Admin Scripts.
	 *
	 * Enqueues the required admin scripts.
	 *
	 * @since 1.0
	 * @param string $hook Page hook
	 * @return void
	 */
	function enqueue_admin_scripts( $hook ) {

		$js_dir  = HARIKRUTFIWU_PLUGIN_URL . 'assets/js/';
		wp_register_script( 'harikrutfiwu-admin', $js_dir . 'featured-image-with-url-admin.js', array('jquery' ) );
		$strings = array(
			'invalid_image_url' => __('Error in Image URL', 'featured-image-with-url'),
		);
		wp_localize_script( 'harikrutfiwu-admin', 'harikrutfiwujs', $strings );
		wp_enqueue_script( 'harikrutfiwu-admin' );

	}

	/**
	 * Add Meta box for Featured Image with URL.
	 *
	 * @since 1.0
	 * @return void
	 */
	function harikrutfiwu_save_image_url_data( $post_id, $post ) {

		$cap = $post->post_type === 'page' ? 'edit_page' : 'edit_post';
		if ( ! current_user_can( $cap, $post_id ) || ! post_type_supports( $post->post_type, 'thumbnail' ) || defined( 'DOING_AUTOSAVE' ) ) {
			return;
		}

		if( isset( $_POST['harikrutfiwu_url'] ) ){ // phpcs:ignore WordPress.Security.NonceVerification.Missing
			global $harikrutfiwu;
			// Update Featured Image URL
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$image_url = isset( $_POST['harikrutfiwu_url'] ) ? esc_url_raw( $_POST['harikrutfiwu_url'] ) : '';
			$image_alt = isset( $_POST['harikrutfiwu_alt'] ) ? wp_strip_all_tags( $_POST['harikrutfiwu_alt'] ): ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

			if ( $image_url != '' ){
				if( get_post_type( $post_id ) == 'product' ){
					$img_url = get_post_meta( $post_id, $this->image_meta_url , true );
					if( is_array( $img_url ) && isset( $img_url['img_url'] ) && $image_url == $img_url['img_url'] ){
							$image_url = array(
								'img_url' => $image_url,
								'width'	  => $img_url['width'],
								'height'  => $img_url['height']
							);
					}else{
						$imagesize = @getimagesize( $image_url );
						$image_url = array(
							'img_url' => $image_url,
							'width'	  => isset( $imagesize[0] ) ? $imagesize[0] : '',
							'height'  => isset( $imagesize[1] ) ? $imagesize[1] : ''
						);
					}
				}

				update_post_meta( $post_id, $this->image_meta_url, $image_url );
				if( $image_alt ){
					update_post_meta( $post_id, $this->image_meta_alt, $image_alt );
				}
			}else{
				delete_post_meta( $post_id, $this->image_meta_url );
				delete_post_meta( $post_id, $this->image_meta_alt );
			}
		}

		if( isset( $_POST['harikrutfiwu_wcgallary'] ) ){ // phpcs:ignore WordPress.Security.NonceVerification.Missing
			// Update WC Gallery
			$harikrutfiwu_wcgallary = isset( $_POST['harikrutfiwu_wcgallary'] ) ? $this->harikrutfiwu_sanitize( $_POST['harikrutfiwu_wcgallary'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if( empty( $harikrutfiwu_wcgallary ) || $post->post_type != 'product' ){
				return;
			}

			$old_images = $harikrutfiwu->common->harikrutfiwu_get_wcgallary_meta( $post_id );
			if( !empty( $old_images ) ){
				foreach ($old_images as $key => $value) {
					$old_images[$value['url']] = $value;
				}
			}

			$gallary_images = array();
			if( !empty( $harikrutfiwu_wcgallary ) ){
				foreach ($harikrutfiwu_wcgallary as $harikrutfiwu_gallary ) {
					if( isset( $harikrutfiwu_gallary['url'] ) && $harikrutfiwu_gallary['url'] != '' ){
						$gallary_image = array();
						$gallary_image['url'] = $harikrutfiwu_gallary['url'];

						if( isset( $old_images[$gallary_image['url']]['width'] ) && $old_images[$gallary_image['url']]['width'] != '' ){
							$gallary_image['width'] = isset( $old_images[$gallary_image['url']]['width'] ) ? $old_images[$gallary_image['url']]['width'] : '';
							$gallary_image['height'] = isset( $old_images[$gallary_image['url']]['height'] ) ? $old_images[$gallary_image['url']]['height'] : '';

						}else{
							$imagesizes = @getimagesize( $harikrutfiwu_gallary['url'] );
							$gallary_image['width'] = isset( $imagesizes[0] ) ? $imagesizes[0] : '';
							$gallary_image['height'] = isset( $imagesizes[1] ) ? $imagesizes[1] : '';
						}

						$gallary_images[] = $gallary_image;
					}
				}
			}

			if( !empty( $gallary_images ) ){
				update_post_meta( $post_id, HARIKRUTFIWU_WCGALLARY, $gallary_images );
			}else{
				delete_post_meta( $post_id, HARIKRUTFIWU_WCGALLARY );
			}
		}
	}

	/**
	 * Get Image metadata by post_id
	 *
	 * @since 1.0
	 * @return array
	 */
	function harikrutfiwu_get_image_meta( $post_id, $is_single_page = false ){
		
		$image_meta  = array();

		$img_url = get_post_meta( $post_id, $this->image_meta_url, true );
		$img_alt = get_post_meta( $post_id, $this->image_meta_alt, true );
		
		if( is_array( $img_url ) && isset( $img_url['img_url'] ) ){
			$image_meta['img_url'] 	 = $img_url['img_url'];	
		}else{
			$image_meta['img_url'] 	 = $img_url;
		}
		$image_meta['img_alt'] 	 = $img_alt;
		if( ( 'product_variation' == get_post_type( $post_id ) || 'product' == get_post_type( $post_id ) ) && $is_single_page ){
			if( isset( $img_url['width'] ) ){
				$image_meta['width'] 	 = $img_url['width'];
				$image_meta['height'] 	 = $img_url['height'];
			}else{

				if( isset( $image_meta['img_url'] ) && $image_meta['img_url'] != '' ){
					$imagesize = @getimagesize( $image_meta['img_url'] );
					$image_url = array(
						'img_url' => $image_meta['img_url'],
						'width'	  => isset( $imagesize[0] ) ? $imagesize[0] : '',
						'height'  => isset( $imagesize[1] ) ? $imagesize[1] : ''
					);
					update_post_meta( $post_id, $this->image_meta_url, $image_url );
					$image_meta = $image_url;	
				}				
			}
		}
		return $image_meta;
	}

	/**
	 * Adds Settings Page
	 *
	 * @since 1.0
	 * @return void
	 */
	function harikrutfiwu_add_options_page() {
		 add_options_page( __('Featured Image with URL', 'featured-image-with-url' ), __('Featured Image with URL', 'featured-image-with-url' ), 'manage_options', 'harikrutfiwu', array( $this, 'harikrutfiwu_options_page_html' ) );
	}

	/**
	 * Settings Page HTML
	 *
	 * @since 1.0
	 * @return array|null
	 */
	function harikrutfiwu_options_page_html() {
		// check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				// output security fields for the registered setting "harikrutfiwu"
				settings_fields( 'harikrutfiwu' );
				
				// output setting sections and their fields
				do_settings_sections( 'harikrutfiwu' );
				
				// output save settings button
				submit_button( 'Save Settings' );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register custom settings, Sections & fields
	 *
	 * @since 1.0
	 * @return void
	 */
	function harikrutfiwu_settings_init() {
		register_setting( 'harikrutfiwu', HARIKRUTFIWU_OPTIONS );
 
		add_settings_section(
			'harikrutfiwu_section',
			__( 'Settings', 'featured-image-with-url' ),
			array( $this, 'harikrutfiwu_section_callback' ),
			'harikrutfiwu'
		);
 
		// register a new field in the "harikrutfiwu_section" section, inside the "harikrutfiwu" page
		add_settings_field(
			'disabled_posttypes',
			__( 'Disable Post types', 'featured-image-with-url' ),
			array( $this, 'disabled_posttypes_callback' ),
			'harikrutfiwu',
			'harikrutfiwu_section',
			array(
				'label_for' => 'disabled_posttypes',
				'class' 	=> 'harikrutfiwu_row',
			)
		);

		add_settings_field(
			'resize_images',
			__( 'Display Resized Images', 'featured-image-with-url' ),
			array( $this, 'resize_images_callback' ),
			'harikrutfiwu',
			'harikrutfiwu_section',
			array(
				'label_for' => 'resize_images',
				'class' 	=> 'harikrutfiwu_row',
			)
		);
	}

	/**
	 * Callback function for harikrutfiwu section.
	 *
	 * @since 1.0
	 * @return void
	 */
	function harikrutfiwu_section_callback( $args ) {
		// Do some HTML here.
	}

	/**
	 * Callback function for disabled_posttypes field.
	 *
	 * @since 1.0
	 * @return void
	 */
	function disabled_posttypes_callback( $args ) {
		// get the value of the setting we've registered with register_setting()
		global $wp_post_types;
		
		$options = get_option( HARIKRUTFIWU_OPTIONS );
		$post_types = $this->harikrutfiwu_get_posttypes( true );
		$disabled_posttypes = isset( $options['disabled_posttypes'] ) ? $options['disabled_posttypes']  : array();

		if( !empty( $post_types ) ){
			foreach ($post_types as $key => $post_type ) {
				?>
				<label for="<?php echo esc_attr( $key ); ?>" style="display: block;">
		            <input name="<?php echo esc_attr( HARIKRUTFIWU_OPTIONS.'['. $args['label_for'] .']' ); ?>[]" class="disabled_posttypes" id="<?php echo esc_attr( $key ); ?>" type="checkbox" value="<?php echo esc_attr( $key ); ?>" <?php if( in_array( $key, $disabled_posttypes ) ){ echo 'checked="checked"'; } ?> >
		            <?php echo $posttype_title = isset( $wp_post_types[$key]->label ) ? esc_html( $wp_post_types[$key]->label ) : esc_html( ucfirst( $key) ); ?>
		        </label>
				<?php
			}
		}
		?>
		<p class="description">
			<?php esc_html_e( 'Please check checkbox for posttypes on which you want to disable Featured image by URL.', 'featured-image-with-url' ); ?>
		</p>

		<?php
	}

	/**
	 * Callback function for resize_images field.
	 *
	 * @since 1.0
	 * @return void
	 */
	function resize_images_callback( $args ) {
		// get the value of the setting we've registered with register_setting()
		$options = get_option( HARIKRUTFIWU_OPTIONS );
		$resize_images = isset( $options['resize_images'] ) ? $options['resize_images']  : false;
		?>
		<label for="resize_images">
			<input name="<?php echo esc_attr( HARIKRUTFIWU_OPTIONS.'['. $args['label_for'] .']' ); ?>" type="checkbox" value="1" id="resize_images" <?php if ( !defined( 'JETPACK__VERSION' ) ) { echo 'disabled="disabled"'; }else{ if( $resize_images ){ echo 'checked="checked"'; } } ?>>
			<?php esc_html_e( 'Enable display resized images for image sizes like thumbnail, medium, large etc..', 'featured-image-with-url' ); ?>
			
		</label>
		<p class="description">
			<?php esc_html_e( 'You need Jetpack plugin installed & connected  for enable this functionality.', 'featured-image-with-url' ); ?>
		</p>

		<?php
	}

	/**
	 * Get Post Types which supports Featured image with URL.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	function harikrutfiwu_get_posttypes( $raw = false ) {

		$post_types = array_diff( get_post_types( array( 'public' => true ), 'names' ), array( 'nav_menu_item', 'attachment', 'revision' ) );
		if( !empty( $post_types ) ){
			foreach ( $post_types as $key => $post_type ) {
				if( !post_type_supports( $post_type, 'thumbnail' ) ){
					unset( $post_types[$key] );
				}
			}
		}
		if( $raw ){
			return $post_types;	
		}else{
			$options = get_option( HARIKRUTFIWU_OPTIONS );
			$disabled_posttypes = isset( $options['disabled_posttypes'] ) ? $options['disabled_posttypes']  : array();
			$post_types = array_diff( $post_types, $disabled_posttypes );
		}

		return $post_types;
	}

	/**
	 * Render Featured image by URL in Product variation
	 *
	 * @return void
	 */
	public function harikrutfiwu_add_product_variation_image_selector( $loop, $variation_data, $variation ){
		$harikrutfiwu_url = '';
		if( isset( $variation_data['_harikrutfiwu_url'][0] ) ){
			$harikrutfiwu_url = $variation_data['_harikrutfiwu_url'][0];
			$harikrutfiwu_url = maybe_unserialize( $harikrutfiwu_url );
			if( is_array( $harikrutfiwu_url ) ){
				$harikrutfiwu_url = $harikrutfiwu_url['img_url'];
			}
		}
		?>
		<div id="harikrutfiwu_product_variation_<?php echo esc_attr( $variation->ID ); ?>" class="harikrutfiwu_product_variation form-row form-row-first">
			<label for="harikrutfiwu_pvar_url_<?php echo esc_attr( $variation->ID ); ?>">
				<strong><?php _e('Product Variation Image by URL', 'featured-image-with-url') ?></strong>
			</label>

			<div id="harikrutfiwu_pvar_img_wrap_<?php echo esc_attr( $variation->ID ); ?>" class="harikrutfiwu_pvar_img_wrap" style="<?php if( $harikrutfiwu_url == '' ){ echo 'display:none'; } ?>" >
				<span href="#" class="harikrutfiwu_pvar_remove" data-id="<?php echo esc_attr( $variation->ID ); ?>"></span>
				<img id="harikrutfiwu_pvar_img_<?php echo esc_attr( $variation->ID  ); ?>" class="harikrutfiwu_pvar_img" data-id="<?php echo esc_attr( $variation->ID ); ?>" src="<?php echo esc_attr( $harikrutfiwu_url ); ?>" />
			</div>
			<div id="harikrutfiwu_url_wrap_<?php echo esc_attr( $variation->ID ); ?>" style="<?php if( $harikrutfiwu_url != '' ){ echo 'display:none'; } ?>" >
				<input id="harikrutfiwu_pvar_url_<?php echo esc_attr( $variation->ID ); ?>" class="harikrutfiwu_pvar_url" type="text" name="harikrutfiwu_pvar_url[<?php echo esc_attr( $variation->ID ); ?>]" placeholder="<?php _e('Product Variation Image URL', 'featured-image-with-url'); ?>" value="<?php echo esc_attr( $harikrutfiwu_url ); ?>"/>
				<a id="harikrutfiwu_pvar_preview_<?php echo esc_attr( $variation->ID ); ?>" class="harikrutfiwu_pvar_preview button" data-id="<?php echo esc_attr( $variation->ID ); ?>">
					<?php _e( 'Preview', 'featured-image-with-url' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Featured image by URL for Product variation
	 *
	 * @return void
	 */
	public function harikrutfiwu_save_product_variation_image( $variation_id, $i ){

		$image_url = isset( $_POST['harikrutfiwu_pvar_url'][$variation_id] ) ? esc_url_raw( $_POST['harikrutfiwu_pvar_url'][$variation_id] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if( $image_url != '' ){
			$img_url = get_post_meta( $variation_id, $this->image_meta_url , true );
			if( is_array( $img_url ) && isset( $img_url['img_url'] ) && $image_url == $img_url['img_url'] ){
					$image_url = array(
						'img_url' => $image_url,
						'width'	  => $img_url['width'],
						'height'  => $img_url['height']
					);
			}else{
				$imagesize = @getimagesize( $image_url );
				$image_url = array(
					'img_url' => $image_url,
					'width'	  => isset( $imagesize[0] ) ? $imagesize[0] : '',
					'height'  => isset( $imagesize[1] ) ? $imagesize[1] : ''
				);
			}
			update_post_meta( $variation_id, $this->image_meta_url, $image_url );
		}else{
			delete_post_meta( $variation_id, $this->image_meta_url );
		}
	}

	/**
	 * Sanitize variables using sanitize_text_field and wp_unslash.
	 *
	 * @param string|array $var Data to sanitize.
	 * @return string|array
	 */
	public function harikrutfiwu_sanitize( $var ) {
		if ( is_array( $var ) ) {
			return array_map( array( $this, 'harikrutfiwu_sanitize' ), $var );
		} else {
			return is_scalar( $var ) ? sanitize_text_field( wp_unslash( $var ) ) : $var;
		}
	}
}