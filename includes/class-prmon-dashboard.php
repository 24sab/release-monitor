<?php
if (!defined('ABSPATH')) exit;

class PRMON_Dashboard {

    private $monitor;

    public function __construct($monitor) {
        $this->monitor = $monitor;
    }

    public static function type_label($type) {
        return strtolower($type) === 'pro'
            ? __('Premium', 'release-monitor')
            : __('WordPress.org', 'release-monitor');
    }

    public function render_overview_html() {
        $data = get_option('prmon_status', []);

        ob_start();

        if (empty($data)) {
            ?>
            <div class="prm-empty">
                <div class="prm-empty-icon"><span class="dashicons dashicons-portfolio"></span></div>
                <h2><?php esc_html_e('No Data Yet', 'release-monitor'); ?></h2>
                <p><?php esc_html_e('Add your plugins in the "Manage Plugins" tab, then click "Run Check Now" to start monitoring.', 'release-monitor'); ?></p>
            </div>
            <?php
            return ob_get_clean();
        }

        $total = count($data);
        $overdue = count(array_filter($data, function($p) { return !empty($p['overdue']); }));
        $errors = count(array_filter($data, function($p) { return !empty($p['error']); }));
        ?>
        <div class="prm-stats">
            <div class="prm-stat-card"><h3><?php echo (int)$total; ?></h3><p><?php esc_html_e('Total Plugins', 'release-monitor'); ?></p></div>
            <div class="prm-stat-card overdue"><h3><?php echo (int)$overdue; ?></h3><p><?php esc_html_e('Overdue', 'release-monitor'); ?></p></div>
            <div class="prm-stat-card healthy"><h3><?php echo (int)($total - $overdue - $errors); ?></h3><p><?php esc_html_e('Up to Date', 'release-monitor'); ?></p></div>
            <?php if ($errors) : ?>
                <div class="prm-stat-card failed"><h3><?php echo (int)$errors; ?></h3><p><?php esc_html_e('Check Failed', 'release-monitor'); ?></p></div>
            <?php endif; ?>
        </div>

        <div class="prm-table-container">
            <table class="prm-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Plugin Name', 'release-monitor'); ?></th>
                        <th><?php esc_html_e('Source', 'release-monitor'); ?></th>
                        <th><?php esc_html_e('Release Date', 'release-monitor'); ?></th>
                        <th><?php esc_html_e('Time Elapsed', 'release-monitor'); ?></th>
                        <th><?php esc_html_e('Status', 'release-monitor'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data as $plugin) :
                    $is_overdue = !empty($plugin['overdue']);
                    $error = $plugin['error'] ?? '';
                    $type = strtolower($plugin['type'] ?? 'free');
                    if ($error) {
                        $status_class = 'failed';
                        $status_text  = __('Check Failed', 'release-monitor');
                    } elseif ($is_overdue) {
                        $status_class = 'overdue';
                        $status_text  = __('Overdue', 'release-monitor');
                    } else {
                        $status_class = 'ok';
                        $status_text  = __('Healthy', 'release-monitor');
                    }
                    ?>
                    <tr class="<?php echo $is_overdue ? 'row-overdue' : ''; ?>">
                        <td class="plugin-name">
                            <strong><?php echo esc_html($plugin['name']); ?></strong>
                            <?php if (!empty($plugin['version'])) : ?>
                                <br><small>v<?php echo esc_html($plugin['version']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="prm-badge-type <?php echo esc_attr($type); ?>"><?php echo esc_html(self::type_label($type)); ?></span></td>
                        <td><code><?php echo esc_html($plugin['last_release'] ?: '—'); ?></code></td>
                        <td>
                            <?php
                            if ($plugin['months'] === null) {
                                echo '—';
                            } else {
                                /* translators: %d: number of months */
                                echo esc_html(sprintf(_n('%d month', '%d months', $plugin['months'], 'release-monitor'), $plugin['months']));
                            }
                            ?>
                        </td>
                        <td>
                            <span class="prm-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span>
                            <?php if ($error) : ?>
                                <br><small class="prm-error-text"><?php echo esc_html($error); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * One editable row of the "Manage Plugins" table. $index may be a placeholder for the JS row template.
     */
    private function render_plugin_row($index, $plugin = []) {
        $type = ($plugin['type'] ?? 'free') === 'pro' ? 'pro' : 'free';
        $field = function($key) use ($index) {
            return 'plugins[' . $index . '][' . $key . ']';
        };
        ?>
        <tr class="prm-plugin-row" data-index="<?php echo esc_attr($index); ?>">
            <td><input type="text" name="<?php echo esc_attr($field('name')); ?>" value="<?php echo esc_attr($plugin['name'] ?? ''); ?>" required placeholder="<?php esc_attr_e('e.g. My Plugin', 'release-monitor'); ?>"/></td>
            <td><input type="text" name="<?php echo esc_attr($field('slug')); ?>" value="<?php echo esc_attr($plugin['slug'] ?? ''); ?>" required placeholder="<?php esc_attr_e('e.g. my-plugin', 'release-monitor'); ?>"/></td>
            <td>
                <select name="<?php echo esc_attr($field('type')); ?>" class="prm-type-select">
                    <option value="free" <?php selected($type, 'free'); ?>><?php esc_html_e('WordPress.org', 'release-monitor'); ?></option>
                    <option value="pro" <?php selected($type, 'pro'); ?>><?php esc_html_e('Premium (local changelog)', 'release-monitor'); ?></option>
                </select>
            </td>
            <td><input type="number" class="prm-threshold-input" name="<?php echo esc_attr($field('threshold')); ?>" value="<?php echo (int)($plugin['threshold'] ?? 3); ?>" min="1" max="60"/></td>
            <td class="pro-fields-cell <?php echo $type === 'pro' ? 'active' : ''; ?>">
                <div class="pro-inputs">
                    <input type="text" name="<?php echo esc_attr($field('file')); ?>" value="<?php echo esc_attr($plugin['file'] ?? ''); ?>" placeholder="<?php esc_attr_e('Main file, e.g. my-plugin/my-plugin.php', 'release-monitor'); ?>"/>
                    <input type="text" name="<?php echo esc_attr($field('changelog')); ?>" value="<?php echo esc_attr($plugin['changelog'] ?? ''); ?>" placeholder="<?php esc_attr_e('Changelog, e.g. my-plugin/changelog.txt', 'release-monitor'); ?>"/>
                </div>
                <span class="pro-fields-na"><?php esc_html_e('Not needed for WordPress.org plugins', 'release-monitor'); ?></span>
            </td>
            <td><button type="button" class="prm-btn-delete prm-row-remove"><?php esc_html_e('Delete', 'release-monitor'); ?></button></td>
        </tr>
        <?php
    }

    public function render() {
        $last_checked = get_option('prmon_last_checked', __('Never', 'release-monitor'));
        $monitored_plugins = get_option('prmon_monitored_plugins', []);
        if (!is_array($monitored_plugins)) $monitored_plugins = [];

        $settings = get_option('prmon_settings', []);
        $emails = $settings['emails'] ?? get_option('admin_email');
        ?>
        <div class="wrap prm-wrap">
            <div id="prm-alert-container"></div>

            <div class="prm-header">
                <div class="prm-heading">
                    <h1><?php esc_html_e('Release Monitor', 'release-monitor'); ?></h1>
                    <p>
                        <?php esc_html_e('Track your plugin release lifecycle.', 'release-monitor'); ?>
                        <?php esc_html_e('Last check:', 'release-monitor'); ?>
                        <code id="prm-last-checked-date"><?php echo esc_html($last_checked); ?></code>
                    </p>
                </div>
                <div class="prm-actions">
                    <form id="prm-run-check-form" method="post">
                        <?php wp_nonce_field('prmon_run_check', 'prmon_nonce'); ?>
                        <button type="submit" class="prm-btn"><?php esc_html_e('Run Check Now', 'release-monitor'); ?></button>
                    </form>
                </div>
            </div>

            <div class="prm-tabs-nav">
                <button class="prm-tab-link active" data-tab="overview"><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Status Overview', 'release-monitor'); ?></button>
                <button class="prm-tab-link" data-tab="plugins"><span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e('Manage Plugins', 'release-monitor'); ?></button>
                <button class="prm-tab-link" data-tab="notifications"><span class="dashicons dashicons-bell"></span> <?php esc_html_e('Notifications', 'release-monitor'); ?></button>
            </div>

            <div id="prm-tab-overview" class="prm-tab-content active">
                <div id="prm-overview-dynamic-content">
                    <?php echo $this->render_overview_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while building ?>
                </div>
            </div>

            <div id="prm-tab-plugins" class="prm-tab-content">
                <div class="prm-card">
                    <h2><?php esc_html_e('Monitored Plugins', 'release-monitor'); ?></h2>
                    <p class="subtitle"><?php esc_html_e('Add plugins hosted on WordPress.org, or premium plugins installed on this site that ship a changelog file.', 'release-monitor'); ?></p>
                    <form id="prm-plugins-form" method="post" action="">
                        <?php wp_nonce_field('prmon_save_plugins', 'prmon_plugins_nonce'); ?>
                        <table class="prm-settings-table" id="prm-plugins-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Plugin Name', 'release-monitor'); ?></th>
                                    <th><?php esc_html_e('Slug', 'release-monitor'); ?></th>
                                    <th><?php esc_html_e('Source', 'release-monitor'); ?></th>
                                    <th><?php esc_html_e('Threshold (Months)', 'release-monitor'); ?></th>
                                    <th><?php esc_html_e('Premium Plugin Files', 'release-monitor'); ?></th>
                                    <th><?php esc_html_e('Action', 'release-monitor'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach (array_values($monitored_plugins) as $index => $plugin) {
                                    $this->render_plugin_row($index, $plugin);
                                }
                                ?>
                            </tbody>
                        </table>
                        <template id="prm-plugin-row-template">
                            <?php $this->render_plugin_row('__INDEX__'); ?>
                        </template>

                        <div class="prm-settings-footer">
                            <button type="button" class="prm-btn secondary" id="prm-add-plugin-btn"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Add New Plugin', 'release-monitor'); ?></button>
                            <button type="submit" class="prm-btn"><?php esc_html_e('Save Changes', 'release-monitor'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="prm-tab-notifications" class="prm-tab-content">
                <div class="prm-card">
                    <h2><?php esc_html_e('Notification Settings', 'release-monitor'); ?></h2>
                    <p class="subtitle"><?php esc_html_e('Choose who receives overdue release alerts.', 'release-monitor'); ?></p>
                    <form id="prm-settings-form" method="post" action="">
                        <?php wp_nonce_field('prmon_save_settings', 'prmon_settings_nonce'); ?>
                        <div class="prm-field-group">
                            <label for="prmon-emails"><?php esc_html_e('Recipient Email Addresses', 'release-monitor'); ?></label>
                            <textarea name="emails" id="prmon-emails" class="prm-input-block" placeholder="<?php esc_attr_e('e.g. admin@example.com, dev@example.com', 'release-monitor'); ?>"><?php echo esc_textarea($emails); ?></textarea>
                            <span class="description"><?php esc_html_e('Comma-separated. Leave empty to use the site admin email.', 'release-monitor'); ?></span>
                        </div>
                        <div class="prm-settings-footer">
                            <button type="submit" class="prm-btn"><?php esc_html_e('Save Changes', 'release-monitor'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /* AJAX endpoints */

    public function ajax_save_plugins() {
        check_ajax_referer('prmon_save_plugins', 'prmon_plugins_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You are not allowed to do this.', 'release-monitor')]);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every field is sanitized below
        $submitted = isset($_POST['plugins']) && is_array($_POST['plugins']) ? wp_unslash($_POST['plugins']) : [];
        $new_plugins = [];
        foreach ($submitted as $p) {
            if (!is_array($p) || empty($p['name']) || empty($p['slug'])) {
                continue;
            }
            $new_plugins[] = [
                'name'      => sanitize_text_field($p['name']),
                'slug'      => sanitize_title($p['slug']),
                'type'      => isset($p['type']) && $p['type'] === 'pro' ? 'pro' : 'free',
                'threshold' => min(60, max(1, (int)($p['threshold'] ?? 1))),
                'file'      => isset($p['file']) ? sanitize_text_field($p['file']) : '',
                'changelog' => isset($p['changelog']) ? sanitize_text_field($p['changelog']) : '',
            ];
        }
        update_option('prmon_monitored_plugins', $new_plugins);
        wp_send_json_success(['message' => __('Plugins saved.', 'release-monitor')]);
    }

    public function ajax_save_settings() {
        check_ajax_referer('prmon_save_settings', 'prmon_settings_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You are not allowed to do this.', 'release-monitor')]);
        }

        $raw = isset($_POST['emails']) ? sanitize_textarea_field(wp_unslash($_POST['emails'])) : '';
        $emails = array_filter(array_map('sanitize_email', explode(',', $raw)), 'is_email');

        update_option('prmon_settings', ['emails' => implode(', ', $emails)]);
        wp_send_json_success(['message' => __('Notification settings saved.', 'release-monitor')]);
    }

    public function ajax_run_check() {
        check_ajax_referer('prmon_run_check', 'prmon_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You are not allowed to do this.', 'release-monitor')]);
        }

        $this->monitor->run_check();

        wp_send_json_success([
            'message'      => __('Check completed.', 'release-monitor'),
            'html'         => $this->render_overview_html(),
            'last_checked' => get_option('prmon_last_checked', ''),
        ]);
    }
}
