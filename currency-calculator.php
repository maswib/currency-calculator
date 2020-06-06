<?php
/**
 * Plugin Name: Currency Calculator
 * Plugin URI: https://wahyuwibowo.com/projects/currency-calculator/
 * Description: Calculate exchange rates between two currencies.
 * Author: Wahyu Wibowo
 * Author URI: https://wahyuwibowo.com
 * Version: 1.0.1
 * Text Domain: currency-calculator
 * Domain Path: languages
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class Currency_Calculator {
    
    private static $_instance = NULL;
    private $default_options = NULL;
    private $api_url = 'https://openexchangerates.org/api/';
    
    /**
     * Initialize all variables, filters and actions
     */
    public function __construct() {
        $this->default_options = array(
            'open_exchange_rates_app_id' => ''
        );
        
        add_action( 'admin_init',                        array( $this, 'settings_init' ) );
        add_action( 'admin_init',                        array( $this, 'update_currency' ) );
        add_action( 'admin_menu',                        array( $this, 'admin_menu' ) );
        add_action( 'init',                              array( $this, 'load_plugin_textdomain' ), 0 );
        add_action( 'wp_enqueue_scripts',                array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_currency_calculate',        array( $this, 'calculate' ) );
        add_action( 'wp_ajax_nopriv_currency_calculate', array( $this, 'calculate' ) );
        add_filter( 'http_request_args',                 array( $this, 'dont_update_plugin' ), 5, 2 );
        
        add_shortcode( 'currency_calculator', array( $this, 'add_shortcode' ) );
    }
    
    /**
     * retrieve singleton class instance
     * @return instance reference to plugin
     */
    public static function instance() {
        if ( NULL === self::$_instance ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    public function admin_menu() {
        add_options_page( __( 'Currency Calculator Settings', 'currency-calculator' ), __( 'Currency Calculator', 'currency-calculator' ), 'manage_options', 'currency-calculator', array( $this, 'admin_page' ) );
    }
    
    public function update_currency() {
        $sendback = remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), wp_get_referer() );
        
        if ( isset( $_REQUEST['page'] ) && 'currency-calculator' === $_REQUEST['page'] ) {
            if ( ! empty( $_POST['update_currency_list'] ) && 'Y' === $_POST['update_currency_list'] ) {
                check_admin_referer( 'currency_calculator' );
                
                $options = get_option( 'currency_calculator_options', $this->default_options );
                $api_url = add_query_arg( 
                    array( 
                        'app_id' => $options['open_exchange_rates_app_id'] 
                    ), 
                    $this->api_url . 'currencies.json'
                );
                
                $response = wp_remote_get( $api_url, $args );
                
                if ( is_array( $response ) && ! is_wp_error( $response ) ) {
                    $currencies = json_decode( $response['body'] );
                    update_option( 'currency_calculator_currencies', $currencies );
                }
                
                wp_redirect( $sendback );
                exit;
            }
        }
    }
    
    public function admin_page() {
        $options = get_option( 'currency_calculator_options', $this->default_options );
        ?>
        <div class="wrap">
            <h2><?php _e( 'Currency Calculator Settings', 'currency-calculator' );?></h2>
            <form method="post" action="options.php">
                <?php settings_fields( 'currency_calculator_options' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php echo esc_html__( 'Open Exchange Rates App ID', 'currency-calculator' );?></th>
                        <td class="forminp">
                            <input type="text" name="currency_calculator_options[open_exchange_rates_app_id]" value="<?php echo esc_attr( $options['open_exchange_rates_app_id'] ) ?>" class="regular-text">
                            <p class="description"><?php printf( '<a href="%s" target="_blank">Sign up</a> to Open Exchange Rates to get your App ID.', 'https://openexchangerates.org/signup' ) ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php echo esc_attr__( 'Save Changes', 'currency-calculator' ); ?>" />
                </p>
            </form>
            
            <h2><?php _e( 'Currency List', 'currency-calculator' );?></h2>
            
            <p><?php _e( 'This table displays the currency list supported by Open Exchange Rates.', 'currency-calculator' ) ?></p>
                
            <?php $currencies = get_option( 'currency_calculator_currencies', array() ); ?>
            
            <table class="wp-list-table widefat plugins">
                <thead>
                    <tr>
                        <td scope="col" id="name" class="manage-column column-name column-primary">Symbol</td>
                        <td scope="col" id="description" class="manage-column column-description">Description</td>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php if ( count( $currencies ) > 0 ): $i = 0; ?>
                    <?php foreach ( $currencies as $symbol => $description ): ?>
                    <tr class="<?php echo $i % 2 === 0 ? 'active' : 'inactive' ?>">
                        <td class="column-primary"><?php echo $symbol ?></td>
                        <td class="column-description"><?php echo $description ?></td>
                    </tr>
                    <?php $i++; endforeach ?>
                    <?php else: ?>
                    <tr><td colspan="2"><?php _e( 'You have no currency', 'currency-calculator' ) ?></td></tr>
                    <?php endif ?>
                </tbody>
            </table>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'currency_calculator' ); ?>
                <input type="hidden" name="update_currency_list" value="Y">
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php echo esc_attr__( 'Update Currency List', 'currency-calculator' ); ?>" />
                </p>
            </form>
        </div>
        <?php
    }
    
    public function settings_init() {
        register_setting( 'currency_calculator_options', 'currency_calculator_options', array( $this, 'settings_sanitize' ) );
    }
    
    public function settings_sanitize( $input ) {
        $options = get_option( 'currency_calculator_options', $this->default_options );
        $keys = array( 'open_exchange_rates_app_id' );
        
        foreach ( $keys as $key ) {
            $options[$key] = sanitize_text_field( $input[$key] );
        }
        
        return $options;
    }
    
    public function load_plugin_textdomain() {
        $locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
        $locale = apply_filters( 'plugin_locale', $locale, 'currency-calculator' );
        
        unload_textdomain( 'currency-calculator' );
        load_textdomain( 'currency-calculator', WP_LANG_DIR . '/currency-calculator/currency-calculator-' . $locale . '.mo' );
        load_plugin_textdomain( 'currency-calculator', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
    }
    
    public function dont_update_plugin( $r, $url ) {
        if ( 0 !== strpos( $url, 'https://api.wordpress.org/plugins/update-check/1.1/' ) ) {
            return $r; // Not a plugin update request. Bail immediately.
        }
        
        $plugins = json_decode( $r['body']['plugins'], true );
        unset( $plugins['plugins'][plugin_basename( __FILE__ )] );
        $r['body']['plugins'] = json_encode( $plugins );
        
        return $r;
    }
    
    public function enqueue_scripts() {
        wp_register_script( 'currency-calculator-frontend', plugin_dir_url( __FILE__ ) . 'assets/js/frontend.js', array( 'jquery' ), false, true );
        wp_enqueue_style( 'currency-calculator-frontend', plugin_dir_url( __FILE__ ) . 'assets/css/frontend.css' );
        
        wp_localize_script( 'currency-calculator-frontend', 'Currency_Calculator', array(
            'ajaxurl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'currency_calculator' ),
            'calculating' => __( 'Calculating...', 'currency-calculator' )
        ) );
    }
    
    private function get_exchange_rates() {
        $options = get_option( 'currency_calculator_options', $this->default_options );
        
        $api_url = add_query_arg( 
            array( 
                'app_id' => $options['open_exchange_rates_app_id'] ,
                'base'   => 'USD'
            ), 
            $this->api_url . 'latest.json'
        );
        
        $currency_rates = get_transient( 'currency_calculator_rates' );
        
        if ( $currency_rates ) {
            $rates = $currency_rates->rates;
        }

        $response = wp_remote_get( $api_url, $args );

        if ( is_array( $response ) && ! is_wp_error( $response ) ) {
            $currency_rates = json_decode( $response['body'] );
            set_transient( 'currency_calculator_rates', $currency_rates, DAY_IN_SECONDS );
        }
        
        return $currency_rates->rates;
    }
    
    public function add_shortcode() {
        wp_enqueue_script( 'currency-calculator-frontend' );
        $currencies = get_option( 'currency_calculator_currencies', array() );
        
        $output = '<div class="currency-calculator-container">';
        
        $output .= '<div class="currency-calculator-form-input">';
        $output .= '<table>';
        $output .= sprintf( '<tr><td>%s</td><td><input type="number" min="0" id="currency-calculator-amount" value="1"></td></tr>', __( 'Amount', 'currency-calculator' ) );
        $output .= sprintf( '<tr><td>%s</td><td><select id="currency-calculator-from">', __( 'From', 'currency-calculator' ) );
        foreach ( $currencies as $symbol => $description ) {
            $output .= sprintf( '<option value="%s"%s>%s (%s)</option>', $symbol, selected( 'USD', $symbol, false ), $symbol, $description );
        }
        $output .= '</select></td></tr>';
        
        $output .= sprintf( '<tr><td>%s</td><td><select id="currency-calculator-to">', __( 'To', 'currency-calculator' ) );
        foreach ( $currencies as $symbol => $description ) {
            $output .= sprintf( '<option value="%s"%s>%s (%s)</option>', $symbol, selected( 'IDR', $symbol, false ), $symbol, $description );
        }
        $output .= '</select></td></tr>';
        
        $output .= '</table>';
        $output .= sprintf( '<div class="currency-calculator-calculate"><button id="currency-calculator-calculate-button">%s</button></div>', __( 'Calculate', 'currency-calculator' ) );
        $output .= '</div>';
        
        $output .= '<div id="currency-calculator-output" class="currency-calculator-output"></div>';
        
        $output .= '</div>';
        
        return $output;
    }
    
    public function calculate() {
        check_ajax_referer( 'currency_calculator', 'nonce' );
        
        $amount = sanitize_text_field( $_POST['amount'] );
        $from   = sanitize_text_field( $_POST['from'] );
        $to     = sanitize_text_field( $_POST['to'] );
        
        $currency_rates = $this->get_exchange_rates();
        
        $currency_rate_from = 0;
        $currency_rate_to = 0;
        
        foreach ( $currency_rates as $symbol => $rate ) {
            if ( $from === $symbol ) {
                $currency_rate_from = $rate;
                break;
            }
        }
        
        foreach ( $currency_rates as $symbol => $rate ) {
            if ( $to === $symbol ) {
                $currency_rate_to = $rate;
                break;
            }
        }
        
        $result = $amount * $currency_rate_to / $currency_rate_from;
        
        $output = sprintf( '<div class="currency-calculator-output-title">%s</div>', __( 'Result', 'currency-calculator' ) );
        $output .= sprintf( '<div class="currency-calculator-output-number">%s %s = %s %s</div>', number_format( $amount, 2 ), $from, number_format( $result, 2 ), $to );
        
        wp_send_json_success( array( 
            'output' => $output
        ) );
    }

}

Currency_Calculator::instance();
