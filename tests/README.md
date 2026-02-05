# Testing Documentation

Comprehensive test suite for the Marketing Analytics MCP WordPress plugin.

## Table of Contents

1. [Overview](#overview)
2. [Test Structure](#test-structure)
3. [Running Tests](#running-tests)
4. [Test Suites](#test-suites)
5. [Code Coverage](#code-coverage)
6. [Integration Tests Setup](#integration-tests-setup)
7. [WordPress.org Submission Checklist](#wordpressorg-submission-checklist)
8. [CI/CD Integration](#cicd-integration)

## Overview

This test suite provides comprehensive coverage for WordPress.org plugin submission, including:

- **Unit Tests**: Isolated tests for individual components
- **Integration Tests**: Tests with external API dependencies
- **Security Tests**: Security and sanitization validation
- **WordPress.org Validation**: Pre-submission compliance checks

### Test Statistics

- **Total Test Files**: 13+
- **Test Coverage Target**: 80%+
- **Required for WordPress.org**: ✅ All security and validation tests must pass

## Test Structure

```
tests/
├── bootstrap.php                     # Test bootstrap and WordPress mocks
├── README.md                         # This file
├── unit/                            # Unit tests (fast, no external dependencies)
│   ├── ActivationTest.php          # Plugin activation/deactivation
│   ├── CacheManagerTest.php        # Caching functionality
│   ├── ClarityClientTest.php       # Clarity API client
│   ├── CredentialManagerTest.php   # Credential management
│   ├── EncryptionTest.php          # Encryption/decryption
│   ├── GA4ClientTest.php           # GA4 API client
│   ├── GSCClientTest.php           # Search Console client
│   ├── LoggerTest.php              # Logging functionality
│   ├── PermissionManagerTest.php   # Permission and RBAC
│   └── SecurityTest.php            # Security validation
└── integration/                     # Integration tests (slower, external APIs)
    ├── AbilitiesIntegrationTest.php         # MCP abilities registration
    ├── ClarityIntegrationTest.php           # Real Clarity API calls
    ├── GA4IntegrationTest.php               # Real GA4 API calls
    └── WordPressOrgValidationTest.php       # WordPress.org compliance
```

## Running Tests

### Prerequisites

```bash
# Install dependencies
composer install --dev

# Ensure PHPUnit is available
vendor/bin/phpunit --version
```

### Quick Development Tests

Run only fast unit tests (recommended during development):

```bash
vendor/bin/phpunit --testsuite=Quick
```

### All Unit Tests

Run all unit tests without external dependencies:

```bash
vendor/bin/phpunit --testsuite=Unit
```

### Security Tests

Run security and sanitization validation:

```bash
vendor/bin/phpunit --testsuite=Security
```

### Integration Tests

**WARNING**: These tests make real API calls. Ensure credentials are configured.

```bash
# Set environment variables first
export CLARITY_PROJECT_ID="your_project_id"
export CLARITY_API_KEY="your_api_key"

# Run integration tests
vendor/bin/phpunit --testsuite=Integration
```

### WordPress.org Validation

Run all WordPress.org compliance checks:

```bash
vendor/bin/phpunit --testsuite=WordPress.org
```

### Complete Test Suite

Run ALL tests (unit + integration + validation):

```bash
vendor/bin/phpunit --testsuite=All
```

### Specific Test File

Run a single test file:

```bash
vendor/bin/phpunit tests/unit/EncryptionTest.php
```

### Specific Test Method

Run a single test method:

```bash
vendor/bin/phpunit --filter test_encrypt_decrypt_reversible
```

## Test Suites

### 1. Unit Tests (`--testsuite=Unit`)

**Purpose**: Fast, isolated tests with no external dependencies

**Coverage**:
- Encryption/decryption
- Cache management
- Credential validation
- API client parameter validation
- Permission and RBAC
- Logging functionality
- Activation/deactivation

**Runtime**: ~2-5 seconds

**When to Run**: After every code change, before commits

### 2. Integration Tests (`--testsuite=Integration`)

**Purpose**: Test interactions with external APIs and services

**Coverage**:
- Real Clarity API calls
- Real GA4 API calls
- Real GSC API calls
- OAuth token refresh
- API error handling
- Caching with real data
- MCP abilities registration

**Runtime**: ~30-60 seconds (depends on API response times)

**When to Run**: Before merging to main, in CI/CD pipeline

**Requirements**:
- Valid API credentials in environment variables
- Internet connection
- API rate limits awareness

### 3. Security Tests (`--testsuite=Security`)

**Purpose**: Validate security measures and prevent vulnerabilities

**Coverage**:
- SQL injection prevention
- XSS prevention
- CSRF protection
- Nonce verification
- Capability checks
- Input sanitization
- Output escaping
- Secure random generation
- Direct file access protection

**Runtime**: ~3-8 seconds

**When to Run**: Before every release, required for WordPress.org

### 4. WordPress.org Validation (`--testsuite=WordPress.org`)

**Purpose**: Ensure compliance with WordPress.org plugin requirements

**Coverage**:
- Valid plugin header
- GPL-compatible license
- readme.txt format
- No blocked file types
- Plugin size limits
- No external dependencies (CDN)
- Proper text domain
- Translatable strings
- No PHP short tags
- Proper namespacing
- No eval() usage
- Output escaping

**Runtime**: ~5-10 seconds

**When to Run**: Before submitting to WordPress.org

## Code Coverage

### Generate Coverage Report

```bash
# HTML coverage report
vendor/bin/phpunit --coverage-html coverage/

# Open in browser
open coverage/index.html
```

### Coverage Targets

- **Overall**: 80%+ coverage
- **Critical Classes**: 90%+ coverage
  - Encryption
  - Credential_Manager
  - API Clients
  - Abilities_Registrar

### Excluded from Coverage

- Admin view templates (`includes/admin/views/`)
- Vendor directory
- Tests directory

## Integration Tests Setup

### Environment Variables

Create a `.env` file in the plugin root (DO NOT commit this file):

```bash
# Microsoft Clarity
CLARITY_PROJECT_ID=your_project_id_here
CLARITY_API_KEY=your_api_key_here

# Google Analytics 4
GA4_CLIENT_ID=your_client_id.apps.googleusercontent.com
GA4_CLIENT_SECRET=your_client_secret_here
GA4_REFRESH_TOKEN=your_refresh_token_here
GA4_PROPERTY_ID=123456789

# Google Search Console
GSC_CLIENT_ID=your_client_id.apps.googleusercontent.com
GSC_CLIENT_SECRET=your_client_secret_here
```

### Load Environment Variables

```bash
# Option 1: Export manually
export $(cat .env | xargs)

# Option 2: Use direnv (recommended)
echo 'dotenv' > .envrc
direnv allow

# Option 3: Set in phpunit.xml.dist (for CI/CD)
```

### CI/CD Integration

Add these as secrets in your CI/CD platform:
- GitHub Actions: Repository Secrets
- GitLab CI: CI/CD Variables
- CircleCI: Environment Variables

## WordPress.org Submission Checklist

Before submitting to WordPress.org, ensure all these tests pass:

### Required Tests

- [ ] All unit tests pass (`--testsuite=Unit`)
- [ ] All security tests pass (`--testsuite=Security`)
- [ ] WordPress.org validation passes (`--testsuite=WordPress.org`)
- [ ] Code coverage > 80% (`--coverage-html`)

### Manual Checks

- [ ] Update readme.txt with latest version
- [ ] Update changelog in readme.txt
- [ ] Update plugin version in main file
- [ ] Update `Tested up to` WordPress version
- [ ] Remove any development/debug code
- [ ] Check for console.log in JavaScript
- [ ] Verify all assets are properly licensed

### Run Complete Pre-submission Tests

```bash
# 1. Run all tests
vendor/bin/phpunit --testsuite=All

# 2. Run WordPress Coding Standards
vendor/bin/phpcs

# 3. Run PHPStan static analysis
vendor/bin/phpstan analyse

# 4. Generate coverage report
vendor/bin/phpunit --coverage-html coverage/

# 5. Check coverage meets minimum 80%
open coverage/index.html
```

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: sodium, mbstring

      - name: Install dependencies
        run: composer install --dev

      - name: Run unit tests
        run: vendor/bin/phpunit --testsuite=Unit

      - name: Run security tests
        run: vendor/bin/phpunit --testsuite=Security

      - name: Run WordPress.org validation
        run: vendor/bin/phpunit --testsuite=WordPress.org

      - name: Generate coverage
        run: vendor/bin/phpunit --coverage-clover coverage.xml

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v2
```

### GitLab CI Example

```yaml
test:
  image: php:8.1
  before_script:
    - apt-get update && apt-get install -y git unzip
    - curl -sS https://getcomposer.org/installer | php
    - php composer.phar install --dev
  script:
    - vendor/bin/phpunit --testsuite=Unit
    - vendor/bin/phpunit --testsuite=Security
    - vendor/bin/phpunit --testsuite=WordPress.org
  coverage: '/^\s*Lines:\s*\d+.\d+\%/'
```

## Troubleshooting

### Sodium Extension Not Available

```bash
# Install sodium extension
sudo apt-get install php8.1-sodium  # Ubuntu/Debian
brew install php@8.1                # macOS
```

### Integration Tests Failing

1. Check environment variables are set correctly
2. Verify API credentials are valid
3. Check API rate limits (especially Clarity: 10/day)
4. Ensure internet connection is available

### Coverage Report Not Generating

```bash
# Install Xdebug
sudo apt-get install php8.1-xdebug

# Or use PCOV (faster)
sudo apt-get install php8.1-pcov
```

## Best Practices

### Writing New Tests

1. **Unit Tests**: Mock external dependencies
2. **Integration Tests**: Use `@group integration` annotation
3. **Test Naming**: Use descriptive method names (`test_feature_does_expected_behavior`)
4. **Assertions**: Use specific assertions (`assertEquals` not `assertTrue`)
5. **Test Data**: Use realistic test data
6. **Clean Up**: Always implement `tearDown()` for cleanup

### Running Tests During Development

```bash
# Quick feedback loop
vendor/bin/phpunit --testsuite=Quick --filter ClassName

# Watch mode (with entr or similar)
ls tests/unit/*.php | entr vendor/bin/phpunit --testsuite=Quick
```

### Before Commits

```bash
# Pre-commit hook (add to .git/hooks/pre-commit)
#!/bin/bash
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpcs
```

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Plugin Handbook: Testing](https://developer.wordpress.org/plugins/testing/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [WordPress.org Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)

## Support

For test-related issues:
1. Check this README
2. Review test output for detailed error messages
3. Check existing test files for examples
4. Review CLAUDE.md for project-specific guidance
