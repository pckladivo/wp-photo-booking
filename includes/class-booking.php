<?php
if (!defined('ABSPATH')) die('Forbidden');

class WPPB_Booking {

    // -----------------------------------------------
    // Booking lock
    // -----------------------------------------------

    private static $locked = false;

    private static function lock_name() {
        global $wpdb;
        // Database lock names are server-wide, so make it unique to this site.
        return 'wppb_' . md5(DB_NAME . $wpdb->prefix);
    }

    /** Serialise booking creation. Returns false when the lock is not obtained in time. */
    public static function lock() {
        global $wpdb;
        self::$locked = $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, 10)", self::lock_name())) === '1';
        return self::$locked;
    }

    public static function unlock() {
        global $wpdb;
        if (!self::$locked) return;
        $wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", self::lock_name()));
        self::$locked = false;
    }

    /** Statuses an entry of the given type can have. */
    public static function statuses($entry_type) {
        return $entry_type === 'contact'
            ? array('new', 'read', 'archived')
            : array('pending', 'confirmed', 'cancelled');
    }

    /**
     * Store one form submission.
     *
     * @param object $form  Form row.
     * @param array  $items Output of WPPB_Form_Renderer::collect().
     * @return int|false Entry ID.
     */
    public static function create_entry($form, $items) {
        global $wpdb;

        $is_booking = WPPB_Forms::creates_bookings($form);
        $now        = current_time('mysql');

        // Column => maximum length; filled from the fields that carry the matching role.
        $limits = array(
            'first_name'       => 100,
            'last_name'        => 100,
            'email'            => 200,
            'phone'            => 50,
            'photography_type' => 100,
            'time_slot'        => 10,
            'message'          => 0,
            'booking_date'     => 0,
        );
        $cols = array_fill_keys(array_keys($limits), '');
        foreach ($items as $item) {
            $role = $item['role'];
            if ($role === '' || !isset($limits[$role])) continue;
            $cols[$role] = $limits[$role] ? mb_substr($item['value'], 0, $limits[$role]) : $item['value'];
        }
        if (!$is_booking) {
            $cols['time_slot'] = '';
        }

        $row = array_merge($cols, array(
            'form_id'             => intval($form->id),
            'entry_type'          => $is_booking ? 'booking' : 'contact',
            'email'               => sanitize_email($cols['email']),
            'booking_date'        => ($is_booking && $cols['booking_date'] !== '') ? $cols['booking_date'] : null,
            'status'              => $is_booking ? 'pending' : 'new',
            'booking_lang'        => function_exists('pll_current_language') ? (string) pll_current_language() : '',
            'questionnaire_token' => $is_booking ? wp_generate_password(32, false) : '',
            // Full snapshot of the submission, in form order, with the labels shown at the time.
            'custom_data'         => wp_json_encode(array_map(function ($item) {
                return array(
                    'id'    => $item['id'],
                    'role'  => $item['role'],
                    'label' => $item['label'],
                    'value' => $item['value'],
                );
            }, $items)),
            'created_at'          => $now,
            'updated_at'          => $now,
        ));

        if (!$wpdb->insert(WPPB_TABLE_BOOKINGS, $row)) return false;
        $id = $wpdb->insert_id;

        if ($is_booking) {
            $desc = trim($cols['photography_type'] . ' on ' . $cols['booking_date']);
            if ($cols['time_slot']) $desc .= ' at ' . $cols['time_slot'];
            self::log_activity($id, 'booking_created', $desc);
        } else {
            self::log_activity($id, 'message_received', 'Submitted via form "' . $form->name . '"');
        }
        return $id;
    }

    /**
     * Submitted fields of an entry as a list of array( id, role, label, value ).
     * Entries created before 1.7 have no snapshot, so it is rebuilt from the columns.
     */
    public static function get_items($entry) {
        $items = !empty($entry->custom_data) ? json_decode($entry->custom_data, true) : null;
        if (is_array($items)) return $items;

        $legacy = array(
            'photography_type' => 'Type',
            'first_name'       => 'First Name',
            'last_name'        => 'Last Name',
            'email'            => 'Email',
            'phone'            => 'Phone',
            'booking_date'     => 'Date',
            'time_slot'        => 'Time',
            'message'          => 'Message',
        );
        $items = array();
        foreach ($legacy as $col => $label) {
            if (!isset($entry->$col) || $entry->$col === '') continue;
            $items[] = array('id' => $col, 'role' => $col, 'label' => $label, 'value' => $entry->$col);
        }
        return $items;
    }

    /** Submitted fields that are not stored in a column of their own. */
    public static function get_extra_items($entry) {
        return array_values(array_filter(self::get_items($entry), function ($item) {
            return empty($item['role']);
        }));
    }

    public static function get($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPPB_TABLE_BOOKINGS . " WHERE id = %d",
            intval($id)
        ));
    }

    public static function get_all($filters = array()) {
        global $wpdb;
        $where  = array('1=1');
        $params = array();

        // Bookings only by default; pass 'entry_type' => 'all' to include every entry.
        $entry_type = isset($filters['entry_type']) ? $filters['entry_type'] : 'booking';
        if ($entry_type !== 'all') {
            $where[]  = 'entry_type = %s';
            $params[] = $entry_type;
        }

        if (!empty($filters['status'])) {
            $where[]  = 'status = %s';
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'booking_date >= %s';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'booking_date <= %s';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['form_id'])) {
            $where[]  = 'form_id = %d';
            $params[] = intval($filters['form_id']);
        }
        if (!empty($filters['search'])) {
            $like     = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where[]  = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR message LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }

        // Bookings in session order, messages newest first.
        $order = $entry_type === 'contact' ? 'created_at DESC, id DESC' : 'booking_date ASC, created_at DESC';
        $sql   = "SELECT * FROM " . WPPB_TABLE_BOOKINGS . " WHERE " . implode(' AND ', $where) . " ORDER BY " . $order;

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return $wpdb->get_results($sql);
    }

    public static function update($id, $data) {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        return $wpdb->update(WPPB_TABLE_BOOKINGS, $data, array('id' => intval($id)));
    }

    public static function confirm($id) {
        $result = self::update($id, array('status' => 'confirmed'));
        if ($result !== false) {
            self::log_activity($id, 'status_confirmed', 'Booking confirmed by admin');
        }
        return $result;
    }

    public static function cancel($id) {
        $result = self::update($id, array('status' => 'cancelled'));
        if ($result !== false) {
            self::log_activity($id, 'status_cancelled', 'Booking cancelled by admin');
        }
        return $result;
    }

    public static function delete($id) {
        global $wpdb;
        $id = intval($id);
        $wpdb->delete(WPPB_TABLE_ACTIVITY,  array('booking_id' => $id));
        $wpdb->delete(WPPB_TABLE_EMAIL_LOG, array('booking_id' => $id));
        return $wpdb->delete(WPPB_TABLE_BOOKINGS, array('id' => $id));
    }

    /**
     * Get dates that are fully booked (no available slots left).
     * In date-only mode: any date with a pending/confirmed booking.
     * In time-slot mode: only dates where ALL configured slots are taken.
     */
    public static function get_booked_dates() {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT booking_date, time_slot FROM " . WPPB_TABLE_BOOKINGS . " WHERE entry_type = 'booking' AND status IN ('pending','confirmed')"
        );

        $slots = WPPB_Settings::get_time_slots();

        // Date-only mode: any booked date is fully booked
        if (empty($slots)) {
            return array_unique(array_column($rows, 'booking_date'));
        }

        // Time-slot mode: count booked slots per date
        $booked_per_date = array();
        foreach ($rows as $row) {
            $d = $row->booking_date;
            if (!isset($booked_per_date[$d])) $booked_per_date[$d] = 0;
            $booked_per_date[$d]++;
        }

        $total_slots  = count($slots);
        $fully_booked = array();
        foreach ($booked_per_date as $date => $count) {
            if ($count >= $total_slots) {
                $fully_booked[] = $date;
            }
        }
        return $fully_booked;
    }

    /**
     * Get available time slots for a specific date.
     * Returns all configured slots minus those already booked.
     */
    public static function get_available_slots($date) {
        $all_slots = WPPB_Settings::get_time_slots();
        if (empty($all_slots)) return array();

        global $wpdb;
        $taken = $wpdb->get_col($wpdb->prepare(
            "SELECT time_slot FROM " . WPPB_TABLE_BOOKINGS .
            " WHERE entry_type = 'booking' AND booking_date = %s AND status IN ('pending','confirmed') AND time_slot != ''",
            $date
        ));

        return array_values(array_diff($all_slots, $taken));
    }

    public static function get_blocked_dates() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . WPPB_TABLE_BLOCKED . " ORDER BY blocked_date ASC");
    }

    public static function add_blocked_date($date, $reason = '') {
        global $wpdb;
        return $wpdb->replace(
            WPPB_TABLE_BLOCKED,
            array(
                'blocked_date' => sanitize_text_field($date),
                'reason'       => sanitize_text_field($reason),
            )
        );
    }

    public static function remove_blocked_date($id) {
        global $wpdb;
        return $wpdb->delete(WPPB_TABLE_BLOCKED, array('id' => intval($id)));
    }

    /**
     * Check if a date (and optionally a time slot) is available for booking.
     */
    public static function is_date_available($date, $time_slot = '') {
        global $wpdb;

        // Must be in the future (respecting min_days_ahead)
        $min_days = max(1, intval(WPPB_Settings::get('min_days_ahead')));
        if (strtotime($date) < strtotime('+' . $min_days . ' days midnight')) {
            return false;
        }

        // Must be a working day
        $dow          = intval(date('w', strtotime($date)));
        $working_days = WPPB_Settings::get_working_days();
        if (!in_array($dow, $working_days)) {
            return false;
        }

        // Must not be manually blocked
        $blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . WPPB_TABLE_BLOCKED . " WHERE blocked_date = %s",
            $date
        ));
        if ($blocked) return false;

        // Time-slot mode: check if specific slot is taken
        if (WPPB_Settings::has_time_slots()) {
            if (!$time_slot) return false; // slot is required when time slots are configured
            $taken = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . WPPB_TABLE_BOOKINGS . " WHERE entry_type = 'booking' AND booking_date = %s AND time_slot = %s AND status IN ('pending','confirmed')",
                $date, $time_slot
            ));
            return !$taken;
        }

        // Date-only mode: check if any booking exists
        $booked = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . WPPB_TABLE_BOOKINGS . " WHERE entry_type = 'booking' AND booking_date = %s AND status IN ('pending','confirmed')",
            $date
        ));
        return !$booked;
    }

    public static function count_by_status($entry_type = 'booking', $form_id = 0) {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT status, COUNT(*) as cnt FROM " . WPPB_TABLE_BOOKINGS . " WHERE entry_type = %s",
            $entry_type
        );
        if ($form_id) {
            $sql .= $wpdb->prepare(" AND form_id = %d", intval($form_id));
        }
        $counts = array_fill_keys(self::statuses($entry_type), 0);
        foreach ($wpdb->get_results($sql . " GROUP BY status") as $row) {
            $counts[$row->status] = intval($row->cnt);
        }
        return $counts;
    }

    /** Change the status of a contact entry (new / read / archived). */
    public static function set_contact_status($id, $status) {
        $entry = self::get($id);
        if (!$entry || $entry->entry_type !== 'contact' || !in_array($status, self::statuses('contact'), true)) {
            return false;
        }
        if ($entry->status === $status) return true;
        $result = self::update($id, array('status' => $status));
        if ($result !== false) {
            self::log_activity($id, 'status_' . $status, 'Message marked as ' . $status);
        }
        return $result !== false;
    }

    // -----------------------------------------------
    // Questionnaire
    // -----------------------------------------------

    public static function get_by_questionnaire_token($token) {
        global $wpdb;
        if (empty($token)) return null;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . WPPB_TABLE_BOOKINGS . " WHERE questionnaire_token = %s",
            $token
        ));
    }

    public static function save_questionnaire($booking_id, $data_json) {
        global $wpdb;
        $wpdb->update(WPPB_TABLE_BOOKINGS, array(
            'questionnaire_data'         => $data_json,
            'questionnaire_completed_at' => current_time('mysql'),
            'updated_at'                 => current_time('mysql'),
        ), array('id' => intval($booking_id)));
        self::log_activity($booking_id, 'questionnaire_completed', 'Client completed the pre-session questionnaire');
    }

    // -----------------------------------------------
    // Activity log
    // -----------------------------------------------

    public static function log_activity($booking_id, $action, $detail = '') {
        global $wpdb;
        $wpdb->insert(WPPB_TABLE_ACTIVITY, array(
            'booking_id' => intval($booking_id),
            'action'     => sanitize_text_field($action),
            'detail'     => sanitize_text_field($detail),
            // Data minimisation: the address of whoever triggered the event is not recorded.
            'ip'         => '',
            'created_at' => current_time('mysql'),
        ));
    }

    public static function get_activity($booking_id, $limit = 50) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT action, detail, ip, created_at FROM " . WPPB_TABLE_ACTIVITY .
            " WHERE booking_id = %d ORDER BY created_at DESC LIMIT %d",
            intval($booking_id), intval($limit)
        ));
    }

    // -----------------------------------------------
    // Email log (used by cron to prevent duplicates)
    // -----------------------------------------------

    public static function email_was_sent($booking_id, $email_type) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . WPPB_TABLE_EMAIL_LOG . " WHERE booking_id = %d AND email_type = %s",
            intval($booking_id), $email_type
        ));
    }

    public static function mark_email_sent($booking_id, $email_type) {
        global $wpdb;
        $wpdb->replace(WPPB_TABLE_EMAIL_LOG, array(
            'booking_id' => intval($booking_id),
            'email_type' => $email_type,
            'sent_at'    => current_time('mysql'),
        ));
    }
}
