# WP Inactivity Logout Pro

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/PeopleInside/wp-inactivity-logout-pro/blob/main/LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-Tested%20up%20to%207.1.4-21759b.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-8892bf.svg)](http://php.net/)
[![GitHub Stars](https://img.shields.io/github/stars/PeopleInside/wp-inactivity-logout-pro.svg?style=social&label=Star)](https://github.com/PeopleInside/wp-inactivity-logout-pro/stargazers)

Automatic inactivity logout with server-side closed-tab protection, customizable countdown warning popup, and full bilingual support (IT/EN).

## 🛡️ Description

WP Inactivity Logout Pro protects WordPress user sessions across all roles (administrators, editors, customers, and subscribers).

Unlike traditional plugins that only work via JavaScript while the browser tab is open, WP Inactivity Logout Pro introduces a **dual security barrier**:

1. **Client-Side Engine**: Continuously monitors user activity (mouse, keyboard, touch, scroll) without displaying a popup while the user is active. If the user becomes truly inactive, it displays a countdown popup synchronized across all open tabs via `BroadcastChannel` and `localStorage`.
2. **Server-Side Closed-Tab Guard**: Records the last active timestamp. If a user closes the browser or tab and returns after the allowed time (e.g., 30 minutes), the session is immediately destroyed server-side on `init` using the native `wp_logout()` API, avoiding 500 errors or redirect loops.

## ✨ Key Features

- **Continuous Activity Detection**: No premature popups while the user is interacting with the page.
- **Customizable Warning & Countdown**: Set the logout time (e.g., 30 min) and the countdown warning (e.g., after 15 min).
- **Disable with 0**: Set warning minutes to 0 to completely disable the popup and perform a direct logout.
- **Validity Check**: The warning value cannot be greater than or equal to the total logout time.
- **Closed-Tab Protection**: Total security if the user closes the window and leaves the PC unattended.
- **Fluid Tab Navigation**: 100% working admin tabs with integrated native JavaScript.
- **Automatic Bilingual**: If WordPress is in English, the plugin and messages are in English; if in Italian, they are in Italian.
- **PHP 8.5 Ready**: 100% compatible with PHP 8.0, 8.1, 8.2, 8.3, 8.4, and 8.5.
- **Zero Vulnerabilities**: Cryptographic nonces, strict sanitization, `manage_options` permission checks, and secure native logout via `wp_logout()`.
- **Automatic Updates**: The plugin automatically checks for new releases on GitHub and allows you to update directly from your WordPress dashboard.

## 📦 Installation

1. Download the latest `wp-inactivity-logout-pro.zip` file from the Releases section.
2. In your WordPress admin panel, go to **Plugins > Add New > Upload Plugin**.
3. Select the zip file and click **Install Now**, then **Activate**.
4. Go to **Settings > Inactivity Logout** to configure timeout and messages.

## 🔄 Automatic Updates

This plugin integrates with GitHub for automatic updates. When a new tag (release) is published in this repository, a GitHub Action automatically generates the versioned ZIP file. Users with the plugin installed will receive the update notification directly in their WordPress dashboard, leveraging the native WordPress cron system.

## ⚠️ Disclaimer

This software is provided **"AS IS"**, without any warranty. While it has been tested and reasonable efforts are made to ensure security and reliability, no guarantees are provided. As an open project, anyone may contribute or report issues, but this does not imply endorsement or liability from the maintainers.

**You use this software entirely at your own risk.** The authors and contributors are not liable for any damages, data loss, or unexpected behavior resulting from its use, modification, or distribution. Always review and test the code independently before deploying it in critical or production environments.

## 📜 License

This project is distributed under the MIT License. See the [LICENSE](LICENSE) file for details.
