<?php
/**
 * Plugin Name:       Release Monitor
 * Description:       Track how long it has been since each of your plugins was released, and get email alerts when a release is overdue.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Sabira
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       release-monitor
 */

if (!defined('ABSPATH')) exit;

define('PRMON_VERSION', '1.0.0');
define('PRMON_FILE', __FILE__);

require_once plugin_dir_path(__FILE__) . 'includes/class-prmon-monitor.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-prmon-dashboard.php';

class PRMON_Main {

    const PAGE_HOOK = 'toplevel_page_release-monitor';

    private $monitor;
    private $dashboard;

    public function __construct() {
        $this->monitor = new PRMON_Monitor();
        $this->dashboard = new PRMON_Dashboard($this->monitor);

        add_action('admin_init', [$this, 'maybe_upgrade']);
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_notices', [$this, 'admin_notice']);
        add_action('prmon_daily_check', [$this->monitor, 'run_check']);
        add_filter('plugin_row_meta', [$this, 'add_badge'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('wp_ajax_prmon_save_plugins', [$this->dashboard, 'ajax_save_plugins']);
        add_action('wp_ajax_prmon_save_settings', [$this->dashboard, 'ajax_save_settings']);
        add_action('wp_ajax_prmon_run_check', [$this->dashboard, 'ajax_run_check']);
    }

    /**
     * Carry settings over from the pre-1.0 "prm_" option names.
     */
    public function maybe_upgrade() {
        if (get_option('prmon_version') === PRMON_VERSION) return;

        foreach (['monitored_plugins', 'settings', 'release_log', 'status', 'last_checked'] as $key) {
            $old = get_option('prm_' . $key);
            if ($old !== false && get_option('prmon_' . $key) === false) {
                update_option('prmon_' . $key, $old);
            }
            delete_option('prm_' . $key);
        }

        $plugins = get_option('prmon_monitored_plugins', []);
        if (is_array($plugins) && get_option('prmon_reminders') === false) {
            $reminders = [];
            foreach ($plugins as $p) {
                $release = get_option('prm_last_release_' . $p['slug']);
                if ($release) {
                    $reminders[$p['slug']] = ['release' => $release, 'sent' => (bool)get_option('prm_reminder_sent_' . $p['slug'])];
                }
                delete_option('prm_last_release_' . $p['slug']);
                delete_option('prm_reminder_sent_' . $p['slug']);
            }
            update_option('prmon_reminders', $reminders);
        }

        wp_clear_scheduled_hook('prm_daily_check');
        self::schedule();

        update_option('prmon_version', PRMON_VERSION);
    }

    public static function schedule() {
        if (!wp_next_scheduled('prmon_daily_check')) {
            wp_schedule_event(time(), 'daily', 'prmon_daily_check');
        }
    }

    private function get_overdue() {
        $data = get_option('prmon_status', []);
        return is_array($data) ? array_filter($data, function($p) { return !empty($p['overdue']); }) : [];
    }

    /**
     * Screens where overdue warnings are shown.
     */
    private function is_notice_screen() {
        $screen = get_current_screen();
        return $screen && in_array($screen->id, ['dashboard', 'plugins', self::PAGE_HOOK], true);
    }

    public function enqueue_assets($hook) {
        $url = plugin_dir_url(PRMON_FILE);
        $dir = plugin_dir_path(PRMON_FILE);

        if ($hook === self::PAGE_HOOK || ($this->is_notice_screen() && $this->get_overdue())) {
            wp_enqueue_style('prmon-dashboard', $url . 'assets/css/prm-dashboard.css', [], filemtime($dir . 'assets/css/prm-dashboard.css'));
        }

        if ($hook === self::PAGE_HOOK) {
            wp_enqueue_script('prmon-dashboard', $url . 'assets/js/prm-dashboard.js', [], filemtime($dir . 'assets/js/prm-dashboard.js'), true);
            wp_localize_script('prmon-dashboard', 'prmonData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'i18n'    => [
                    'saving'       => __('Saving...', 'release-monitor'),
                    'checking'     => __('Checking...', 'release-monitor'),
                    'saved'        => __('Saved.', 'release-monitor'),
                    'error'        => __('Something went wrong.', 'release-monitor'),
                    'networkError' => __('Network error. Please try again.', 'release-monitor'),
                ],
            ]);
        }
    }

    public function add_menu() {
        add_menu_page(
            __('Release Monitor', 'release-monitor'),
            __('Release Monitor', 'release-monitor'),
            'manage_options',
            'release-monitor',
            [$this->dashboard, 'render'],
            'dashicons-clock'
        );
    }

    public function admin_notice() {
        if (!current_user_can('manage_options') || !$this->is_notice_screen()) return;

        $overdue_plugins = $this->get_overdue();
        if (empty($overdue_plugins)) return;
        ?>
        <div class="notice notice-error is-dismissible prm-admin-notice">
            <p>
                <strong><?php esc_html_e('Plugin releases overdue:', 'release-monitor'); ?></strong><br>
                <?php foreach ($overdue_plugins as $plugin) : ?>
                    <span class="plugin-name"><?php echo esc_html($plugin['name']); ?></span>
                    (<?php
                    /* translators: %d: number of months */
                    echo esc_html(sprintf(_n('last release %d month ago', 'last release %d months ago', $plugin['months'], 'release-monitor'), $plugin['months']));
                    ?>)<br>
                <?php endforeach; ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=release-monitor')); ?>" class="notice-link"><?php esc_html_e('Open Release Monitor', 'release-monitor'); ?> &rarr;</a>
            </p>
        </div>
        <?php
    }

    public function add_badge($links, $file) {
        $data = get_option('prmon_status', []);
        if (empty($data)) return $links;

        foreach ($this->monitor->get_plugins()['pro'] as $p) {
            if ($file === $p['file'] && !empty($data[$p['slug']]['overdue'])) {
                $links[] = '<span class="prm-badge-overdue">' . esc_html__('Release overdue', 'release-monitor') . '</span>';
            }
        }
        return $links;
    }
}

new PRMON_Main();

register_activation_hook(__FILE__, ['PRMON_Main', 'schedule']);

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('prmon_daily_check');
});
