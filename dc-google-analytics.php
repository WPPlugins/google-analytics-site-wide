<?php
/*
  Plugin Name: Google Analytics Site Wide
  Plugin URI: http://dcweb.nu
  Description: A simple plugin for adding Google Analytics tracking code site wide for wp network
  Version: 1.0.1
  Author: Daniel Söderström 
  Author URI: http://dcweb.nu/
  License: GPL
*/

define( 'DC_TEXTDOMAIN', 'dc_gasw_textdomain' );

DC_Google_Analytics::get_instance();

class DC_Google_Analytics {
 
    private static $instance;
    private $excluded_sites = array();

    /**
     * Get instance for object
     */
    public static function get_instance() { 
      if ( ! self::$instance )
        self::$instance = new DC_Google_Analytics();

      return self::$instance;
    }
 
    /**
     * Class constructor with init method
     */
    public function __construct() {
      $this->init();
    }
 
    /**
     * Action and filter hooks
     */
    private function init() {

      // load textdomain
      add_action( 'plugins_loaded', array( $this, 'load_textdomain') );
      
 
      //add settings to network settings
      add_filter( 'wpmu_options'       , array( $this, 'show_network_settings' ) );
      add_action( 'update_wpmu_options', array( $this, 'save_network_settings' ) );

      if(! is_admin() ){
        $this->excluded_sites = $this->set_excluded_sites();
        $this->add_tracking_code();
      }
    }

    /**
     * Store excluded sites if there is
     */
    private function set_excluded_sites(){

      // get ids from site option, remove whitespaces.
      $sites = str_replace(' ', ',', get_site_option( 'dc-ga-exclude-sites' ) );
      $sites = explode( ',', $sites );

      $excluded = array();
      foreach( $sites as $site ){

        // only save digits
        if( is_numeric( $site ) ){
         $excluded[] = '_' . $site . '_'; // add som separators so we can use in_array
        }

      }

      if( !empty( $excluded ) )
        return $excluded;

      return false;

    }

    /**
     * Adding google code to header or footer if site is not excluded
     */
    public function add_tracking_code(){
      
      // create a search needle for current site id
      $search = '_' . get_current_blog_id() . '_';

      // check if current site is excluded
      // 
      if(!empty( $this->excluded_sites )){
        if( !in_array( $search, $this->excluded_sites )){
          
          // where to put google code
          if( get_site_option( 'dc-ga-location') == 'dc-ga-location-header')
            add_action( 'wp_head', array( $this, 'render_ga_code' ) );
          else
            add_action( 'wp_footer', array( $this, 'render_ga_code' ) );

        }
      }else{
        // where to put google code
        if( get_site_option( 'dc-ga-location') == 'dc-ga-location-header')
          add_action( 'wp_head', array( $this, 'render_ga_code' ) );
        else
          add_action( 'wp_footer', array( $this, 'render_ga_code' ) );        
      }

    }

    /**
     * Load textdomain
     */
    public function load_textdomain(){
      load_plugin_textdomain( DC_TEXTDOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 
    }

    /**
     * Render the html/script for analytics
     */
    public function render_ga_code() { 
    	$tracking_code = get_site_option( 'dc-ga-code'); 

    	$tracking_domain = get_site_option( 'dc-ga-domain'); 
      if(empty ($tracking_domain ))
        $tracking_domain = 'auto';
      
      $disable_universal = get_site_option( 'dc-ga-universal' );
      
      // bail if empty
    	if(empty( $tracking_code ))
    		return false;

      // print old analytics script if universal disabled
      if( $disable_universal == 1 ) :
    ?>
        <script>
        var _gaq = _gaq || [];
        _gaq.push(['_setAccount', '<?php echo $tracking_code ?>']);
        _gaq.push(['_trackPageview']);

        (function() {
          var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
          ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
          var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
        })();

      </script>

    <?php else: ?>
      
      <script>
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
        (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
        m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

        ga('create', '<?php echo $tracking_code ?>', '<?php echo $tracking_domain ?>');
        ga('send', 'pageview');
      </script>
    
    <?php endif; ?>

		<?php 
		} 	


    /**
     * Save network settings
     */
    public static function save_network_settings() {

      $post_options  = array_map( 'sanitize_text_field', $_POST['ccga'] );

      // delete checkbox value for disable universal
      delete_site_option( 'dc-ga-universal' );

      foreach ( $post_options as $name => $value ) {
        update_site_option( $name, $value );
      }

    }
 
    /**
     * Show setting fields on network settings
     */
    public static function show_network_settings() {
			$settings = self::get_network_settings();
    ?>
      <h3><?php _e( 'Google Analytics - Site Wide', DC_TEXTDOMAIN ); ?></h3>
      <table id="menu" class="form-table">
         
         <?php foreach ( $settings as $setting ) : ?>

          <tr valign="top">
            <th scope="row"><?php echo $setting['title']; ?></th>
            <td>

            	<?php if ($setting['type'] == 'radio'): ?>
            		<?php foreach ( $setting['fields'] as $s ) : ?>
            			<label><input type="radio" name="ccga[<?php echo $setting['name']; ?>]" id="<?php echo $s['id'] ?>" value="<?php echo $s['id']; ?>" <?php echo checked( $s['id'], get_site_option( $setting['name'] ) ); ?>/><?php echo $s['desc'] ?></label><br />
            		<?php endforeach; ?>

              <?php elseif ($setting['type'] == 'checkbox'): ?>
                <label><input type="<?php echo $setting['type'];?>" name="ccga[<?php echo $setting['name']; ?>]" value="1" <?php echo checked( '1', get_site_option( $setting['name'] ) ); ?> /><?php _e('Disable Universal Analytics', DC_TEXTDOMAIN ) ?></label>
                <p class="description"><?php echo $setting['desc']; ?></p>

            	<?php else: ?>	
            		<input type="<?php echo $setting['type'];?>" size="<?php echo $setting['size']; ?>" name="ccga[<?php echo $setting['id']; ?>]" value="<?php echo esc_attr( get_site_option( $setting['id'] ) ); ?>" />
              	<p class="description"><?php echo $setting['desc']; ?></p>
            	<?php endif; ?>
              
            </td>
          </tr>
          <?php endforeach; ?>
     	</table>
     	<?php
    }

    /**
     * Set field settings in array
     */
    public static function get_network_settings() {
 
        $settings[] = array(
          'id'   => 'dc-ga-code',
          'name' => 'dc-ga-code',
          'title' => __( 'Google Analytics Tracking Code', DC_TEXTDOMAIN  ),
          'desc' => 'UA-123456-1',
          'type' => 'text',
          'size' => '40'
        );
 
        $settings[] = array(
	        'id'   => 'dc-ga-domain',
	        'name' => 'dc-ga-domain',
	        'title' => __( 'Domain', DC_TEXTDOMAIN ),
	        'desc' => __(' Enter the domain (domainname.se, exclude http:// and www) that is registred for your tracking code.', DC_TEXTDOMAIN ),
	        'type' => 'text',
	        'size' => '40'
        );

        $settings[] = array(
          'id'   => 'dc-ga-universal',
          'name' => 'dc-ga-universal',
          'title' => __( 'Disable Universal Analytics', DC_TEXTDOMAIN ),
          'desc' => __('Default setting is for Universal Analytics, check this option if you need to use ga.js', DC_TEXTDOMAIN ),
          'type' => 'checkbox'
        );        


        $settings[] = array(
          'id'   => 'dc-ga-exclude-sites',
          'name' => 'dc-ga-exclude-sites',
          'title' => __( 'Excluded sites', DC_TEXTDOMAIN ),
          'desc' => __('Enter the id for the site you wish to exclude (use a comma separated list for multiple sites).', DC_TEXTDOMAIN ),
          'type' => 'text',
          'size' => '40'
        );        

        $settings[] = array(
	        'name'   => 'dc-ga-location',
	        'title' => __( 'Location', DC_TEXTDOMAIN ),
	        'desc' => 'des',
	        'type' => 'radio', 
	        'fields' => array(
	        	array(
	        		'id' => 'dc-ga-location-header',
	        		'desc' => __('Add to page header', DC_TEXTDOMAIN )
	    			),
	        	array(
	        		'id' => 'dc-ga-location-footer',
	        		'desc' => __('Add to page footer', DC_TEXTDOMAIN )
	    			)
	    		)
        );
 
        return apply_filters( 'plugin_settings', $settings );
    }
}




?>