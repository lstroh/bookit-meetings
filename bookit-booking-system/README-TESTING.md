# Testing Guide

## Prerequisites

1. **Docker Desktop** - Must be running
2. **wp-env** - Install globally if not already:
   ```bash
   npm install -g @wordpress/env
   ```
3. **Composer** - For installing PHPUnit

## Setup

### 1. Install Dependencies

```bash
# Install PHP dependencies
composer install

# If upgrading from yoast/phpunit-polyfills ^1.0 to ^2.0, update the lock file first:
# composer update yoast/phpunit-polyfills --with-all-dependencies

# Install npm dependencies (for wp-env scripts)
npm install
```

### 2. Start wp-env

```bash
npm run wp-env:start
```

This will:
- Download WordPress
- Create Docker containers
- Install the plugin
- Set up test database

**Access:**
- WordPress site: http://localhost:8888
- Admin: http://localhost:8888/wp-admin (admin/password)
- Test instance: http://localhost:8889

### 3. Run Tests

```bash
# Run all tests
npm test

# Run tests with verbose output
npm run test:verbose

# Run tests with code coverage
npm run test:coverage
```

## Common Commands

```bash
# Start wp-env
npm run wp-env:start

# Stop wp-env
npm run wp-env:stop

# Restart wp-env (useful after code changes)
npm run wp-env:restart

# Destroy and rebuild (fresh start)
npm run wp-env:destroy
npm run wp-env:start

# Run tests inside wp-env container
npm test

# Access WordPress container shell
wp-env run tests-wordpress bash

# Access database
wp-env run tests-cli wp db cli
```

## Troubleshooting

### Tests fail with "WordPress not found"

Make sure wp-env is running:
```bash
npm run wp-env:start
```

### Docker issues

Restart Docker Desktop and try again:
```bash
npm run wp-env:restart
```

### Database issues

Reset the test database:
```bash
npm run wp-env:destroy
npm run wp-env:start
```

### Port conflicts

If port 8888 or 8889 is already in use, edit `.wp-env.json` and change the ports.

## Test Organization

```
tests/
├── bootstrap.php              # Test setup
├── test-plugin-activation.php # Activation tests
├── test-database.php          # Database tests
├── test-logger.php            # Logger tests
├── test-auth.php              # Authentication tests
├── test-admin-menu.php        # Admin menu tests
└── test-session.php           # Session tests
```

## Writing New Tests

1. Create file: `tests/test-feature.php`
2. Extend `Yoast\PHPUnitPolyfills\TestCases\TestCase` (or `PHPUnit\Framework\TestCase`)
3. Name methods: `test_feature_description()`
4. Run tests: `npm test`

Example:

```php
<?php
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class Test_My_Feature extends TestCase {
    public function test_something() {
        $this->assertTrue(true);
    }
}
```

## Continuous Integration

These tests can run in GitHub Actions or other CI environments using wp-env.

See `.github/workflows/test.yml` (to be created in later sprint).
