<?php
if (!defined('ABSPATH')) die('Forbidden');

class WPPB_Database {

    /**
     * Bring the schema and data up to the current plugin version.
     * Safe to run repeatedly: every step checks whether it is still needed.
     */
    public static function upgrade() {
        self::create_tables();
        self::make_booking_date_nullable();
        self::assign_roles_to_legacy_forms();
        self::retire_global_booking_templates();
        self::clear_stored_ips();
        update_option('wppb_db_version', WPPB_VERSION);
    }

    /**
     * Contact entries have no date. dbDelta does not change NULL-ability
     * of an existing column, so pre-1.7 installs need an explicit ALTER.
     */
    private static function make_booking_date_nullable() {
        global $wpdb;
        $col = $wpdb->get_row("SHOW COLUMNS FROM " . WPPB_TABLE_BOOKINGS . " LIKE 'booking_date'");
        if ($col && $col->Null === 'NO') {
            $wpdb->query("ALTER TABLE " . WPPB_TABLE_BOOKINGS . " MODIFY booking_date date DEFAULT NULL");
        }
    }

    /**
     * Until 1.9 new-booking emails had global templates in Settings and one
     * protected booking form behind [photo_booking]. Both are gone: every form
     * carries its own templates. Customised global texts move into the booking
     * forms that still relied on them.
     */
    private static function retire_global_booking_templates() {
        delete_option('wppb_default_booking_form_id');

        $saved = get_option('wppb_settings', array());
        if (!is_array($saved)) return;

        $old = array();
        foreach (array('client_subject', 'client_body', 'admin_subject', 'admin_body') as $key) {
            if (isset($saved['email_new_' . $key])) {
                $old[$key] = (string) $saved['email_new_' . $key];
                unset($saved['email_new_' . $key]);
            }
        }
        if (!$old) return;

        // Texts shipped as defaults before 1.10: not a customisation, nothing to keep.
        $shipped = array(
            'client_subject' => 'Your booking request has been received — {site_name}',
            'client_body'    => "Hi {first_name},\n\nThank you for your booking request!\n\nDetails:\nDate: {booking_date}\nType: {photography_type}\n\nWe will review your request and confirm it shortly.\n\nBest regards,\n{site_name}",
            'admin_subject'  => 'New booking request from {first_name} {last_name}',
            'admin_body'     => "New booking request received:\n\nName: {first_name} {last_name}\nEmail: {client_email}\nPhone: {phone}\nType: {photography_type}\nDate: {booking_date}\nMessage: {message}",
        );
        $norm = function ($text) { return trim(str_replace("\r\n", "\n", $text)); };
        foreach ($old as $key => $text) {
            if ($norm($text) === '' || $norm($text) === $norm($shipped[$key])) unset($old[$key]);
        }

        if ($old) {
            foreach (WPPB_Forms::get_all() as $form) {
                if (!WPPB_Forms::creates_bookings($form)) continue;
                $settings = WPPB_Forms::get_settings($form);
                $subject  = $form->email_subject;
                $changed  = false;

                foreach (array('client_subject', 'client_body', 'admin_body') as $key) {
                    if (isset($old[$key]) && $settings[$key] === '') {
                        $settings[$key] = $old[$key];
                        $changed = true;
                    }
                }
                if (isset($old['admin_subject']) && $subject === '') {
                    $subject = $old['admin_subject'];
                    $changed = true;
                }
                if (!$changed) continue;

                WPPB_Forms::save(array(
                    'name'            => $form->name,
                    'fields'          => WPPB_Forms::get_fields($form),
                    'notify_email'    => $form->notify_email,
                    'email_subject'   => $subject,
                    'success_message' => $form->success_message,
                    'settings'        => $settings,
                ), $form->id);
            }
        }

        update_option('wppb_settings', $saved);
    }

    /**
     * Forms built before 1.7 do not say what their fields mean. Guess the
     * obvious ones so their entries get a name, an email to reply to and a message.
     */
    private static function assign_roles_to_legacy_forms() {
        foreach (WPPB_Forms::get_all() as $form) {
            $fields = WPPB_Forms::get_fields($form);
            if (empty($fields)) continue;

            foreach ($fields as $field) {
                if (array_key_exists('role', $field)) continue 2; // saved by 1.7+
            }

            $used = array();
            foreach ($fields as &$field) {
                $type  = $field['type'] ?? 'text';
                $label = strtolower($field['label'] ?? '');
                $role  = '';

                if ($type === 'email') {
                    $role = 'email';
                } elseif ($type === 'tel') {
                    $role = 'phone';
                } elseif ($type === 'textarea') {
                    $role = 'message';
                } elseif ($type === 'text' && preg_match('/\b(last name|surname|priimek)\b/', $label)) {
                    $role = 'last_name';
                } elseif ($type === 'text' && preg_match('/\b(name|ime)\b/', $label)) {
                    $role = 'first_name';
                }

                if ($role !== '' && isset($used[$role])) $role = '';
                if ($role !== '') $used[$role] = true;

                $field['role']  = $role;
                $field['width'] = 'full';
            }
            unset($field);

            global $wpdb;
            $wpdb->update(WPPB_TABLE_FORMS, array('fields' => wp_json_encode($fields)), array('id' => $form->id));
        }
    }

    /**
     * Earlier versions recorded the visitor's IP address with every activity
     * entry although it was never used. Remove what was collected.
     */
    private static function clear_stored_ips() {
        global $wpdb;
        $wpdb->query("UPDATE " . WPPB_TABLE_ACTIVITY . " SET ip = '' WHERE ip <> ''");
    }

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $bookings_sql = "CREATE TABLE " . WPPB_TABLE_BOOKINGS . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(200) NOT NULL,
            phone varchar(50) NOT NULL,
            photography_type varchar(100) NOT NULL,
            form_id bigint(20) NOT NULL DEFAULT 0,
            entry_type varchar(20) NOT NULL DEFAULT 'booking',
            booking_date date DEFAULT NULL,
            time_slot varchar(10) NOT NULL DEFAULT '',
            message text,
            status varchar(20) NOT NULL DEFAULT 'pending',
            admin_notes text,
            booking_lang varchar(10) NOT NULL DEFAULT '',
            questionnaire_token varchar(64) NOT NULL DEFAULT '',
            questionnaire_data longtext,
            questionnaire_completed_at datetime DEFAULT NULL,
            custom_data longtext,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY booking_date (booking_date),
            KEY entry_type (entry_type),
            KEY form_id (form_id),
            KEY questionnaire_token (questionnaire_token)
        ) $charset;";

        $blocked_sql = "CREATE TABLE " . WPPB_TABLE_BLOCKED . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            blocked_date date NOT NULL,
            reason varchar(255),
            PRIMARY KEY (id),
            UNIQUE KEY blocked_date (blocked_date)
        ) $charset;";

        $forms_sql = "CREATE TABLE " . WPPB_TABLE_FORMS . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            form_type varchar(20) NOT NULL DEFAULT 'contact',
            fields longtext NOT NULL,
            notify_email varchar(200) NOT NULL DEFAULT '',
            email_subject varchar(500) NOT NULL DEFAULT '',
            success_message text NOT NULL,
            settings longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id)
        ) $charset;";

        $activity_sql = "CREATE TABLE " . WPPB_TABLE_ACTIVITY . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) NOT NULL,
            action varchar(100) NOT NULL,
            detail varchar(500) NOT NULL DEFAULT '',
            ip varchar(45) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY booking_id (booking_id)
        ) $charset;";

        $email_log_sql = "CREATE TABLE " . WPPB_TABLE_EMAIL_LOG . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) NOT NULL,
            email_type varchar(100) NOT NULL,
            sent_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY booking_email_type (booking_id, email_type)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($bookings_sql);
        dbDelta($blocked_sql);
        dbDelta($forms_sql);
        dbDelta($activity_sql);
        dbDelta($email_log_sql);
    }
}
