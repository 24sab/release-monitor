<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

$prmon_options = [
    'prmon_version', 'prmon_status', 'prmon_last_checked', 'prmon_settings',
    'prmon_monitored_plugins', 'prmon_release_log', 'prmon_reminders',
];
foreach ($prmon_options as $prmon_option) {
    delete_option($prmon_option);
}

// Options left by pre-1.0 versions
foreach (['prm_status', 'prm_last_checked', 'prm_settings', 'prm_monitored_plugins', 'prm_release_log'] as $prmon_option) {
    delete_option($prmon_option);
}
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('prm_reminder_sent_') . '%',
        $wpdb->esc_like('prm_last_release_') . '%'
    )
);

wp_clear_scheduled_hook('prmon_daily_check');
wp_clear_scheduled_hook('prm_daily_check');
