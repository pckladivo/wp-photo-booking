<?php
if (!defined('ABSPATH')) die('Forbidden');

class WPPB_Settings {
    private static $option_key = 'wppb_settings';

    private static $defaults = array(
        // General
        'photographer_email'             => '',
        'working_days'                   => array(1, 2, 3, 4, 5),
        'photography_types'              => array('Portrait', 'Wedding', 'Corporate', 'Family', 'Event'),
        'min_days_ahead'                 => 1,
        // Time slots (empty = date-only mode, one booking per day)
        'time_slots'                     => array(),
        // Spam protection
        'recaptcha_site_key'             => '',
        'recaptcha_secret_key'           => '',
        // Submissions accepted per visitor address in 10 minutes (0 = unlimited)
        'rate_limit'                     => 5,
        // Data retention in days (0 = keep until deleted by hand)
        'retention_messages_days'        => 0,
        'retention_bookings_days'        => 0,
        // Transactional emails
        'email_confirmed_client_subject' => 'Your booking is confirmed! — {site_name}',
        'email_confirmed_client_body'    => "Hi {first_name},\n\nGreat news! Your photo session has been confirmed.\n\nDetails:\nDate: {booking_date}\nType: {photography_type}\n\nLooking forward to seeing you!\n\nBest regards,\n{site_name}",
        'email_confirmed_admin_subject'  => 'Booking confirmed: {first_name} {last_name} on {booking_date}',
        'email_confirmed_admin_body'     => "Booking confirmed:\n\nName: {first_name} {last_name}\nEmail: {client_email}\nPhone: {phone}\nType: {photography_type}\nDate: {booking_date}",
        'email_cancelled_client_subject' => 'Your booking has been cancelled — {site_name}',
        'email_cancelled_client_body'    => "Hi {first_name},\n\nYour booking for {booking_date} ({photography_type}) has been cancelled.\n\nPlease contact us if you have any questions.\n\nBest regards,\n{site_name}",
        'email_cancelled_admin_subject'  => 'Booking cancelled: {first_name} {last_name}',
        'email_cancelled_admin_body'     => "Booking cancelled:\n\nName: {first_name} {last_name}\nEmail: {client_email}\nType: {photography_type}\nDate: {booking_date}",
        // Automated emails (cron)
        'auto_session_reminder'                  => '0',
        'auto_session_reminder_days'             => '1',
        'auto_session_reminder_client_subject'   => 'Your photo session is tomorrow! — {site_name}',
        'auto_session_reminder_client_body'      => "Hi {first_name},\n\nJust a friendly reminder that your photo session is scheduled for tomorrow, {booking_date}.\n\nSession type: {photography_type}\n\nIf you have any questions or need to make changes, please contact us as soon as possible.\n\nLooking forward to seeing you!\n\n{site_name}",
        'auto_session_reminder_admin_subject'    => 'Session tomorrow: {first_name} {last_name} ({photography_type})',
        'auto_session_reminder_admin_body'       => "Reminder: you have a session scheduled for tomorrow.\n\nClient: {first_name} {last_name}\nEmail: {client_email}\nPhone: {phone}\nType: {photography_type}\nDate: {booking_date}",
        'auto_pending_followup'                  => '0',
        'auto_pending_followup_days'             => '3',
        'auto_pending_followup_subject'          => 'Your booking request is being reviewed — {site_name}',
        'auto_pending_followup_body'             => "Hi {first_name},\n\nWe wanted to let you know that we received your booking request for {booking_date} ({photography_type}) and we are reviewing it.\n\nWe will be in touch very soon to confirm your session.\n\nThank you for your patience!\n\n{site_name}",
        // Pre-session questionnaire
        'questionnaire_enabled'                  => '0',
        'questionnaire_form_id'                  => '0',
        'questionnaire_days_before'              => '3',
        'questionnaire_email_subject'            => 'Please fill out your session questionnaire — {site_name}',
        'questionnaire_email_body'               => "Hi {first_name},\n\nYour photo session is coming up on {booking_date}!\n\nTo help us prepare the best experience for you, please take a moment to fill out a short questionnaire:\n\n{questionnaire_link}\n\nThank you!\n\n{site_name}",
    );

    public static function get($key, $default = null) {
        $settings = self::get_all();
        if (isset($settings[$key])) return $settings[$key];
        if ($default !== null) return $default;
        return isset(self::$defaults[$key]) ? self::$defaults[$key] : null;
    }

    public static function get_all() {
        $saved = get_option(self::$option_key, array());
        return array_merge(self::$defaults, (array) $saved);
    }

    public static function save($data) {
        $current = self::get_all();
        $allowed = array_keys(self::$defaults);
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $current[$key] = $value;
            }
        }
        update_option(self::$option_key, $current);
    }

    const MAX_RECIPIENTS = 5;

    /**
     * Turn "a@x.com, b@y.com" into a list of valid, distinct addresses.
     * Separators: comma, semicolon, space or new line.
     *
     * @param int $max_length Longest allowed "a, b" string (0 = no limit).
     */
    public static function parse_emails($raw, $max_length = 0) {
        $emails = array();
        foreach (preg_split('/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            $email = sanitize_email($part);
            if ($email === '' || !is_email($email)) continue;
            if (in_array(strtolower($email), array_map('strtolower', $emails), true)) continue;
            if ($max_length && strlen(implode(', ', array_merge($emails, array($email)))) > $max_length) break;
            $emails[] = $email;
            if (count($emails) >= self::MAX_RECIPIENTS) break;
        }
        return $emails;
    }

    /** All notification addresses: the first is the main one, the others get a blind copy. */
    public static function get_photographer_emails() {
        $emails = self::parse_emails(self::get('photographer_email'));
        return $emails ? $emails : array(get_option('admin_email'));
    }

    /** The main address: used as sender and as the visible recipient. */
    public static function get_photographer_email() {
        $emails = self::get_photographer_emails();
        return $emails[0];
    }

    public static function get_working_days() {
        $days = self::get('working_days');
        return is_array($days) ? array_map('intval', $days) : array(1, 2, 3, 4, 5);
    }

    public static function get_photography_types() {
        $types = self::get('photography_types');
        return is_array($types) ? array_filter($types) : array('Portrait', 'Wedding', 'Corporate', 'Family', 'Event');
    }

    public static function has_recaptcha() {
        return !empty(self::get('recaptcha_site_key')) && !empty(self::get('recaptcha_secret_key'));
    }

    public static function get_time_slots() {
        $slots = self::get('time_slots');
        return is_array($slots) ? array_filter($slots) : array();
    }

    public static function has_time_slots() {
        return !empty(self::get_time_slots());
    }
}
