<?php
if (!defined('ABSPATH')) die('Forbidden');

/**
 * Data protection: hooks the plugin into WordPress' personal data export and
 * erasure tools (Tools → Export / Erase Personal Data), suggests text for the
 * privacy policy and deletes entries once their retention period has passed.
 */
class WPPB_Privacy {

    const PER_PAGE = 50;

    public static function init() {
        add_filter('wp_privacy_personal_data_exporters', array(__CLASS__, 'register_exporter'));
        add_filter('wp_privacy_personal_data_erasers',   array(__CLASS__, 'register_eraser'));
        add_action('admin_init',                         array(__CLASS__, 'add_policy_content'));
    }

    public static function register_exporter($exporters) {
        $exporters['wp-photo-booking'] = array(
            'exporter_friendly_name' => 'WP Photo Booking',
            'callback'               => array(__CLASS__, 'export'),
        );
        return $exporters;
    }

    public static function register_eraser($erasers) {
        $erasers['wp-photo-booking'] = array(
            'eraser_friendly_name' => 'WP Photo Booking',
            'callback'             => array(__CLASS__, 'erase'),
        );
        return $erasers;
    }

    /**
     * Entries (bookings and messages) submitted with an email address.
     * Matches the client email column and, for forms where the address was
     * collected as an extra field, the stored copy of the submission.
     */
    public static function find_entries($email, $page = 1) {
        global $wpdb;
        $email = sanitize_email($email);
        if (!is_email($email)) return array();

        $in_snapshot = '%' . $wpdb->esc_like('"value":' . wp_json_encode($email)) . '%';
        $offset      = (max(1, intval($page)) - 1) * self::PER_PAGE;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . WPPB_TABLE_BOOKINGS . " WHERE email = %s OR custom_data LIKE %s ORDER BY id ASC LIMIT %d OFFSET %d",
            $email, $in_snapshot, self::PER_PAGE, $offset
        ));
    }

    /**
     * Personal data exporter callback.
     */
    public static function export($email, $page = 1) {
        $entries    = self::find_entries($email, $page);
        $form_names = array();
        foreach (WPPB_Forms::get_all() as $form) {
            $form_names[$form->id] = $form->name;
        }

        $export = array();
        foreach ($entries as $entry) {
            $is_booking = $entry->entry_type === 'booking';
            $data = array(
                array('name' => 'Type',      'value' => $is_booking ? 'Booking request' : 'Message'),
                array('name' => 'Form',      'value' => $form_names[$entry->form_id] ?? ''),
                array('name' => 'Submitted', 'value' => $entry->created_at),
                array('name' => 'Status',    'value' => $entry->status),
            );
            foreach (WPPB_Booking::get_items($entry) as $item) {
                if ($item['value'] === '') continue;
                $data[] = array('name' => $item['label'], 'value' => $item['value']);
            }
            if (!empty($entry->questionnaire_data)) {
                $answers = json_decode($entry->questionnaire_data, true);
                foreach (is_array($answers) ? $answers : array() as $answer) {
                    if (($answer['value'] ?? '') === '') continue;
                    $data[] = array('name' => 'Questionnaire: ' . ($answer['label'] ?? ''), 'value' => $answer['value']);
                }
            }

            $export[] = array(
                'group_id'          => 'wp-photo-booking',
                'group_label'       => 'Booking requests and messages',
                'group_description' => 'Information you submitted through the forms on this site.',
                'item_id'           => 'wppb-entry-' . intval($entry->id),
                'data'              => $data,
            );
        }

        return array(
            'data' => $export,
            'done' => count($entries) < self::PER_PAGE,
        );
    }

    /**
     * Personal data eraser callback: removes the entries together with their
     * activity and email log.
     */
    public static function erase($email, $page = 1) {
        // Deleted rows drop out of the result, so always work on the first page.
        $entries = self::find_entries($email, 1);
        $removed = false;

        foreach ($entries as $entry) {
            if (WPPB_Booking::delete($entry->id)) $removed = true;
        }

        return array(
            'items_removed'  => $removed,
            'items_retained' => false,
            'messages'       => array(),
            'done'           => count($entries) < self::PER_PAGE,
        );
    }

    /**
     * Delete entries whose retention period has passed. Runs with the daily cron.
     * Messages age from the day they were received, bookings from the session date.
     *
     * @return int Number of entries deleted.
     */
    public static function purge_expired() {
        global $wpdb;
        $deleted = 0;
        $today   = current_time('timestamp');

        $rules = array(
            'contact' => array('created_at',   intval(WPPB_Settings::get('retention_messages_days'))),
            'booking' => array('booking_date', intval(WPPB_Settings::get('retention_bookings_days'))),
        );

        foreach ($rules as $entry_type => $rule) {
            list($column, $days) = $rule;
            if ($days <= 0) continue;

            $cutoff = date('Y-m-d H:i:s', $today - $days * DAY_IN_SECONDS);
            $ids    = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM " . WPPB_TABLE_BOOKINGS . " WHERE entry_type = %s AND {$column} IS NOT NULL AND {$column} < %s LIMIT 500",
                $entry_type, $cutoff
            ));
            foreach ($ids as $id) {
                if (WPPB_Booking::delete($id)) $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Suggested wording shown under Settings → Privacy → policy guide.
     */
    public static function add_policy_content() {
        if (!function_exists('wp_add_privacy_policy_content')) return;

        $messages_days = intval(WPPB_Settings::get('retention_messages_days'));
        $bookings_days = intval(WPPB_Settings::get('retention_bookings_days'));

        $content  = '<h2>Booking and contact forms</h2>';
        $content .= '<p class="privacy-policy-tutorial">This text is a suggestion. Adapt it to what your forms actually ask for and to your legal basis for processing.</p>';
        $content .= '<p><strong>What we collect.</strong> When you send a booking request or a message through a form on this site, we store what you enter in the form (for example your name, email address, phone number, the requested date and your message) together with the date and time of the submission. If you later fill in a pre-session questionnaire, your answers are stored with your booking.</p>';
        $content .= '<p><strong>Why.</strong> We use this information only to answer your enquiry, to arrange and carry out the photo session, and to send you emails about it (acknowledgement, confirmation or cancellation, reminders).</p>';
        $content .= '<p><strong>What we do not collect.</strong> We do not store your IP address with your submission. To prevent abuse, a scrambled, non-reversible value derived from your network address is kept for ten minutes and then discarded.</p>';
        if (WPPB_Settings::has_recaptcha()) {
            $content .= '<p><strong>Third parties.</strong> Forms protected by Google reCAPTCHA load a script from Google, which receives your IP address and information about your browser to decide whether you are a person. Google\'s privacy policy applies to that processing.</p>';
        }
        $content .= '<p><strong>How long we keep it.</strong> '
            . ($messages_days > 0 ? 'Messages are deleted automatically ' . $messages_days . ' days after we receive them. ' : 'Messages are kept until we delete them. ')
            . ($bookings_days > 0 ? 'Booking requests are deleted automatically ' . $bookings_days . ' days after the session date.' : 'Booking requests are kept until we delete them.')
            . '</p>';
        $content .= '<p><strong>Your rights.</strong> You can ask us for a copy of the information we hold about you, or ask us to delete it, at any time.</p>';

        wp_add_privacy_policy_content('WP Photo Booking', wp_kses_post($content));
    }
}

WPPB_Privacy::init();
