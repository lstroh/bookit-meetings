# Booking System - WordPress Plugin

Professional appointment booking system for UK service businesses.

## Features

- Appointment scheduling for service businesses
- Staff management with service assignments
- Customer database with GDPR compliance
- Payment processing (Stripe, PayPal)
- Email notifications via transactional email service
- Google Calendar integration
- Separate business dashboard (non-WordPress admin)

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- MySQL 5.7 or higher
- HTTPS enabled (required for payment processing)

## Installation

1. Upload the `booking-system` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Booking System > Settings to configure

## Development

### Setup

```bash
# Install Composer dependencies
composer install

# Run tests
composer test

# Check coding standards
composer phpcs

# Fix coding standards automatically
composer phpcbf
```

### Directory Structure

```
booking-system/
├── admin/              Admin-specific functionality
├── includes/           Core plugin classes
├── public/             Public-facing functionality
├── tests/              PHPUnit tests
└── booking-system.php  Main plugin file
```

## Testing

Run PHPUnit tests:

```bash
vendor/bin/phpunit
```

## License

GPL-2.0-or-later

## Support

For support, please contact: your-email@example.com
