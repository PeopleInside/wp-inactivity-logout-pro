=== WP Inactivity Logout Pro ===
Contributors: PeopleInside
Tags: logout, inactivity, security, session, closed tab, inactivity logout, auto logout
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.5
License: MIT License
License URI: https://github.com/PeopleInside/wp-inactivity-logout-pro/blob/main/LICENSE
Plugin URI: https://github.com/PeopleInside/wp-inactivity-logout-pro
Author URI: https://github.com/PeopleInside

Automatic inactivity logout with server-side closed-tab protection, customizable countdown warning popup, and full bilingual support (IT/EN).

== Description ==

WP Inactivity Logout Pro protects WordPress user sessions across all roles (administrators, editors, customers, and subscribers).

Unlike traditional plugins that only work via JavaScript while the browser tab is open, WP Inactivity Logout Pro introduces a **dual security barrier**:

1. **Client-Side Engine**: Continuously monitors user activity (mouse, keyboard, touch, scroll) without displaying a popup while the user is active. If the user becomes truly inactive, it displays a countdown popup synchronized across all open tabs via BroadcastChannel and localStorage.
2. **Server-Side Closed-Tab Guard**: Records the last active timestamp. If a user closes the browser or tab and returns after the allowed time (e.g., 30 minutes), the session is immediately destroyed server-side on `init` using the native `wp_logout()` API, avoiding 500 errors or redirect loops.

== Key Features ==

* **Continuous Activity Detection**: No premature popups while the user is interacting with the page.
* **Customizable Warning & Countdown**: Set the logout time (e.g., 30 min) and the countdown warning (e.g., after 15 min).
* **Disable with 0**: Set warning minutes to 0 to completely disable the popup and perform a direct logout.
* **Validity Check**: The warning value cannot be greater than or equal to the total logout time.
* **Closed-Tab Protection**: Total security if the user closes the window and leaves the PC unattended.
* **Fluid Tab Navigation**: 100% working admin tabs with integrated native JavaScript.
* **Automatic Bilingual**: If WordPress is in English, the plugin and messages are in English; if in Italian, they are in Italian.
* **PHP 8.5 Ready**: 100% compatible with PHP 8.0, 8.1, 8.2, 8.3, 8.4, and 8.5.
* **Zero Vulnerabilities**: Cryptographic nonces, strict sanitization, `manage_options` permission checks, and secure native logout via `wp_logout()`.
* **Automatic Updates**: Receive updates directly from your WordPress dashboard when a new release is published on GitHub.

== Installation ==

1. Download the `wp-inactivity-logout-pro.zip` file from the [Releases](https://github.com/PeopleInside/wp-inactivity-logout-pro/releases) section on GitHub.
2. In your WordPress admin panel, go to **Plugins > Add New > Upload Plugin**.
3. Select the zip file and click **Install Now**, then **Activate**.
4. Go to **Settings > Inactivity Logout** to configure timeout and messages.

== Changelog ==

= 1.0.5 =
* Improved README with GitHub style and repository links.
* Integrated automatic update system via GitHub Releases.
* Added GitHub Actions workflow for automatic versioned ZIP generation on every new tag.
* Corrected license to MIT License in all headers.

= 1.0.4 =
* Initial public release with server-side and client-side protection.
