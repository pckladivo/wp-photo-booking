<?php
if (!defined('ABSPATH')) die('Forbidden');

class WPPB_Admin {
    public function __construct() {
        add_action('admin_menu',            array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        add_action('admin_post_wppb_confirm_booking',     array($this, 'confirm_booking'));
        add_action('admin_post_wppb_cancel_booking',      array($this, 'cancel_booking'));
        add_action('admin_post_wppb_delete_booking',      array($this, 'delete_booking'));
        add_action('admin_post_wppb_save_notes',          array($this, 'save_notes'));
        add_action('admin_post_wppb_add_blocked_date',    array($this, 'add_blocked_date'));
        add_action('admin_post_wppb_remove_blocked_date', array($this, 'remove_blocked_date'));
        add_action('admin_post_wppb_save_settings',       array($this, 'save_settings'));
        add_action('admin_post_wppb_save_form',           array($this, 'save_form'));
        add_action('admin_post_wppb_delete_form',         array($this, 'delete_form'));
        add_action('admin_post_wppb_export_csv',          array($this, 'export_csv'));
        add_action('admin_post_wppb_set_entry_status',    array($this, 'set_entry_status'));

        // Lets Polylang offer the texts of every form for translation.
        add_action('admin_init', array('WPPB_Forms', 'register_strings'));
    }

    public function add_menu() {
        add_menu_page(
            'Photo Bookings',
            'Photo Bookings',
            'manage_options',
            'wppb-bookings',
            array($this, 'page_bookings'),
            'dashicons-calendar-alt',
            30
        );
        add_submenu_page(
            'wppb-bookings',
            'All Bookings',
            'All Bookings',
            'manage_options',
            'wppb-bookings',
            array($this, 'page_bookings')
        );
        $new_messages = WPPB_Booking::count_by_status('contact')['new'];
        add_submenu_page(
            'wppb-bookings',
            'Messages',
            'Messages' . ($new_messages ? ' <span class="awaiting-mod">' . intval($new_messages) . '</span>' : ''),
            'manage_options',
            'wppb-messages',
            array($this, 'page_messages')
        );
        add_submenu_page(
            'wppb-bookings',
            'Calendar',
            'Calendar',
            'manage_options',
            'wppb-calendar',
            array($this, 'page_calendar')
        );
        add_submenu_page(
            'wppb-bookings',
            'Settings',
            'Settings',
            'manage_options',
            'wppb-settings',
            array($this, 'page_settings')
        );
        add_submenu_page(
            'wppb-bookings',
            'Forms',
            'Forms',
            'manage_options',
            'wppb-forms',
            array($this, 'page_forms')
        );
        add_submenu_page(
            'wppb-bookings',
            'Add New Form',
            'Add New Form',
            'manage_options',
            'wppb-form-edit',
            array($this, 'page_form_edit')
        );
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'wppb') === false) return;
        wp_enqueue_style('wppb-jquery-ui', WPPB_URL . 'assets/css/jquery-ui.min.css', array(), '1.13.2');
        wp_enqueue_style('wppb-admin', WPPB_URL . 'assets/css/admin.css', array(), WPPB_VERSION);
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('wppb-admin', WPPB_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-datepicker'), WPPB_VERSION, true);

        if (strpos($hook, 'wppb-form-edit') !== false) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wppb-form-builder', WPPB_URL . 'assets/js/form-builder.js', array('jquery', 'jquery-ui-sortable', 'wp-color-picker'), WPPB_VERSION, true);
            wp_localize_script('wppb-form-builder', 'wppb_builder', array(
                'types'      => array(
                    WPPB_Forms::TYPE_CONTACT => WPPB_Forms::field_types(WPPB_Forms::TYPE_CONTACT),
                    WPPB_Forms::TYPE_BOOKING => WPPB_Forms::field_types(WPPB_Forms::TYPE_BOOKING),
                    WPPB_Forms::TYPE_CUSTOM  => WPPB_Forms::field_types(WPPB_Forms::TYPE_CUSTOM),
                ),
                'roles'      => WPPB_Forms::roles(),
                'role_types' => WPPB_Forms::role_capable_types(),
            ));
        }
    }

    // -----------------------------------------------
    // Pages
    // -----------------------------------------------

    public function page_bookings() {
        $this->page_entries('booking');
    }

    public function page_messages() {
        $this->page_entries('contact');
    }

    /** Admin page slug that lists entries of a type. */
    private static function entries_page($entry_type) {
        return $entry_type === 'contact' ? 'wppb-messages' : 'wppb-bookings';
    }

    /**
     * List or single view of the entries of one type (bookings or messages).
     */
    private function page_entries($entry_type) {
        $page = self::entries_page($entry_type);

        // Single entry view
        if (!empty($_GET['action']) && $_GET['action'] === 'view' && !empty($_GET['id'])) {
            $booking = WPPB_Booking::get(intval($_GET['id']));
            if ($booking) {
                // Opening a new message marks it as read.
                if ($booking->entry_type === 'contact' && $booking->status === 'new') {
                    WPPB_Booking::set_contact_status($booking->id, 'read');
                    $booking = WPPB_Booking::get($booking->id);
                }
                $is_contact = $booking->entry_type === 'contact';
                $page       = self::entries_page($booking->entry_type);
                $form       = WPPB_Forms::get($booking->form_id);
                include WPPB_DIR . 'admin/views/booking-view.php';
                return;
            }
        }

        // List
        $is_contact    = $entry_type === 'contact';
        $statuses      = WPPB_Booking::statuses($entry_type);
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        if (!in_array($status_filter, $statuses, true)) $status_filter = '';
        $search        = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $form_filter   = intval($_GET['form_id'] ?? 0);

        $filters = array('entry_type' => $entry_type);
        if ($status_filter) $filters['status']  = $status_filter;
        if ($search)        $filters['search']  = $search;
        if ($form_filter)   $filters['form_id'] = $form_filter;

        $bookings = WPPB_Booking::get_all($filters);
        $counts   = WPPB_Booking::count_by_status($entry_type, $form_filter);

        // Names of all forms for the list, and the forms offered in the filter:
        // those that currently produce this kind of entry. A custom form can
        // have produced both kinds over time, so it is always offered.
        $form_names = array();
        $forms      = array();
        foreach (WPPB_Forms::get_all() as $f) {
            $form_names[$f->id] = $f->name;
            if ($f->form_type === WPPB_Forms::TYPE_CUSTOM || WPPB_Forms::creates_bookings($f) !== $is_contact) {
                $forms[$f->id] = $f->name;
            }
        }

        include WPPB_DIR . 'admin/views/booking-list.php';
    }

    public function page_calendar() {
        include WPPB_DIR . 'admin/views/calendar.php';
    }

    public function page_settings() {
        $settings      = WPPB_Settings::get_all();
        $blocked_dates = WPPB_Booking::get_blocked_dates();
        include WPPB_DIR . 'admin/views/settings.php';
    }

    public function page_forms() {
        $forms = WPPB_Forms::get_all();
        include WPPB_DIR . 'admin/views/form-list.php';
    }

    public function page_form_edit() {
        $id   = intval($_GET['id'] ?? 0);
        $form = $id ? WPPB_Forms::get($id) : null;

        if ($form) {
            $form_type = $form->form_type;
            $fields    = WPPB_Forms::get_fields($form);
        } else {
            $form_type = WPPB_Forms::valid_type(sanitize_key($_GET['type'] ?? ''));
            $fields    = array();
            if ($form_type === WPPB_Forms::TYPE_BOOKING) $fields = WPPB_Forms::default_booking_fields();
            if ($form_type === WPPB_Forms::TYPE_CONTACT) $fields = WPPB_Forms::default_contact_fields();
        }
        $form_settings   = $form ? WPPB_Forms::get_settings($form) : WPPB_Forms::default_settings($form_type);
        $is_booking_form = $form ? WPPB_Forms::creates_bookings($form) : $form_type === WPPB_Forms::TYPE_BOOKING;
        $email_defaults  = WPPB_Email::default_templates($is_booking_form);

        include WPPB_DIR . 'admin/views/form-edit.php';
    }

    // -----------------------------------------------
    // Actions
    // -----------------------------------------------

    /** Booking targeted by a confirm / cancel request, or null. */
    private static function posted_booking() {
        $booking = WPPB_Booking::get(intval($_POST['booking_id'] ?? 0));
        return ($booking && $booking->entry_type === 'booking') ? $booking : null;
    }

    public function confirm_booking() {
        $this->verify_action('wppb_confirm_booking');
        $booking = self::posted_booking();
        if (!$booking) {
            wp_redirect(admin_url('admin.php?page=wppb-bookings'));
            exit;
        }
        WPPB_Booking::confirm($booking->id);
        WPPB_Email::send_booking_confirmed($booking->id);
        wp_redirect(admin_url('admin.php?page=wppb-bookings&action=view&id=' . $booking->id . '&notice=confirmed'));
        exit;
    }

    public function cancel_booking() {
        $this->verify_action('wppb_cancel_booking');
        $booking = self::posted_booking();
        if (!$booking) {
            wp_redirect(admin_url('admin.php?page=wppb-bookings'));
            exit;
        }
        WPPB_Booking::cancel($booking->id);
        WPPB_Email::send_booking_cancelled($booking->id);
        wp_redirect(admin_url('admin.php?page=wppb-bookings&action=view&id=' . $booking->id . '&notice=cancelled'));
        exit;
    }

    /** Mark a message as new, read or archived. */
    public function set_entry_status() {
        $this->verify_action('wppb_set_entry_status');
        $id     = intval($_POST['booking_id'] ?? 0);
        $status = sanitize_key($_POST['status'] ?? '');
        WPPB_Booking::set_contact_status($id, $status);

        // Marking as new from the detail view would be undone by reopening it.
        $url = admin_url('admin.php?page=wppb-messages&notice=status_updated');
        if ($status !== 'new' && !empty($_POST['return_to_view'])) {
            $url = admin_url('admin.php?page=wppb-messages&action=view&id=' . $id . '&notice=status_updated');
        }
        wp_redirect($url);
        exit;
    }

    public function delete_booking() {
        $this->verify_action('wppb_delete_booking');
        $id    = intval($_POST['booking_id'] ?? 0);
        $entry = $id ? WPPB_Booking::get($id) : null;
        $page  = self::entries_page($entry ? $entry->entry_type : 'booking');
        if ($entry) {
            WPPB_Booking::delete($id);
        }
        wp_redirect(admin_url('admin.php?page=' . $page . '&notice=deleted'));
        exit;
    }

    public function save_notes() {
        $this->verify_action('wppb_save_notes');
        $id = intval($_POST['booking_id'] ?? 0);
        $entry = $id ? WPPB_Booking::get($id) : null;
        if ($entry) {
            $notes = sanitize_textarea_field(wp_unslash($_POST['admin_notes'] ?? ''));
            WPPB_Booking::update($id, array('admin_notes' => $notes));
            WPPB_Booking::log_activity($id, 'notes_saved', 'Admin notes updated');
        }
        $page = self::entries_page($entry ? $entry->entry_type : 'booking');
        wp_redirect(admin_url('admin.php?page=' . $page . '&action=view&id=' . $id . '&notice=notes_saved'));
        exit;
    }

    public function add_blocked_date() {
        $this->verify_action('wppb_add_blocked_date');
        $date   = sanitize_text_field($_POST['blocked_date'] ?? '');
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            WPPB_Booking::add_blocked_date($date, $reason);
        }
        wp_redirect(admin_url('admin.php?page=wppb-settings&tab=blocked&notice=date_added'));
        exit;
    }

    public function remove_blocked_date() {
        $this->verify_action('wppb_remove_blocked_date');
        $id = intval($_POST['blocked_date_id'] ?? 0);
        if ($id) {
            WPPB_Booking::remove_blocked_date($id);
        }
        wp_redirect(admin_url('admin.php?page=wppb-settings&tab=blocked&notice=date_removed'));
        exit;
    }

    public function save_settings() {
        $this->verify_action('wppb_save_settings');

        $tab  = sanitize_text_field($_POST['wppb_tab'] ?? 'general');
        $data = array();

        if ($tab === 'general') {
            $working_days = (isset($_POST['working_days']) && is_array($_POST['working_days']))
                ? array_map('intval', $_POST['working_days'])
                : array();

            $slots_raw = sanitize_textarea_field($_POST['time_slots'] ?? '');
            $slots     = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $slots_raw))));
            $slots = array_values(array_filter($slots, function ($s) {
                return preg_match('/^\d{2}:\d{2}$/', $s);
            }));
            sort($slots);

            $data = array(
                'photographer_email' => implode(', ', WPPB_Settings::parse_emails(wp_unslash($_POST['photographer_email'] ?? ''))),
                'working_days'       => $working_days,
                'time_slots'         => $slots,
                'min_days_ahead'     => max(0, intval($_POST['min_days_ahead'] ?? 1)),
                'recaptcha_site_key'    => sanitize_text_field($_POST['recaptcha_site_key'] ?? ''),
                'recaptcha_secret_key'  => sanitize_text_field($_POST['recaptcha_secret_key'] ?? ''),
                'rate_limit'            => min(1000, max(0, intval($_POST['rate_limit'] ?? 5))),
                'retention_messages_days' => min(36500, max(0, intval($_POST['retention_messages_days'] ?? 0))),
                'retention_bookings_days' => min(36500, max(0, intval($_POST['retention_bookings_days'] ?? 0))),
            );
        } elseif ($tab === 'emails') {
            $data = array(
                'email_confirmed_client_subject' => sanitize_text_field($_POST['email_confirmed_client_subject'] ?? ''),
                'email_confirmed_client_body'    => sanitize_textarea_field($_POST['email_confirmed_client_body'] ?? ''),
                'email_confirmed_admin_subject'  => sanitize_text_field($_POST['email_confirmed_admin_subject'] ?? ''),
                'email_confirmed_admin_body'     => sanitize_textarea_field($_POST['email_confirmed_admin_body'] ?? ''),
                'email_cancelled_client_subject' => sanitize_text_field($_POST['email_cancelled_client_subject'] ?? ''),
                'email_cancelled_client_body'    => sanitize_textarea_field($_POST['email_cancelled_client_body'] ?? ''),
                'email_cancelled_admin_subject'  => sanitize_text_field($_POST['email_cancelled_admin_subject'] ?? ''),
                'email_cancelled_admin_body'     => sanitize_textarea_field($_POST['email_cancelled_admin_body'] ?? ''),
            );
        } elseif ($tab === 'automation') {
            $data = array(
                'auto_session_reminder'                => !empty($_POST['auto_session_reminder']) ? '1' : '0',
                'auto_session_reminder_days'           => max(1, intval($_POST['auto_session_reminder_days'] ?? 1)),
                'auto_session_reminder_client_subject' => sanitize_text_field($_POST['auto_session_reminder_client_subject'] ?? ''),
                'auto_session_reminder_client_body'    => sanitize_textarea_field($_POST['auto_session_reminder_client_body'] ?? ''),
                'auto_session_reminder_admin_subject'  => sanitize_text_field($_POST['auto_session_reminder_admin_subject'] ?? ''),
                'auto_session_reminder_admin_body'     => sanitize_textarea_field($_POST['auto_session_reminder_admin_body'] ?? ''),
                'auto_pending_followup'                => !empty($_POST['auto_pending_followup']) ? '1' : '0',
                'auto_pending_followup_days'           => max(1, intval($_POST['auto_pending_followup_days'] ?? 3)),
                'auto_pending_followup_subject'        => sanitize_text_field($_POST['auto_pending_followup_subject'] ?? ''),
                'auto_pending_followup_body'           => sanitize_textarea_field($_POST['auto_pending_followup_body'] ?? ''),
                'questionnaire_enabled'                => !empty($_POST['questionnaire_enabled']) ? '1' : '0',
                'questionnaire_form_id'                => intval($_POST['questionnaire_form_id'] ?? 0),
                'questionnaire_days_before'            => max(1, intval($_POST['questionnaire_days_before'] ?? 3)),
                'questionnaire_email_subject'          => sanitize_text_field($_POST['questionnaire_email_subject'] ?? ''),
                'questionnaire_email_body'             => sanitize_textarea_field($_POST['questionnaire_email_body'] ?? ''),
            );
        }

        if (!empty($data)) {
            WPPB_Settings::save($data);
        }

        wp_redirect(admin_url('admin.php?page=wppb-settings&tab=' . $tab . '&notice=saved'));
        exit;
    }

    public function save_form() {
        $this->verify_action('wppb_save_form');

        $id       = intval($_POST['form_id'] ?? 0);
        $existing = $id ? WPPB_Forms::get($id) : null;
        if ($id && !$existing) {
            wp_die('Form not found.');
        }

        // The type is chosen when the form is created and never changes afterwards.
        $form_type = $existing
            ? $existing->form_type
            : WPPB_Forms::valid_type(sanitize_key($_POST['form_type'] ?? ''));

        $fields = json_decode(wp_unslash($_POST['fields_json'] ?? '[]'), true);
        $fields = WPPB_Forms::sanitize_fields(is_array($fields) ? $fields : array(), $form_type);

        $text = function ($key) {
            return sanitize_text_field(wp_unslash($_POST[$key] ?? ''));
        };
        // Email bodies may be HTML templates.
        $body = function ($key) {
            return trim(wp_kses_post(wp_unslash($_POST[$key] ?? '')));
        };

        $new_id = WPPB_Forms::save(array(
            'name'            => $text('form_name'),
            'form_type'       => $form_type,
            'fields'          => $fields,
            'notify_email'    => sanitize_text_field(wp_unslash($_POST['notify_email'] ?? '')),
            'email_subject'   => $text('email_subject'),
            'success_message' => sanitize_textarea_field(wp_unslash($_POST['success_message'] ?? '')),
            'settings'        => array(
                'submit_label'   => $text('submit_label'),
                'recaptcha'      => !empty($_POST['recaptcha']) ? '1' : '0',
                'field_height'   => self::optional_px($_POST['field_height'] ?? '', 24, 120),
                'field_gap'      => self::optional_px($_POST['field_gap'] ?? '', 0, 80),
                'button_style'   => in_array($_POST['button_style'] ?? '', array('outline', 'filled', 'theme'), true) ? $_POST['button_style'] : 'outline',
                'button_color'   => (string) sanitize_hex_color(is_string($_POST['button_color'] ?? null) ? trim($_POST['button_color']) : ''),
                'button_align'   => in_array($_POST['button_align'] ?? '', array('left', 'center', 'right'), true) ? $_POST['button_align'] : 'left',
                'button_full'    => !empty($_POST['button_full']) ? '1' : '0',
                'admin_body'     => $body('admin_body'),
                'client_enabled' => !empty($_POST['client_enabled']) ? '1' : '0',
                'client_subject' => $text('client_subject'),
                'client_body'    => $body('client_body'),
            ),
        ), $id);

        if (!$new_id) {
            wp_die('The form could not be saved.');
        }

        wp_redirect(admin_url('admin.php?page=wppb-form-edit&id=' . $new_id . '&notice=saved'));
        exit;
    }

    public function delete_form() {
        $this->verify_action('wppb_delete_form');
        $id = intval($_POST['form_id'] ?? 0);
        if ($id) WPPB_Forms::delete($id);
        wp_redirect(admin_url('admin.php?page=wppb-forms&notice=deleted'));
        exit;
    }

    public function export_csv() {
        if (!current_user_can('manage_options') ||
            !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'wppb_export_csv')) {
            wp_die('Unauthorized.');
        }

        $entry_type    = (($_GET['entry_type'] ?? '') === 'contact') ? 'contact' : 'booking';
        $is_contact    = $entry_type === 'contact';
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $form_filter   = intval($_GET['form_id'] ?? 0);

        $filters = array('entry_type' => $entry_type);
        if ($status_filter) $filters['status']  = $status_filter;
        if ($form_filter)   $filters['form_id'] = $form_filter;
        $bookings = WPPB_Booking::get_all($filters);

        // One column per extra field label, across all exported entries.
        $extra_labels = array();
        $extras       = array();
        foreach ($bookings as $b) {
            $extras[$b->id] = array();
            foreach (WPPB_Booking::get_extra_items($b) as $item) {
                $extras[$b->id][$item['label']] = $item['value'];
                $extra_labels[$item['label']]   = true;
            }
        }
        $extra_labels = array_keys($extra_labels);

        $form_names = array();
        foreach (WPPB_Forms::get_all() as $f) {
            $form_names[$f->id] = $f->name;
        }

        $filename = ($is_contact ? 'messages' : 'bookings') . ($status_filter ? '-' . sanitize_key($status_filter) : '') . '-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

        $header = array('ID', 'Form', 'First Name', 'Last Name', 'Email', 'Phone', 'Type');
        if (!$is_contact) {
            $header[] = 'Date';
            $header[] = 'Time';
        }
        fputcsv($out, self::csv_safe(array_merge($header, array('Status', 'Message', 'Submitted'), $extra_labels)));

        foreach ($bookings as $b) {
            $row = array(
                $b->id,
                $form_names[$b->form_id] ?? '',
                $b->first_name,
                $b->last_name,
                $b->email,
                $b->phone,
                $b->photography_type,
            );
            if (!$is_contact) {
                $row[] = $b->booking_date;
                $row[] = $b->time_slot;
            }
            $row[] = $b->status;
            $row[] = $b->message;
            $row[] = $b->created_at;
            foreach ($extra_labels as $label) {
                $row[] = $extras[$b->id][$label] ?? '';
            }
            fputcsv($out, self::csv_safe($row));
        }
        fclose($out);
        exit;
    }

    /** A pixel value within bounds, or '' when the field was left empty. */
    private static function optional_px($value, $min, $max) {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || !is_numeric($value)) return '';
        return (string) min($max, max($min, intval($value)));
    }

    /**
     * Visitors control these values: neutralise cells that a spreadsheet
     * would otherwise run as a formula.
     */
    private static function csv_safe($row) {
        return array_map(function ($cell) {
            $cell = (string) $cell;
            if ($cell === '' || strpbrk($cell[0], "=+-@\t\r") === false) return $cell;
            // Phone numbers and signed numbers are harmless and stay readable.
            if (preg_match('/^[+-][\d\s().\/-]*$/', $cell)) return $cell;
            return "'" . $cell;
        }, $row);
    }

    // -----------------------------------------------
    // Helpers
    // -----------------------------------------------

    private function verify_action($action) {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized.');
        }
        check_admin_referer($action);
    }
}
