# Laravel ShareLink

<div align="center">
  <img src="https://raw.githubusercontent.com/Grazulex/laravel-sharelink/main/new_logo.png" alt="Laravel ShareLink" width="200">
  
  **Generate, manage, and secure temporary share links for files, routes, and models**
  
  *A powerful Laravel package for creating secure, time-limited sharing capabilities with comprehensive audit trails*

  [![Latest Version](https://img.shields.io/packagist/v/grazulex/laravel-sharelink.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-sharelink)
  [![Total Downloads](https://img.shields.io/packagist/dt/grazulex/laravel-sharelink.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-sharelink)
  [![License](https://img.shields.io/github/license/grazulex/laravel-sharelink.svg?style=flat-square)](https://github.com/Grazulex/laravel-sharelink/blob/main/LICENSE.md)
  [![PHP Version](https://img.shields.io/packagist/php-v/grazulex/laravel-sharelink.svg?style=flat-square)](https://php.net/)
  [![Laravel Version](https://img.shields.io/badge/laravel-12.x%20%7C%2013.x-ff2d20?style=flat-square&logo=laravel)](https://laravel.com/)
  [![Tests](https://img.shields.io/github/actions/workflow/status/grazulex/laravel-sharelink/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Grazulex/laravel-sharelink/actions)
  [![Code Style](https://img.shields.io/badge/code%20style-pint-000000?style=flat-square&logo=laravel)](https://github.com/laravel/pint)
</div>

> [!TIP]
> **What Laravel ShareLink does for you** — Share a file, a route or a model through a secure, expiring link — with optional password, download limits and a complete access log. Temporary access in a few lines, not a weekend project.
>
> **This package is free and maintained on my own time.** If it saves you hours, a small contribution helps me keep it going:
> [💖 GitHub Sponsors](https://github.com/sponsors/Grazulex) · [☕ Buy Me a Coffee](https://buymeacoffee.com/grazulex) · [PayPal](https://paypal.me/strauven)

---

## 🚀 Overview

Laravel ShareLink is a comprehensive package for generating **secure**, **time-limited** sharing capabilities in your Laravel applications. Perfect for sharing files, creating temporary access to routes, or providing time-limited previews of model data with complete audit trails and advanced security features.

## ✨ Key Features

- 🔗 **Multiple Resource Types** - Share files, routes, and model previews seamlessly
- ⏰ **Time-Limited Access** - Set expiration dates and usage limits
- 🔒 **Password Protection** - Optional password gates for enhanced security
- 🚫 **Rate Limiting** - Per-token rate limiting to prevent abuse
- 🌐 **IP Filtering** - Allow/deny specific IP addresses or CIDR ranges
- 🔏 **Signed URLs** - Optional Laravel signed route integration
- 🔥 **Burn After Reading** - One-time access links that self-destruct
- 📊 **Comprehensive Auditing** - Track access patterns, IPs, and timestamps
- 🛡️ **Advanced Security** - Password throttling, brute force protection
- 🎯 **Flexible Delivery** - Support for X-Sendfile, X-Accel-Redirect, and streaming
- 📋 **Management API** - Revoke and extend links programmatically
- 🎨 **CLI Commands** - Full Artisan command support
- 📈 **Observability** - Built-in logging and metrics integration
- 🧪 **Test-Friendly** - Comprehensive test coverage with easy mocking

## 📦 Installation

Install the package via Composer:

```bash
composer require grazulex/laravel-sharelink
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="sharelink-migrations"
php artisan migrate
```

Optionally, publish the configuration file:

```bash
php artisan vendor:publish --tag="sharelink-config"
```

> **💡 Auto-Discovery**: The service provider will be automatically registered thanks to Laravel's package auto-discovery.

## ⚡ Quick Start

### 🚀 Basic Usage
```php
use Grazulex\ShareLink\Facades\ShareLink;

// Share a file with expiration
$link = ShareLink::create('/path/to/document.pdf')
    ->expiresIn(60) // 60 minutes
    ->maxClicks(5)
    ->withPassword('secret123')
    ->generate();

echo $link->url; // https://yourapp.com/share/abc123xyz
```

### 📁 File Sharing
```php
// Share a local file
$link = ShareLink::create('/storage/documents/report.pdf')
    ->expiresIn(1440) // 24 hours
    ->maxClicks(10)
    ->generate();

// Share via Laravel Storage
$link = ShareLink::create('s3://bucket/private/document.pdf')
    ->expiresIn(60)
    ->withPassword('secure')
    ->generate();
```

### 🌐 Route Sharing
```php
// Share a named route with parameters
$link = ShareLink::create([
    'type' => 'route',
    'route' => 'user.profile',
    'parameters' => ['user' => 123]
])
->expiresIn(120)
->generate();
```

### 📊 Model Preview
```php
// Share a model preview (JSON representation)
$user = User::find(1);
$link = ShareLink::create([
    'type' => 'model',
    'class' => User::class,
    'id' => $user->id
])
->expiresIn(30)
->generate();
```

### 🔥 Advanced Security Features
```php
// Burn-after-reading link with IP restrictions
$link = ShareLink::create('/secure/document.pdf')
    ->expiresIn(60)
    ->burnAfterReading() // Self-destructs after first access
    ->metadata([
        'allowed_ips' => ['192.168.1.0/24', '10.0.0.1'],
        'denied_ips' => ['192.168.1.100']
    ])
    ->generate();

// Signed URL for extra security
$signedUrl = ShareLink::signedUrl($link, now()->addHour());
```

## 🔧 Requirements

- **PHP 8.3+**
- **Laravel 12.0+ | 13.0+**

## 📚 Complete Documentation

For comprehensive documentation, examples, and advanced usage guides, visit our **Wiki**:

### 📖 **[👉 Laravel ShareLink Wiki](https://github.com/Grazulex/laravel-sharelink/wiki)**

The wiki includes:

- **🚀 [Installation & Setup](https://github.com/Grazulex/laravel-sharelink/wiki/Install)**
- **⚙️ [Configuration](https://github.com/Grazulex/laravel-sharelink/wiki/Configuration)**
- **🎯 [Quickstart Guide](https://github.com/Grazulex/laravel-sharelink/wiki/Quickstart)**
- **🌐 [API Endpoints](https://github.com/Grazulex/laravel-sharelink/wiki/Endpoints)**
- **📋 [API Reference](https://github.com/Grazulex/laravel-sharelink/wiki/API)**
- **🛡️ [Security Features](https://github.com/Grazulex/laravel-sharelink/wiki/Security)**
- **📡 [Events & Observability](https://github.com/Grazulex/laravel-sharelink/wiki/Events)**
- **🎨 [CLI Commands](https://github.com/Grazulex/laravel-sharelink/wiki/CLI)**
- **📈 [Version Matrix](https://github.com/Grazulex/laravel-sharelink/wiki/Version-Matrix)**
- **📝 [Changelog](https://github.com/Grazulex/laravel-sharelink/wiki/Changelog)**

## 🎨 Artisan Commands

Laravel ShareLink includes powerful CLI commands for managing your share links:

```bash
# Create a new share link
php artisan sharelink:create /path/to/file --expires=60 --max-clicks=5

# List all share links
php artisan sharelink:list --active --expired

# Revoke a specific link
php artisan sharelink:revoke abc123xyz

# Clean up expired links
php artisan sharelink:prune --days=7
```

## 🔧 Configuration

The package comes with sensible defaults, but you can customize everything:

```php
// config/sharelink.php
return [
    'route' => [
        'prefix' => 'share',
        'middleware' => ['web'],
    ],
    
    'security' => [
        'signed_routes' => [
            'enabled' => true,
            'required' => false,
        ],
        'rate_limiting' => [
            'enabled' => true,
            'max_attempts' => 10,
        ],
        'password_throttling' => [
            'enabled' => true,
            'max_attempts' => 5,
        ],
    ],
    
    'delivery' => [
        'x_sendfile' => false,
        'x_accel_redirect' => false,
    ],
];
```

## 🧪 Testing

```bash
composer test
```

## 🤝 Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 🔒 Security

If you discover any security-related issues, please email **jms@grazulex.be** instead of using the issue tracker.

## 📝 Changelog

Please see the [Wiki Changelog](https://github.com/Grazulex/laravel-sharelink/wiki/Changelog) for more information on what has changed recently.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 👥 Credits

- **[Jean-Marc Strauven](https://github.com/Grazulex)**
- **[All Contributors](../../contributors)**

## 💬 Support

- 🐛 **[Report Issues](https://github.com/Grazulex/laravel-sharelink/issues)**
- 💬 **[Discussions](https://github.com/Grazulex/laravel-sharelink/discussions)**
- 📖 **[Documentation](https://github.com/Grazulex/laravel-sharelink/wiki)**

---

<div align="center">
  <strong>Laravel ShareLink</strong> - Secure, time-limited sharing for Laravel applications<br>
  with comprehensive audit trails and advanced security features.
</div>

---

## 💼 Need Custom Laravel Solutions?

Laravel ShareLink is maintained by **Jean-Marc Strauven**, creator of 17+ Laravel packages.

### 🚀 I Can Help With:

**🔐 Secure File Sharing & Access Control**
- Custom file management systems
- Advanced permission systems
- Temporary access solutions
- Integration with cloud storage (S3, Azure, etc.)

**📦 Custom Laravel Package Development**
- Build tailored packages for your specific needs
- Internal tools for your team
- Integration with third-party services
- **€5,000-€10,000** depending on complexity

**🏗️ Complete Laravel Applications**
- SaaS platforms
- Document management systems
- Collaboration tools
- **€8,000-€15,000** for MVP

### 👨‍💻 About Me:
- 15+ years Laravel/PHP expertise
- Ex-CTO at Delcampe (millions of users)
- Chapter Lead at BNP Paribas Fortis
- 6,000+ package downloads across 17+ packages

### 📬 Let's Talk:
- 📧 [jms@grazulex.be](mailto:jms@grazulex.be)
- 💼 [LinkedIn Profile](https://www.linkedin.com/in/jean-marc-strauven)
- 💻 [Available on Malt](https://www.malt.be)

💡 **Building a Laravel SaaS or need custom features?** I'd love to help bring your project to life.

---

## ⭐ Show Your Support

If Laravel ShareLink is useful for your project:
- Give it a ⭐ on GitHub
- Share it with other Laravel developers
- [Sponsor my work](https://github.com/sponsors/Grazulex) ❤️

