<?php
/**
 * SES API Handler
 *
 * @package SEDANA_SES
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * SES API Handler Class
 */
class Sedana_SES_API {
    /**
     * AWS SES Client
     */
    private $ses_client;
    
    /**
     * Settings
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct($settings) {
        $this->settings = $settings;
        
        // Include AWS SDK if it's not already included
        if (!class_exists('Aws\Ses\SesClient')) {
            if (file_exists(SEDANA_SES_PLUGIN_DIR . 'vendor/autoload.php')) {
                require_once SEDANA_SES_PLUGIN_DIR . 'vendor/autoload.php';
            } else {
                // Log an error or display a notice
                add_action('admin_notices', array($this, 'display_sdk_missing_notice'));
                return;
            }
        }
        
        try {
            // Initialize SES client
            $this->ses_client = new Aws\Ses\SesClient([
                'version' => 'latest',
                'region' => $this->settings['aws_region'],
                'credentials' => [
                    'key' => $this->settings['aws_access_key'],
                    'secret' => $this->settings['aws_secret_key'],
                ]
            ]);
        } catch (Exception $e) {
            // Log error
            error_log('SEDANA SES: Error initializing SES client: ' . $e->getMessage());
        }
    }
    
    /**
     * Display SDK missing notice
     */
    public function display_sdk_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('SEDANA SES plugin requires the AWS SDK. Please run <code>composer install</code> in the plugin directory or contact your administrator.', 'sedana-ses'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Send email via SES API
     */
    public function send_email($to, $subject, $message, $headers = '', $attachments = array()) {
        if (!$this->ses_client) {
            return false;
        }
        
        // Parse headers
        $cc = array();
        $bcc = array();
        $reply_to = array();
        
        $content_type = 'text/plain';
        
        if (!is_array($headers)) {
            // Split headers into an array
            $tempheaders = explode("\n", str_replace("\r\n", "\n", $headers));
        } else {
            $tempheaders = $headers;
        }
        
        // If it's an array, a header might be multiple lines
        foreach ((array) $tempheaders as $header) {
            if (strpos($header, ':') === false) {
                continue;
            }
            
            // Explode them out
            list($name, $content) = explode(':', trim($header), 2);
            
            // Cleanup
            $name = trim($name);
            $content = trim($content);
            
            switch (strtolower($name)) {
                case 'content-type':
                    if (strpos($content, ';') !== false) {
                        list($type, $charset) = explode(';', $content);
                        $content_type = trim($type);
                    } else {
                        $content_type = trim($content);
                    }
                    break;
                case 'cc':
                    $cc = array_merge(
                        $cc,
                        explode(',', $content)
                    );
                    break;
                case 'bcc':
                    $bcc = array_merge(
                        $bcc,
                        explode(',', $content)
                    );
                    break;
                case 'reply-to':
                    $reply_to = array_merge(
                        $reply_to,
                        explode(',', $content)
                    );
                    break;
            }
        }
        
        // Clean up recipients
        $to = $this->prepare_recipients($to);
        $cc = $this->prepare_recipients($cc);
        $bcc = $this->prepare_recipients($bcc);
        $reply_to = $this->prepare_recipients($reply_to);
        
        // Set from name and email
        $from_email = $this->settings['from_email'];
        $from_name = $this->settings['from_name'];
        
        // Build the request parameters
        $params = [
            'Source' => $from_name ? $from_name . ' <' . $from_email . '>' : $from_email,
            'Destination' => [
                'ToAddresses' => $to,
            ],
            'Message' => [
                'Subject' => [
                    'Data' => $subject,
                    'Charset' => 'UTF-8',
                ],
                'Body' => [],
            ],
        ];
        
        // Add CC and BCC if they exist
        if (!empty($cc)) {
            $params['Destination']['CcAddresses'] = $cc;
        }
        
        if (!empty($bcc)) {
            $params['Destination']['BccAddresses'] = $bcc;
        }
        
        // Add Reply-To if it exists
        if (!empty($reply_to)) {
            $params['ReplyToAddresses'] = $reply_to;
        }
        
        // Set message body based on content type
        if ($content_type === 'text/html') {
            $params['Message']['Body']['Html'] = [
                'Data' => $message,
                'Charset' => 'UTF-8',
            ];
            
            // Also include a text version
            $text_message = wp_strip_all_tags($message);
            $params['Message']['Body']['Text'] = [
                'Data' => $text_message,
                'Charset' => 'UTF-8',
            ];
        } else {
            $params['Message']['Body']['Text'] = [
                'Data' => $message,
                'Charset' => 'UTF-8',
            ];
        }
        
        // Handle attachments
        if (!empty($attachments)) {
            // AWS SES doesn't directly support attachments, so we need to use raw email
            // This requires a more complex implementation using MIME
            // For simplicity, we'll return false for now and log an error
            error_log('SEDANA SES: Attachments are not supported with the AWS SES API method. Consider using SMTP instead.');
            return false;
        }
        
        try {
            // Send the email
            $result = $this->ses_client->sendEmail($params);
            return true;
        } catch (Exception $e) {
            error_log('SEDANA SES: Error sending email: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Prepare recipients
     */
    private function prepare_recipients($recipients) {
        if (!is_array($recipients)) {
            $recipients = explode(',', $recipients);
        }
        
        $prepared = array();
        
        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);
            
            if (empty($recipient)) {
                continue;
            }
            
            // Extract email from "Name <email@example.com>" format
            if (preg_match('/(.*)<(.+)>/', $recipient, $matches)) {
                $recipient = trim($matches[2]);
            }
            
            if (is_email($recipient)) {
                $prepared[] = $recipient;
            }
        }
        
        return $prepared;
    }
}