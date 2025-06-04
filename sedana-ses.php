<?php
/**
 * Plugin Name: SEDANA SES
 * Plugin URI: https://sedana.tg
 * Description: Configure WordPress to send all emails through Amazon SES using either API or SMTP.
 * Version: 1.0.0
 * Author: SEDANA
 * Author URI: https://sedana.tg
 * Text Domain: sedana-ses
 * Domain Path: /languages
 * License: GPL-2.0+
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('SEDANA_SES_VERSION', '1.0.0');
define('SEDANA_SES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SEDANA_SES_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The core plugin class
 */
class Sedana_SES {
    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Plugin settings
     */
    private $settings;

    /**
     * Get single instance of the plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Load settings
        $this->settings = get_option('sedana_ses_settings', array(
            'connection_type' => 'api',
            'aws_region' => '',
            'aws_access_key' => '',
            'aws_secret_key' => '',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_secure' => 'tls',
            'from_email' => '',
            'from_name' => '',
            'active' => false
        ));

        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_sedana_ses_test_connection', array($this, 'ajax_test_connection'));
        
        // Include API handler
        require_once SEDANA_SES_PLUGIN_DIR . 'includes/class-sedana-ses-api.php';
        
        // Only hook into wp_mail if active
        if ($this->settings['active']) {
            add_filter('wp_mail', array($this, 'filter_wp_mail'));
            
            // For API method, override wp_mail completely
            if ($this->settings['connection_type'] === 'api') {
                add_filter('pre_wp_mail', array($this, 'send_via_api'), 10, 2);
            } else {
                // For SMTP, configure PHPMailer
                add_filter('phpmailer_init', array($this, 'configure_phpmailer'));
            }
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('SEDANA SES Settings', 'sedana-ses'),
            __('SEDANA SES', 'sedana-ses'),
            'manage_options',
            'sedana-ses',
            array($this, 'settings_page')
        );
    }

    /**
     * Register settings
     */
    public function admin_init() {
        register_setting(
            'sedana_ses_settings',
            'sedana_ses_settings',
            array($this, 'validate_settings')
        );

        // Connection section
        add_settings_section(
            'sedana_ses_connection_section',
            __('Connection Settings', 'sedana-ses'),
            array($this, 'connection_section_callback'),
            'sedana-ses'
        );

        // Sender settings section
        add_settings_section(
            'sedana_ses_sender_section',
            __('Sender Settings', 'sedana-ses'),
            array($this, 'sender_section_callback'),
            'sedana-ses'
        );

        // Connection type field
        add_settings_field(
            'connection_type',
            __('Connection Type', 'sedana-ses'),
            array($this, 'connection_type_callback'),
            'sedana-ses',
            'sedana_ses_connection_section'
        );

        // API fields
        add_settings_field(
            'aws_region',
            __('AWS Region', 'sedana-ses'),
            array($this, 'aws_region_callback'),
            'sedana-ses',
            'sedana_ses_connection_section',
            array('class' => 'api-field')
        );

        add_settings_field(
            'aws_access_key',
            __('AWS Access Key', 'sedana-ses'),
            array($this, 'aws_access_key_callback'),
            'sedana-ses',
            'sedana_ses_connection_section',
            array('class' => 'api-field')
        );

        add_settings_field(
            'aws_secret_key',
            __('AWS Secret Key', 'sedana-ses'),
            array($this, 'aws_secret_key_callback'),
            'sedana-ses',
            'sedana_ses_connection_section',
            array('class' => 'api-field')
        );

        // SMTP fields
        add_settings_field(
            'smtp_host',
            __('SMTP Host', 'sedana-ses'),
            array($this, 'smtp_host_callback'),
            'sedana-ses',
            'sedana_ses_connection_section',
            array('class' => 'smtp-field')
        );

        add_settings_field(
            'smtp_port',
            __('SMTP Port', 'sedana-ses'),
            array($this, 'smtp_port_callback'),
            'sedana-ses',
            'sedana_ses_connection_section',
            array('class' => 'smtp-field')
        );

        add_settings_field(
            'smtp_secure',
            __('SMTP Security', 'sedana-ses'),
            array($this, 'smtp_secure_callback'),
            'sedana-ses',
            'sedana_ses_connection_section',
            array('class' => 'smtp-field')
        );

        add_settings_field(
            'smtp_username',
            __('SMTP Username', 'sedana-ses'),
            array($this, 'smtp_username_callback'),
            'sedana-ses',
            'sedana_ses_connection_section',
            array('class' => 'smtp-field')
        );

        add_settings_field(
            'smtp_password',
            __('SMTP Password', 'sedana-ses'),
            array($this, 'smtp_password_callback'),
            'sedana-ses',
            'sedana_ses_connection_section',
            array('class' => 'smtp-field')
        );

        // Sender settings
        add_settings_field(
            'from_email',
            __('From Email', 'sedana-ses'),
            array($this, 'from_email_callback'),
            'sedana-ses',
            'sedana_ses_sender_section'
        );

        add_settings_field(
            'from_name',
            __('From Name', 'sedana-ses'),
            array($this, 'from_name_callback'),
            'sedana-ses',
            'sedana_ses_sender_section'
        );

        // Activation toggle
        add_settings_field(
            'active',
            __('Activate SES', 'sedana-ses'),
            array($this, 'active_callback'),
            'sedana-ses',
            'sedana_ses_sender_section'
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_sedana-ses' !== $hook) {
            return;
        }

        wp_enqueue_style('sedana-ses-admin', SEDANA_SES_PLUGIN_URL . 'assets/css/admin.css', array(), SEDANA_SES_VERSION);
        wp_enqueue_script('sedana-ses-admin', SEDANA_SES_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), SEDANA_SES_VERSION, true);
        
        wp_localize_script('sedana-ses-admin', 'sedana_ses', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sedana_ses_nonce'),
            'testing' => __('Testing...', 'sedana-ses'),
            'success' => __('Success! Connection established.', 'sedana-ses'),
            'error' => __('Error: ', 'sedana-ses')
        ));
    }

    /**
     * Section callbacks
     */
    public function connection_section_callback() {
        echo '<p>' . __('Configure your Amazon SES connection settings.', 'sedana-ses') . '</p>';
    }

    public function sender_section_callback() {
        echo '<p>' . __('Configure your email sender details.', 'sedana-ses') . '</p>';
    }

    /**
     * Field callbacks
     */
    public function connection_type_callback() {
        $connection_type = isset($this->settings['connection_type']) ? $this->settings['connection_type'] : 'api';
        ?>
        <select name="sedana_ses_settings[connection_type]" id="connection_type">
            <option value="api" <?php selected($connection_type, 'api'); ?>><?php _e('AWS API', 'sedana-ses'); ?></option>
            <option value="smtp" <?php selected($connection_type, 'smtp'); ?>><?php _e('SMTP', 'sedana-ses'); ?></option>
        </select>
        <p class="description"><?php _e('Choose how to connect to Amazon SES.', 'sedana-ses'); ?></p>
        <?php
    }

    public function aws_region_callback() {
        $aws_region = isset($this->settings['aws_region']) ? $this->settings['aws_region'] : '';
        ?>
        <select name="sedana_ses_settings[aws_region]" id="aws_region">
            <option value="us-east-1" <?php selected($aws_region, 'us-east-1'); ?>>US East (N. Virginia)</option>
            <option value="us-east-2" <?php selected($aws_region, 'us-east-2'); ?>>US East (Ohio)</option>
            <option value="us-west-1" <?php selected($aws_region, 'us-west-1'); ?>>US West (N. California)</option>
            <option value="us-west-2" <?php selected($aws_region, 'us-west-2'); ?>>US West (Oregon)</option>
            <option value="ca-central-1" <?php selected($aws_region, 'ca-central-1'); ?>>Canada (Central)</option>
            <option value="eu-central-1" <?php selected($aws_region, 'eu-central-1'); ?>>EU (Frankfurt)</option>
            <option value="eu-west-1" <?php selected($aws_region, 'eu-west-1'); ?>>EU (Ireland)</option>
            <option value="eu-west-2" <?php selected($aws_region, 'eu-west-2'); ?>>EU (London)</option>
            <option value="eu-west-3" <?php selected($aws_region, 'eu-west-3'); ?>>EU (Paris)</option>
            <option value="eu-north-1" <?php selected($aws_region, 'eu-north-1'); ?>>EU (Stockholm)</option>
            <option value="eu-south-1" <?php selected($aws_region, 'eu-south-1'); ?>>EU (Milan)</option>
            <option value="ap-east-1" <?php selected($aws_region, 'ap-east-1'); ?>>Asia Pacific (Hong Kong)</option>
            <option value="ap-south-1" <?php selected($aws_region, 'ap-south-1'); ?>>Asia Pacific (Mumbai)</option>
            <option value="ap-northeast-1" <?php selected($aws_region, 'ap-northeast-1'); ?>>Asia Pacific (Tokyo)</option>
            <option value="ap-northeast-2" <?php selected($aws_region, 'ap-northeast-2'); ?>>Asia Pacific (Seoul)</option>
            <option value="ap-northeast-3" <?php selected($aws_region, 'ap-northeast-3'); ?>>Asia Pacific (Osaka)</option>
            <option value="ap-southeast-1" <?php selected($aws_region, 'ap-southeast-1'); ?>>Asia Pacific (Singapore)</option>
            <option value="ap-southeast-2" <?php selected($aws_region, 'ap-southeast-2'); ?>>Asia Pacific (Sydney)</option>
            <option value="sa-east-1" <?php selected($aws_region, 'sa-east-1'); ?>>South America (São Paulo)</option>
            <option value="af-south-1" <?php selected($aws_region, 'af-south-1'); ?>>Africa (Cape Town)</option>
            <option value="me-south-1" <?php selected($aws_region, 'me-south-1'); ?>>Middle East (Bahrain)</option>
        </select>
        <p class="description"><?php _e('Select your AWS region where SES is configured.', 'sedana-ses'); ?></p>
        <?php
    }

    public function aws_access_key_callback() {
        $aws_access_key = isset($this->settings['aws_access_key']) ? $this->settings['aws_access_key'] : '';
        ?>
        <input type="text" name="sedana_ses_settings[aws_access_key]" id="aws_access_key" value="<?php echo esc_attr($aws_access_key); ?>" class="regular-text">
        <p class="description"><?php _e('Enter your AWS Access Key.', 'sedana-ses'); ?></p>
        <?php
    }

    public function aws_secret_key_callback() {
        $aws_secret_key = isset($this->settings['aws_secret_key']) ? $this->settings['aws_secret_key'] : '';
        ?>
        <input type="password" name="sedana_ses_settings[aws_secret_key]" id="aws_secret_key" value="<?php echo esc_attr($aws_secret_key); ?>" class="regular-text">
        <p class="description"><?php _e('Enter your AWS Secret Key.', 'sedana-ses'); ?></p>
        <?php
    }

    public function smtp_host_callback() {
        $smtp_host = isset($this->settings['smtp_host']) ? $this->settings['smtp_host'] : '';
        ?>
        <input type="text" name="sedana_ses_settings[smtp_host]" id="smtp_host" value="<?php echo esc_attr($smtp_host); ?>" class="regular-text">
        <p class="description"><?php _e('Enter your Amazon SES SMTP server address (e.g., email-smtp.us-east-1.amazonaws.com).', 'sedana-ses'); ?></p>
        <?php
    }

    public function smtp_port_callback() {
        $smtp_port = isset($this->settings['smtp_port']) ? $this->settings['smtp_port'] : '587';
        ?>
        <input type="text" name="sedana_ses_settings[smtp_port]" id="smtp_port" value="<?php echo esc_attr($smtp_port); ?>" class="small-text">
        <p class="description"><?php _e('Enter the SMTP port (usually 587 for TLS or 465 for SSL).', 'sedana-ses'); ?></p>
        <?php
    }

    public function smtp_secure_callback() {
        $smtp_secure = isset($this->settings['smtp_secure']) ? $this->settings['smtp_secure'] : 'tls';
        ?>
        <select name="sedana_ses_settings[smtp_secure]" id="smtp_secure">
            <option value="tls" <?php selected($smtp_secure, 'tls'); ?>>TLS</option>
            <option value="ssl" <?php selected($smtp_secure, 'ssl'); ?>>SSL</option>
            <option value="" <?php selected($smtp_secure, ''); ?>>None</option>
        </select>
        <p class="description"><?php _e('Select the encryption method.', 'sedana-ses'); ?></p>
        <?php
    }

    public function smtp_username_callback() {
        $smtp_username = isset($this->settings['smtp_username']) ? $this->settings['smtp_username'] : '';
        ?>
        <input type="text" name="sedana_ses_settings[smtp_username]" id="smtp_username" value="<?php echo esc_attr($smtp_username); ?>" class="regular-text">
        <p class="description"><?php _e('Enter your SES SMTP username.', 'sedana-ses'); ?></p>
        <?php
    }

    public function smtp_password_callback() {
        $smtp_password = isset($this->settings['smtp_password']) ? $this->settings['smtp_password'] : '';
        ?>
        <input type="password" name="sedana_ses_settings[smtp_password]" id="smtp_password" value="<?php echo esc_attr($smtp_password); ?>" class="regular-text">
        <p class="description"><?php _e('Enter your SES SMTP password.', 'sedana-ses'); ?></p>
        <?php
    }

    public function from_email_callback() {
        $from_email = isset($this->settings['from_email']) ? $this->settings['from_email'] : get_option('admin_email');
        ?>
        <input type="email" name="sedana_ses_settings[from_email]" id="from_email" value="<?php echo esc_attr($from_email); ?>" class="regular-text">
        <p class="description"><?php _e('Enter the email address that emails should be sent from. This must be verified in your SES console.', 'sedana-ses'); ?></p>
        <?php
    }

    public function from_name_callback() {
        $from_name = isset($this->settings['from_name']) ? $this->settings['from_name'] : get_bloginfo('name');
        ?>
        <input type="text" name="sedana_ses_settings[from_name]" id="from_name" value="<?php echo esc_attr($from_name); ?>" class="regular-text">
        <p class="description"><?php _e('Enter the name that should appear as the sender.', 'sedana-ses'); ?></p>
        <?php
    }

    public function active_callback() {
        $active = isset($this->settings['active']) ? $this->settings['active'] : false;
        ?>
        <label>
            <input type="checkbox" name="sedana_ses_settings[active]" id="active" value="1" <?php checked($active, true); ?>>
            <?php _e('Enable sending emails through Amazon SES', 'sedana-ses'); ?>
        </label>
        <div class="test-connection-container">
            <button type="button" id="test-connection" class="button button-secondary"><?php _e('Test Connection', 'sedana-ses'); ?></button>
            <span id="test-result"></span>
        </div>
        <?php
    }

    /**
     * Validate settings
     */
    public function validate_settings($input) {
        $output = array();
        
        // Connection type
        $output['connection_type'] = isset($input['connection_type']) && in_array($input['connection_type'], array('api', 'smtp')) 
            ? $input['connection_type'] 
            : 'api';
        
        // AWS API settings
        $output['aws_region'] = isset($input['aws_region']) ? sanitize_text_field($input['aws_region']) : '';
        $output['aws_access_key'] = isset($input['aws_access_key']) ? sanitize_text_field($input['aws_access_key']) : '';
        
        // Only update secret key if it's been changed
        if (isset($input['aws_secret_key']) && !empty($input['aws_secret_key'])) {
            $output['aws_secret_key'] = sanitize_text_field($input['aws_secret_key']);
        } else {
            $output['aws_secret_key'] = isset($this->settings['aws_secret_key']) ? $this->settings['aws_secret_key'] : '';
        }
        
        // SMTP settings
        $output['smtp_host'] = isset($input['smtp_host']) ? sanitize_text_field($input['smtp_host']) : '';
        $output['smtp_port'] = isset($input['smtp_port']) ? absint($input['smtp_port']) : 587;
        $output['smtp_secure'] = isset($input['smtp_secure']) && in_array($input['smtp_secure'], array('tls', 'ssl', '')) 
            ? $input['smtp_secure'] 
            : 'tls';
        $output['smtp_username'] = isset($input['smtp_username']) ? sanitize_text_field($input['smtp_username']) : '';
        
        // Only update SMTP password if it's been changed
        if (isset($input['smtp_password']) && !empty($input['smtp_password'])) {
            $output['smtp_password'] = sanitize_text_field($input['smtp_password']);
        } else {
            $output['smtp_password'] = isset($this->settings['smtp_password']) ? $this->settings['smtp_password'] : '';
        }
        
        // Sender settings
        $output['from_email'] = isset($input['from_email']) ? sanitize_email($input['from_email']) : '';
        $output['from_name'] = isset($input['from_name']) ? sanitize_text_field($input['from_name']) : '';
        
        // Active status
        $output['active'] = isset($input['active']) ? (bool) $input['active'] : false;
        
        return $output;
    }

    /**
     * Settings page
     */
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('sedana_ses_settings');
                do_settings_sections('sedana-ses');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * AJAX test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('sedana_ses_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'sedana-ses'));
        }
        
        $connection_type = isset($_POST['connection_type']) ? sanitize_text_field($_POST['connection_type']) : 'api';
        $test_email = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : get_option('admin_email');
        
        if ($connection_type === 'api') {
            $result = $this->test_api_connection($_POST);
        } else {
            $result = $this->test_smtp_connection($_POST);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('Connection successful!', 'sedana-ses'));
        }
    }

    /**
     * Test API connection
     */
    private function test_api_connection($data) {
        // Include AWS SDK
        if (!class_exists('Aws\Ses\SesClient')) {
            if (!file_exists(SEDANA_SES_PLUGIN_DIR . 'vendor/autoload.php')) {
                return new WP_Error('missing_aws_sdk', __('AWS SDK not found. Please contact the plugin developer.', 'sedana-ses'));
            }
            require_once SEDANA_SES_PLUGIN_DIR . 'vendor/autoload.php';
        }
        
        $region = isset($data['aws_region']) ? sanitize_text_field($data['aws_region']) : '';
        $access_key = isset($data['aws_access_key']) ? sanitize_text_field($data['aws_access_key']) : '';
        $secret_key = isset($data['aws_secret_key']) ? sanitize_text_field($data['aws_secret_key']) : '';
        
        // Check for empty fields
        if (empty($region) || empty($access_key) || empty($secret_key)) {
            return new WP_Error('missing_credentials', __('Please fill in all required fields.', 'sedana-ses'));
        }
        
        try {
            // Create SES client
            $ses = new Aws\Ses\SesClient([
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $access_key,
                    'secret' => $secret_key,
                ]
            ]);
            
            // Test with a call to get send quota
            $result = $ses->getSendQuota();
            
            return true;
        } catch (Exception $e) {
            return new WP_Error('api_error', $e->getMessage());
        }
    }

    /**
     * Test SMTP connection
     */
    private function test_smtp_connection($data) {
        // Check for PHPMailer
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            if (!class_exists('PHPMailer')) {
                // WordPress 5.5 and later
                require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
                require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
                require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            } else {
                // WordPress before 5.5
                $mail = new PHPMailer(true);
            }
        } else {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        }
        
        $host = isset($data['smtp_host']) ? sanitize_text_field($data['smtp_host']) : '';
        $port = isset($data['smtp_port']) ? absint($data['smtp_port']) : 587;
        $secure = isset($data['smtp_secure']) ? sanitize_text_field($data['smtp_secure']) : 'tls';
        $username = isset($data['smtp_username']) ? sanitize_text_field($data['smtp_username']) : '';
        $password = isset($data['smtp_password']) ? sanitize_text_field($data['smtp_password']) : '';
        
        // Check for empty fields
        if (empty($host) || empty($username) || empty($password)) {
            return new WP_Error('missing_credentials', __('Please fill in all required fields.', 'sedana-ses'));
        }
        
        try {
            // Setup SMTP
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = $port;
            
            if (!empty($secure)) {
                $mail->SMTPSecure = $secure;
            }
            
            // Connect to server
            $mail->smtpConnect();
            
            return true;
        } catch (Exception $e) {
            return new WP_Error('smtp_error', $e->getMessage());
        }
    }

    /**
     * Filter wp_mail
     */
    public function filter_wp_mail($args) {
        // Override the from email and name if they're set
        if (!empty($this->settings['from_email'])) {
            $args['from_email'] = $this->settings['from_email'];
        }
        
        if (!empty($this->settings['from_name'])) {
            $args['from_name'] = $this->settings['from_name'];
        }
        
        return $args;
    }

    /**
     * Send email via API
     */
    public function send_via_api($null, $atts) {
        // Create API handler
        $api = new Sedana_SES_API($this->settings);
        
        // Apply wp_mail filter to get any modifications from other plugins
        $atts = apply_filters('wp_mail', $atts);
        
        // Extract attributes
        $to = $atts['to'];
        $subject = $atts['subject'];
        $message = $atts['message'];
        $headers = isset($atts['headers']) ? $atts['headers'] : '';
        $attachments = isset($atts['attachments']) ? $atts['attachments'] : array();
        
        // Send email
        $result = $api->send_email($to, $subject, $message, $headers, $attachments);
        
        // Return true to short-circuit wp_mail
        if ($result) {
            return true;
        } else {
            // Let WordPress handle it if SES fails
            return $null;
        }
    }

    /**
     * Configure PHPMailer
     */
    public function configure_phpmailer($phpmailer) {
        if ($this->settings['connection_type'] === 'smtp') {
            // Configure for SMTP
            $phpmailer->isSMTP();
            $phpmailer->Host = $this->settings['smtp_host'];
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $this->settings['smtp_username'];
            $phpmailer->Password = $this->settings['smtp_password'];
            $phpmailer->Port = $this->settings['smtp_port'];
            
            if (!empty($this->settings['smtp_secure'])) {
                $phpmailer->SMTPSecure = $this->settings['smtp_secure'];
            }
        } else {
            // For API we'll need to handle this differently
            // We'll leave PHPMailer configured normally and intercept via wp_mail filter
        }
        
        return $phpmailer;
    }
}

/**
 * Main function to initialize the plugin
 */
function sedana_ses_init() {
    return Sedana_SES::get_instance();
}

// Initialize the plugin
sedana_ses_init();

/**
 * Set up AWS SDK autoloading via Composer
 */
function sedana_ses_setup_aws_sdk() {
    // Create vendor directory if it doesn't exist
    if (!file_exists(SEDANA_SES_PLUGIN_DIR . 'vendor')) {
        wp_mkdir_p(SEDANA_SES_PLUGIN_DIR . 'vendor');
    }
    
    // Create composer.json file if it doesn't exist
    if (!file_exists(SEDANA_SES_PLUGIN_DIR . 'composer.json')) {
        $composer_json = json_encode([
            'require' => [
                'aws/aws-sdk-php' => '^3.0'
            ]
        ], JSON_PRETTY_PRINT);
        
        file_put_contents(SEDANA_SES_PLUGIN_DIR . 'composer.json', $composer_json);
    }
    
    // Add notice to run composer if AWS SDK is missing
    if (!file_exists(SEDANA_SES_PLUGIN_DIR . 'vendor/autoload.php')) {
        add_action('admin_notices', 'sedana_ses_composer_notice');
    }
}

/**
 * Admin notice for Composer dependencies
 */
function sedana_ses_composer_notice() {
    ?>
    <div class="notice notice-warning">
        <p><?php _e('SEDANA SES plugin requires the AWS SDK. Please run <code>composer install</code> in the plugin directory or contact your administrator.', 'sedana-ses'); ?></p>
    </div>
    <?php
}

// Setup AWS SDK
add_action('plugins_loaded', 'sedana_ses_setup_aws_sdk');

/**
 * Register activation hook
 */
register_activation_hook(__FILE__, 'sedana_ses_activate');

function sedana_ses_activate() {
    // Initialize default settings if they don't exist
    if (!get_option('sedana_ses_settings')) {
        add_option('sedana_ses_settings', array(
            'connection_type' => 'api',
            'aws_region' => '',
            'aws_access_key' => '',
            'aws_secret_key' => '',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_secure' => 'tls',
            'from_email' => get_option('admin_email'),
            'from_name' => get_bloginfo('name'),
            'active' => false
        ));
    }
}

/**
 * Register deactivation hook
 */
register_deactivation_hook(__FILE__, 'sedana_ses_deactivate');

function sedana_ses_deactivate() {
    // Update the 'active' setting to false
    $settings = get_option('sedana_ses_settings', array());
    $settings['active'] = false;
    update_option('sedana_ses_settings', $settings);
}