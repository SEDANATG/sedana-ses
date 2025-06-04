# SEDANA SES

A WordPress plugin that configures your site to send all emails through Amazon SES using either API or SMTP.

## Features

* Configure WordPress to use Amazon SES for all outgoing emails
* Choose between AWS API or SMTP connection methods
* User-friendly admin interface for configuring credentials
* Test connection functionality to verify your settings
* Easily activate/deactivate email sending through SES
* Compatible with WordPress core and most plugins that send emails

## Installation

1. Upload the `sedana-ses` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → SEDANA SES to configure the plugin

### API Method Requirements

If you choose to use the AWS API method, you'll need to install the AWS SDK for PHP. You can do this in one of two ways:

#### Option 1: Using Composer (Recommended)

1. Navigate to the plugin directory: `cd wp-content/plugins/sedana-ses`
2. Run: `composer install`

#### Option 2: Manual Installation

1. Download the AWS SDK for PHP from [https://github.com/aws/aws-sdk-php/releases](https://github.com/aws/aws-sdk-php/releases)
2. Extract the files to the `vendor` directory within the plugin folder

## Configuration

### 1. Amazon SES Setup

Before configuring the plugin, make sure you have:

1. An AWS account with Amazon SES enabled
2. Verified your domain or email address in the SES console
3. Created IAM user credentials with SES permissions
4. (If using production) Moved your SES account out of sandbox mode

### 2. Plugin Settings

Navigate to Settings → SEDANA SES in your WordPress admin and configure the following:

#### Connection Type

Choose between:
- **AWS API**: Uses the AWS SDK for PHP to connect directly to the SES API
- **SMTP**: Uses WordPress's built-in SMTP functionality to connect to SES

#### API Settings (if using API method)

- **AWS Region**: Select the AWS region where SES is configured
- **AWS Access Key**: Enter your IAM user access key
- **AWS Secret Key**: Enter your IAM user secret key

#### SMTP Settings (if using SMTP method)

- **SMTP Host**: Enter your Amazon SES SMTP server address (e.g., email-smtp.us-east-1.amazonaws.com)
- **SMTP Port**: Enter the SMTP port (usually 587 for TLS or 465 for SSL)
- **SMTP Security**: Select the encryption method (TLS, SSL, or None)
- **SMTP Username**: Enter your SES SMTP username
- **SMTP Password**: Enter your SES SMTP password

#### Sender Settings

- **From Email**: Enter the email address that emails should be sent from (must be verified in SES)
- **From Name**: Enter the name that should appear as the sender

### 3. Test and Activate

1. Click the "Test Connection" button to verify your settings
2. If the test is successful, check the "Enable sending emails through Amazon SES" box
3. Click "Save Changes"

## Frequently Asked Questions

### Why use Amazon SES for WordPress emails?

Amazon SES offers several advantages for sending WordPress emails:
- High deliverability rates
- Low cost (first 62,000 emails per month are free when sent from EC2)
- Detailed analytics and tracking
- Reliable infrastructure

### Which connection method should I choose?

- **API Method**: Provides more features and better error handling, but requires the AWS SDK
- **SMTP Method**: Simpler to set up and works with any WordPress installation, but offers fewer features

### I'm getting an error about the AWS SDK missing

If you're using the API connection method, you need to install the AWS SDK for PHP. See the Installation section above for instructions.

### My emails aren't being delivered

Check the following:
1. Verify your domain or email address is verified in the SES console
2. Ensure your SES account is out of sandbox mode if sending to non-verified recipients
3. Check your sending quota in the SES console
4. Verify that the plugin is activated in the settings

## Support

For support or feature requests, please contact SEDANA at support@sedana.tg

## License

This plugin is licensed under the GPL v2 or later.

---

© 2025 SEDANA | [sedana.tg](https://sedana.tg)