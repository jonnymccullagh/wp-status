=== WP Status ===
Contributors: jonnymccullagh
Tags: monitoring, api, json, server-health, status
Stable tag: 1.0.0
Requires at least: 5.0
Tested up to: 6.2
Requires PHP: 7.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

== Description ==
WP Status provides a JSON API endpoint for monitoring your WordPress site with tools like UptimeRobot, Nagios, or Zabbix.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/wp-status/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to *Settings > WP Status* to configure the API password.

== Frequently Asked Questions ==

= What data does the API provide? =  
The API provides basic status information, such as update availability, database connection and overall health.  

= Can I restrict access to the API? =  
Yes, you can configure a password in the *Settings > WP Status* section to restrict access to the endpoint.  

== Screenshots ==
1. The WP Status settings page where you configure the API password.  
2. Example JSON API response format for integration with monitoring tools.  

== Changelog ==
= 1.0.0 =
Initial release
