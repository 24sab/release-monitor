=== Release Monitor ===
Contributors: YOUR-WPORG-USERNAME
Tags: release tracking, release, changelog, maintenance, developer tools
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Never let your plugins go stale. Track how long it has been since each plugin was released and get an email when a release is overdue.

== Description ==

Release Monitor is built for plugin developers, agencies and product teams who maintain several WordPress plugins and want to keep a steady release schedule.

Add the plugins you maintain, set how many months is "too long" without a release, and Release Monitor checks them every day. When a plugin passes its threshold, you get an email and a warning in your dashboard.

= Features =

* **WordPress.org plugins** – reads the current version from the WordPress.org Plugin API and records the date each new version appears. Readme-only updates are not counted as releases.
* **Premium plugins** – reads the latest release date from the plugin's changelog file on your site. Understands common date formats such as `2025-04-10`, `10/04/2025` and `April 10, 2025`.
* **Thresholds per plugin** – set a separate overdue limit (in months) for each plugin.
* **Email alerts** – one alert per release cycle, sent to one or more addresses.
* **Status dashboard** – total, overdue, up-to-date and failed checks at a glance.
* **Clear error reporting** – missing changelogs, unknown slugs and connection problems are shown, not hidden.
* **Daily automatic check** plus a "Run Check Now" button.

= External services =

To monitor plugins hosted on WordPress.org, this plugin requests public plugin information from the WordPress.org Plugin API (`https://api.wordpress.org/plugins/info/1.2/`). Only the slugs of the plugins you choose to monitor are sent. No data about your site or its users is sent. Requests are made during the daily check and when you click "Run Check Now".

* WordPress.org Terms of Service: https://wordpress.org/about/
* WordPress.org Privacy Policy: https://wordpress.org/about/privacy/

== Installation ==

1. Upload the `release-monitor` folder to `/wp-content/plugins/`, or install the plugin from the Plugins screen.
2. Activate the plugin.
3. Go to **Release Monitor → Manage Plugins** and add the plugins you want to track.
4. Set the email recipients under **Notifications**.
5. Click **Run Check Now**.

== Frequently Asked Questions ==

= How do I monitor a plugin from WordPress.org? =

Choose "WordPress.org" as the source and enter the plugin's slug: the last part of its URL, e.g. `woocommerce` for wordpress.org/plugins/woocommerce/.

= How do I monitor a premium plugin? =

The premium plugin must be installed on the same site. Choose "Premium (local changelog)" as the source, then enter its main file (e.g. `my-plugin/my-plugin.php`) and its changelog file (e.g. `my-plugin/changelog.txt`). Both paths are relative to the plugins folder.

= Which date formats are supported in changelogs? =

`2025-04-10`, `2025/04/10`, `10-04-2025`, `10.04.2025`, `10/04/2025` (day first), `April 10, 2025`, `10 April 2025` and `10th Apr 2025`. The newest date that is not in the future is used.

= The daily check does not run. =

Checks use WP-Cron, which only runs when your site gets visits. On low-traffic sites, set up a real server cron job that calls `wp-cron.php`.

= Will I get an email every day? =

No. One email is sent when a plugin becomes overdue. The reminder resets when a new release is detected.

== Screenshots ==

1. Status overview with totals and release status for each plugin.
2. Manage the plugins you want to monitor.
3. Notification settings.

== Changelog ==

= 1.0.0 =
* Initial public release.

== Upgrade Notice ==

= 1.0.0 =
Initial public release.
