# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 0.1.x   | :white_check_mark: |

## Reporting a Vulnerability

We take security vulnerabilities seriously. If you discover a security issue, please follow these steps:

### 1. Do Not Disclose Publicly

Please do not create a public GitHub issue for security vulnerabilities.

### 2. Report Privately

Send a detailed report to the project maintainers via:
- GitHub Security Advisory (preferred)
- Email to project maintainers

### 3. Include Details

Your report should include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

### 4. Response Time

- We aim to acknowledge reports within **48 hours**
- We will provide updates on the status within **7 days**
- We will work to fix critical vulnerabilities within **30 days**

## Security Best Practices

### For Users

- **Change default credentials** immediately after deployment
- **Use strong passwords** for admin accounts
- **Enable HTTPS** in production environments
- **Keep dependencies updated** regularly
- **Restrict MongoDB access** to trusted networks only
- **Set proper file permissions** on server files
- **Enable firewall rules** for production servers

### For Developers

- **Never commit** `.env` files or credentials
- **Use prepared statements** for database queries (already implemented)
- **Validate all user input** on both client and server
- **Sanitize output** to prevent XSS attacks
- **Use CSRF tokens** for form submissions (already implemented)
- **Keep dependencies updated** via `composer update`
- **Review security headers** in `.htaccess` files

## Security Features

This application includes:

- **Password hashing** using bcrypt
- **Session management** with timeout protection
- **Input validation** and sanitization
- **XSS protection** via output escaping
- **CSRF protection** for forms
- **Security headers** (X-Frame-Options, X-XSS-Protection, etc.)
- **MongoDB parameterized queries** to prevent injection
- **Role-based access control** for different user levels

## Known Security Considerations

### Alpha Status
This project is in **alpha** status. While security best practices are implemented, thorough security audits have not been completed. Use in production at your own risk.

### Default Credentials
Default admin credentials (`admin` / `admin123`) must be changed immediately after deployment.

### Docker Security
When running in Docker:
- Containers run as non-root users
- MongoDB not exposed to host network by default
- Use `.env` files for sensitive configuration
- Consider using Docker secrets for production

### Network Security
- Use Cloudflare Tunnel or reverse proxy for public exposure
- Never expose MongoDB port (27017) directly to the internet
- Configure firewall rules appropriately

## Security Updates

Security updates will be released as patches to supported versions. Check the [releases page](https://github.com/RashiMrBRD/Inventory-System/releases) regularly.

## Acknowledgments

We appreciate security researchers who responsibly disclose vulnerabilities. Contributors will be acknowledged (unless they prefer to remain anonymous).
