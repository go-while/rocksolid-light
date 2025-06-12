# RockSolid Light Test Suite

This directory contains comprehensive test files for the RockSolid Light newsreader application. All tests have been updated to run from their current location in the `tests/` directory with proper relative path handling.

## 🧪 Test Files Overview

### Security Tests

#### `security_test.php`
**Purpose:** Core security function testing
**Description:** Tests all security functions including input validation, CSRF protection, file security, and path sanitization. Validates that the security hardening implementation works correctly.
**Key Features:**
- Input validation testing (alphanum, email, HTML escaping)
- CSRF token generation and validation
- File upload security checks
- Path traversal protection
- SQL injection prevention

#### `csrf_debug.php`
**Purpose:** CSRF token functionality debugging
**Description:** Debug utility for CSRF token generation and validation in CLI mode. Helps troubleshoot session-related issues and token validation problems.
**Key Features:**
- Session status debugging
- Token generation testing
- Token validation verification
- CLI compatibility checks

#### `comprehensive_security_audit.php`
**Purpose:** Complete security audit of the application
**Description:** Performs a thorough security scan of all RockSolid Light files, checking for vulnerabilities, insecure patterns, and compliance with security best practices.
**Key Features:**
- File permission auditing
- Code vulnerability scanning
- Configuration security checks
- Directory traversal testing
- Insecure function detection

### Database Performance Tests

#### `database_performance_test.php`
**Purpose:** Database performance analysis
**Description:** Comprehensive testing of database operations, optimization effectiveness, and performance under various conditions.
**Key Features:**
- Query performance benchmarking
- Index effectiveness testing
- Concurrent operation testing
- Memory usage analysis
- Optimization verification

#### `standalone_database_performance_test.php`
**Purpose:** Independent database performance testing
**Description:** Self-contained database performance suite that doesn't require main application files. Creates its own test databases and performs comprehensive analysis.
**Key Features:**
- Independent test database creation
- Comprehensive performance metrics
- Large dataset simulation
- Query optimization testing
- Memory and disk usage analysis

#### `database_monitor.php`
**Purpose:** Real-time database monitoring class
**Description:** Database monitoring utility class that provides health checks, performance monitoring, and alerting capabilities.
**Key Features:**
- Health check functionality
- Performance metric collection
- Database size monitoring
- Query analysis
- Alert generation

#### `test_monitor.php`
**Purpose:** Database monitor testing
**Description:** Tests the DatabaseMonitor class functionality with simulated data and environments.
**Key Features:**
- Monitor class testing
- Test database simulation
- Health check validation
- Performance metric verification

### Integration & Production Tests

#### `test_integration.php`
**Purpose:** Integration testing suite
**Description:** Tests the integration between different components of RockSolid Light, including database operations, security functions, and file handling.
**Key Features:**
- Component integration testing
- Database operation validation
- Security integration checks
- File system operations
- Cross-component communication

#### `production_verification.php`
**Purpose:** Production environment validation
**Description:** Verifies that the production environment is properly configured and all components are working correctly before deployment.
**Key Features:**
- Environment configuration checks
- Permission validation
- Security configuration verification
- Database connectivity testing
- Performance baseline establishment

#### `final_production_test.php`
**Purpose:** Final production readiness check
**Description:** Comprehensive final test before production deployment, checking all critical systems and security measures.
**Key Features:**
- Complete system validation
- Security audit integration
- Performance verification
- Configuration validation
- Deployment readiness check

#### `test_production_optimization.php`
**Purpose:** Production optimization testing
**Description:** Tests the integrated database optimizations in the main RockSolid Light application under production-like conditions.
**Key Features:**
- Production optimization validation
- Performance improvement verification
- Resource usage optimization
- Database tuning effectiveness

### Performance & Benchmarking Tests

#### `performance_test.php`
**Purpose:** Security hardening performance impact assessment
**Description:** Measures the performance impact of security hardening features to ensure they don't significantly affect application speed.
**Key Features:**
- Security function benchmarking
- Performance impact measurement
- Memory usage analysis
- Response time testing
- Throughput analysis

#### `simple_test.php`
**Purpose:** Basic security function testing
**Description:** Simple, quick test of core security functions for development and debugging purposes.
**Key Features:**
- Basic input validation testing
- File operation security checks
- MIME type detection testing
- Path security validation
- Quick development feedback

## 🚀 Running the Tests

### Prerequisites
- PHP 7.4 or higher
- SQLite3 extension
- Write permissions to the `spool/` directory
- Access to the main RockSolid Light files

### Running Individual Tests

```bash
# From the tests directory
cd /path/to/rocksolid-light/tests

# Security tests
php security_test.php
php comprehensive_security_audit.php

# Database performance tests
php database_performance_test.php
php standalone_database_performance_test.php

# Integration tests
php test_integration.php
php production_verification.php

# Performance benchmarks
php performance_test.php
```

### Running All Tests

```bash
# Quick test run
find . -name "*.php" -not -name "database_monitor.php" -exec php {} \;

# With detailed output
for test in *.php; do
    if [[ "$test" != "database_monitor.php" ]]; then
        echo "=== Running $test ==="
        php "$test"
        echo -e "\n"
    fi
done
```

## 📋 Test Results Interpretation

### Security Tests
- **PASS**: Security measure is working correctly
- **FAIL**: Security vulnerability detected - requires immediate attention
- **SKIP**: Test skipped due to missing dependencies or configuration

### Performance Tests
- **Benchmark Results**: Displayed in milliseconds (ms) or operations per second
- **Memory Usage**: Shown in MB or KB
- **Database Size**: File sizes and growth patterns
- **Query Performance**: Execution times and optimization effectiveness

### Integration Tests
- **✓**: Component integration successful
- **✗**: Integration failure - check dependencies and configuration
- **⚠**: Warning - component working but with issues

## 🔧 Configuration

### Test Environment Setup
The tests automatically detect and use the correct paths for:
- Database files in `../spool/`
- Configuration files in `../rocksolid/` and `../common/`
- Main application files in the parent directory

### Custom Configuration
You can override default paths by setting environment variables or modifying the test files directly:

```php
// Example custom configuration
$custom_spool_dir = '/custom/path/to/spool';
$custom_config = '/custom/path/to/config';
```

## 🐛 Troubleshooting

### Common Issues

1. **Permission Errors**
   - Ensure write permissions to `spool/` directory
   - Check file ownership and permissions

2. **Missing Dependencies**
   - Verify SQLite3 extension is installed
   - Check for required PHP modules

3. **Path Issues**
   - All tests use relative paths from the `tests/` directory
   - Ensure the main application files are in the parent directory

4. **Database Connection Errors**
   - Check SQLite file permissions
   - Verify database file integrity
   - Ensure sufficient disk space

### Getting Help
- Check the main application logs in `spool/log/`
- Review error messages carefully
- Ensure all dependencies are installed
- Verify file and directory permissions

## 📊 Test Coverage

| Component | Security | Performance | Integration | Production |
|-----------|----------|-------------|-------------|------------|
| Authentication | ✓ | ✓ | ✓ | ✓ |
| Database Operations | ✓ | ✓ | ✓ | ✓ |
| File Handling | ✓ | ✓ | ✓ | ✓ |
| Input Validation | ✓ | ✓ | ✓ | ✓ |
| CSRF Protection | ✓ | ✓ | ✓ | ✓ |
| Configuration | ✓ | - | ✓ | ✓ |
| Performance Monitoring | - | ✓ | ✓ | ✓ |

## 🔄 Maintenance

### Adding New Tests
1. Create new PHP file in the `tests/` directory
2. Use proper relative paths with `__DIR__ . '/../'` prefix
3. Follow the existing naming convention
4. Add documentation to this README
5. Include error handling and proper output formatting

### Updating Existing Tests
1. Maintain backward compatibility where possible
2. Update path references if application structure changes
3. Keep test documentation current
4. Verify all tests still pass after modifications

---

**Last Updated:** June 12, 2025
**Version:** 1.0.0
**Maintainer:** RockSolid Light Development Team
