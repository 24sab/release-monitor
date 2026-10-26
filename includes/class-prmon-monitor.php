<?php
if (!defined('ABSPATH')) exit;

class PRMON_Monitor {

    private $reminders = [];

    /**
     * Monitored plugins grouped by source: 'pro' (local changelog) and 'free' (WordPress.org).
     */
    public function get_plugins() {
        $saved = get_option('prmon_monitored_plugins', []);
        $plugins = ['pro' => [], 'free' => []];

        if (!is_array($saved)) return $plugins;

        foreach ($saved as $p) {
            $type = isset($p['type']) && $p['type'] === 'pro' ? 'pro' : 'free';
            if ($type === 'pro') {
                $plugins['pro'][] = [
                    'name'      => $p['name'],
                    'slug'      => $p['slug'],
                    'file'      => !empty($p['file']) ? $p['file'] : ($p['slug'] . '/' . $p['slug'] . '.php'),
                    'changelog' => !empty($p['changelog']) ? $p['changelog'] : ($p['slug'] . '/changelog.txt'),
                    'threshold' => (int)$p['threshold'],
                ];
            } else {
                $plugins['free'][] = [
                    'name'      => $p['name'],
                    'slug'      => $p['slug'],
                    'threshold' => (int)$p['threshold'],
                ];
            }
        }
        return $plugins;
    }

    public function run_check() {
        $plugin_groups = $this->get_plugins();
        $results = [];
        $this->reminders = get_option('prmon_reminders', []);

        // 1. Process premium plugins (local changelog file)
        foreach ($plugin_groups['pro'] as $plugin) {
            $plugin['type'] = 'pro';

            if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin['file'])) {
                /* translators: %s: plugin folder name */
                $results[$plugin['slug']] = $this->error_item($plugin, sprintf(__('Plugin is not installed on this site (%s).', 'release-monitor'), dirname($plugin['file'])));
                continue;
            }

            $changelog = $this->resolve_changelog_path($plugin['changelog']);
            if ($changelog === false) {
                $results[$plugin['slug']] = $this->error_item($plugin, __('Changelog must be inside the plugins folder.', 'release-monitor'));
                continue;
            }

            if (!is_readable($changelog)) {
                /* translators: %s: changelog file path */
                $results[$plugin['slug']] = $this->error_item($plugin, sprintf(__('Changelog file not found: %s', 'release-monitor'), $plugin['changelog']));
                continue;
            }

            $content = file_get_contents($changelog);
            $last_date = $content ? $this->parse_changelog_date($content) : null;

            if (!$last_date) {
                $results[$plugin['slug']] = $this->error_item($plugin, __('No release date found in changelog.', 'release-monitor'));
                continue;
            }

            $results[$plugin['slug']] = $this->prepare_item($plugin, $last_date);
        }

        // 2. Process WordPress.org plugins
        $release_log = get_option('prmon_release_log', []);

        foreach ($plugin_groups['free'] as $plugin) {
            $plugin['type'] = 'free';

            $url = add_query_arg([
                'action'                       => 'plugin_information',
                'request[slug]'                => $plugin['slug'],
                'request[fields][sections]'    => 0,
                'request[fields][description]' => 0,
            ], 'https://api.wordpress.org/plugins/info/1.2/');

            $response = wp_remote_get($url, ['timeout' => 15]);

            if (is_wp_error($response)) {
                /* translators: %s: error message */
                $results[$plugin['slug']] = $this->error_item($plugin, sprintf(__('WordPress.org request failed: %s', 'release-monitor'), $response->get_error_message()));
                continue;
            }

            $data = json_decode(wp_remote_retrieve_body($response));
            if (!$data || empty($data->version) || empty($data->last_updated)) {
                $error = ($data && isset($data->error)) ? $data->error : __('Invalid response from WordPress.org.', 'release-monitor');
                $results[$plugin['slug']] = $this->error_item($plugin, $error);
                continue;
            }

            // "last_updated" also changes on readme-only commits, so only move the
            // release date forward when the version number actually changes.
            $known = $release_log[$plugin['slug']] ?? null;
            if (!$known || $known['version'] !== $data->version) {
                $known = [
                    'version' => $data->version,
                    'date'    => wp_date('Y-m-d', strtotime($data->last_updated)),
                ];
                $release_log[$plugin['slug']] = $known;
            }

            $results[$plugin['slug']] = $this->prepare_item($plugin, $known['date'], $known['version']);
        }

        update_option('prmon_release_log', $release_log);
        update_option('prmon_reminders', $this->reminders);
        update_option('prmon_status', $results);
        update_option('prmon_last_checked', current_time('mysql'));
    }

    /**
     * Resolve a changelog setting (relative to the plugins folder, or absolute) to a file path.
     * Returns false when the file exists but lies outside the plugins folder.
     */
    private function resolve_changelog_path($raw) {
        $base = wp_normalize_path(realpath(WP_PLUGIN_DIR));
        $path = path_is_absolute($raw) ? $raw : WP_PLUGIN_DIR . '/' . ltrim($raw, '/\\');
        $real = realpath($path);

        if ($real === false) return wp_normalize_path($path); // Missing file; reported by the caller

        $real = wp_normalize_path($real);
        return strpos($real, trailingslashit($base)) === 0 ? $real : false;
    }

    /**
     * Find the latest valid (non-future) release date in a changelog.
     * Supports 2025-04-10, 2025/04/10, 10-04-2025, 10.04.2025, 10/04/2025 (day first),
     * "April 10, 2025", "10 April 2025" and "10th Apr 2025".
     */
    public function parse_changelog_date($content) {
        $tz = wp_timezone();
        $now = new DateTimeImmutable('now', $tz);
        $months = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';
        $dates = [];

        // Year first: 2025-04-10, 2025/04/10, 2025.04.10
        preg_match_all('/\b(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})\b/', $content, $m, PREG_SET_ORDER);
        foreach ($m as $d) {
            $dates[] = [(int)$d[1], (int)$d[2], (int)$d[3]];
        }

        // Day first: 10-04-2025, 10.04.2025, 10/04/2025
        preg_match_all('/\b(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})\b/', $content, $m, PREG_SET_ORDER);
        foreach ($m as $d) {
            $dates[] = [(int)$d[3], (int)$d[2], (int)$d[1]];
        }

        // Textual: "April 10, 2025" / "10 April 2025" / "10th Apr 2025"
        preg_match_all('/\b(' . $months . ')\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/i', $content, $m, PREG_SET_ORDER);
        foreach ($m as $d) {
            $dates[] = [(int)$d[3], (int)gmdate('n', strtotime($d[1] . ' 1 2000')), (int)$d[2]];
        }
        preg_match_all('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(' . $months . ')\.?,?\s+(\d{4})\b/i', $content, $m, PREG_SET_ORDER);
        foreach ($m as $d) {
            $dates[] = [(int)$d[3], (int)gmdate('n', strtotime($d[2] . ' 1 2000')), (int)$d[1]];
        }

        $latest = null;
        foreach ($dates as [$y, $mo, $d]) {
            if ($y < 2000 || !checkdate($mo, $d, $y)) continue;

            $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $mo, $d), $tz);
            if ($date > $now) continue; // Ignore typos / planned dates in the future

            if (!$latest || $date > $latest) {
                $latest = $date;
            }
        }

        return $latest ? $latest->format('Y-m-d') : null;
    }

    private function months_since($date) {
        $tz = wp_timezone();
        $diff = (new DateTimeImmutable($date, $tz))->diff(new DateTimeImmutable('now', $tz));
        return $diff->y * 12 + $diff->m;
    }

    private function get_recipients() {
        $settings = get_option('prmon_settings', []);
        $emails_str = !empty($settings['emails']) ? $settings['emails'] : get_option('admin_email');
        return array_filter(array_map('trim', explode(',', $emails_str)), 'is_email');
    }

    private function error_item($plugin, $message) {
        return [
            'name'         => $plugin['name'],
            'last_release' => '',
            'version'      => '',
            'months'       => null,
            'overdue'      => false,
            'error'        => $message,
            'type'         => $plugin['type'],
            'threshold'    => $plugin['threshold'],
        ];
    }

    private function prepare_item($plugin, $last_date, $version = '') {
        $months = $this->months_since($last_date);
        $is_overdue = $months >= $plugin['threshold'];

        // A new release resets the reminder for this plugin.
        $reminder = $this->reminders[$plugin['slug']] ?? null;
        if (!$reminder || $reminder['release'] !== $last_date) {
            $reminder = ['release' => $last_date, 'sent' => false];
        }

        if ($is_overdue && !$reminder['sent']) {
            $emails = $this->get_recipients();

            if (!empty($emails)) {
                /* translators: %s: plugin name */
                $subject = sprintf(__('Plugin release overdue: %s', 'release-monitor'), $plugin['name']);
                $lines = [
                    /* translators: %s: plugin name */
                    sprintf(__('Plugin: %s', 'release-monitor'), $plugin['name']),
                ];
                if ($version) {
                    /* translators: %s: version number */
                    $lines[] = sprintf(__('Version: %s', 'release-monitor'), $version);
                }
                /* translators: %s: release date */
                $lines[] = sprintf(__('Last release: %s', 'release-monitor'), $last_date);
                /* translators: %d: number of months */
                $lines[] = sprintf(_n('%d month since last release', '%d months since last release', $months, 'release-monitor'), $months);
                /* translators: %d: number of months */
                $lines[] = sprintf(_n('Threshold: %d month', 'Threshold: %d months', $plugin['threshold'], 'release-monitor'), $plugin['threshold']);
                $lines[] = '';
                $lines[] = admin_url('admin.php?page=release-monitor');

                wp_mail($emails, $subject, implode("\n", $lines));
            }

            $reminder['sent'] = true;
        }

        $this->reminders[$plugin['slug']] = $reminder;

        return [
            'name'         => $plugin['name'],
            'last_release' => $last_date,
            'version'      => $version,
            'months'       => $months,
            'overdue'      => $is_overdue,
            'error'        => '',
            'type'         => $plugin['type'],
            'threshold'    => $plugin['threshold'],
        ];
    }
}
