<?php
if (!defined('ABSPATH')) die('Forbidden');

class WPPB_Cron {

    const HOOK = 'wppb_daily_automation';

    public static function init() {
        add_action(self::HOOK, array(__CLASS__, 'process'));
        // Ensure cron is scheduled (covers upgrades where activation hook didn't run)
        if (!wp_next_scheduled(self::HOOK)) {
            self::schedule();
        }
    }

    public static function schedule() {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time(), 'daily', self::HOOK);
        }
    }

    public static function unschedule() {
        wp_clear_scheduled_hook(self::HOOK);
    }

    /**
     * Main processor — runs once daily via WP-Cron.
     */
    public static function process() {
        $bookings = WPPB_Booking::get_all();
        foreach ($bookings as $booking) {
            self::maybe_send_session_reminder($booking);
            self::maybe_send_pending_followup($booking);
            self::maybe_send_questionnaire($booking);
        }

        WPPB_Privacy::purge_expired();
    }

    /**
     * Session reminder: confirmed booking, session is N days away, send to both client and admin.
     */
    private static function maybe_send_session_reminder($booking) {
        if (WPPB_Settings::get('auto_session_reminder', '0') !== '1') return;
        if ($booking->status !== 'confirmed') return;
        if (WPPB_Booking::email_was_sent($booking->id, 'session_reminder')) return;

        $days           = max(1, intval(WPPB_Settings::get('auto_session_reminder_days', '1')));
        $session_time   = strtotime($booking->booking_date . ' midnight');
        $window_start   = $session_time - ($days * DAY_IN_SECONDS);
        $window_end     = $window_start + DAY_IN_SECONDS;

        // Only fire if we are within the 24-hour window before the session
        if (time() < $window_start || time() >= $window_end) return;

        $tokens = WPPB_Email::get_tokens($booking);

        // Client
        self::switch_lang($booking->booking_lang);
        WPPB_Email::send(
            $booking->email,
            WPPB_Settings::get('auto_session_reminder_client_subject'),
            WPPB_Settings::get('auto_session_reminder_client_body'),
            $tokens
        );
        self::restore_lang();

        // Admin
        WPPB_Email::send(
            WPPB_Settings::get_photographer_emails(),
            WPPB_Settings::get('auto_session_reminder_admin_subject'),
            WPPB_Settings::get('auto_session_reminder_admin_body'),
            $tokens
        );

        WPPB_Booking::mark_email_sent($booking->id, 'session_reminder');
        WPPB_Booking::log_activity($booking->id, 'auto_email', 'Session reminder sent to client and admin');
    }

    /**
     * Pending follow-up: booking still pending after N days — reassure the client.
     */
    private static function maybe_send_pending_followup($booking) {
        if (WPPB_Settings::get('auto_pending_followup', '0') !== '1') return;
        if ($booking->status !== 'pending') return;
        if (WPPB_Booking::email_was_sent($booking->id, 'pending_followup')) return;

        $days    = max(1, intval(WPPB_Settings::get('auto_pending_followup_days', '3')));
        $created = strtotime($booking->created_at);
        if ((time() - $created) < ($days * DAY_IN_SECONDS)) return;

        $tokens = WPPB_Email::get_tokens($booking);

        self::switch_lang($booking->booking_lang);
        WPPB_Email::send(
            $booking->email,
            WPPB_Settings::get('auto_pending_followup_subject'),
            WPPB_Settings::get('auto_pending_followup_body'),
            $tokens
        );
        self::restore_lang();

        WPPB_Booking::mark_email_sent($booking->id, 'pending_followup');
        WPPB_Booking::log_activity($booking->id, 'auto_email', 'Pending follow-up sent to client');
    }

    /**
     * Pre-session questionnaire: confirmed booking, N days before session,
     * questionnaire not yet sent and not yet completed.
     */
    private static function maybe_send_questionnaire($booking) {
        if (WPPB_Settings::get('questionnaire_enabled', '0') !== '1') return;
        if ($booking->status !== 'confirmed') return;
        if (!empty($booking->questionnaire_completed_at)) return;
        if (WPPB_Booking::email_was_sent($booking->id, 'questionnaire')) return;

        $form_id = intval(WPPB_Settings::get('questionnaire_form_id', 0));
        if (!$form_id) return;

        $days         = max(1, intval(WPPB_Settings::get('questionnaire_days_before', '3')));
        $session_time = strtotime($booking->booking_date . ' midnight');
        $trigger_time = $session_time - ($days * DAY_IN_SECONDS);

        if (time() < $trigger_time) return;
        if (time() > $session_time) return; // don't send after the session

        WPPB_Email::send_questionnaire($booking->id);
        WPPB_Booking::mark_email_sent($booking->id, 'questionnaire');
    }

    // -----------------------------------------------
    // Language helpers (Polylang)
    // -----------------------------------------------

    private static $original_lang = null;

    private static function switch_lang($lang) {
        if (!$lang || !function_exists('pll_switch_language')) return;
        self::$original_lang = function_exists('pll_current_language') ? pll_current_language() : null;
        pll_switch_language($lang);
    }

    private static function restore_lang() {
        if (self::$original_lang && function_exists('pll_switch_language')) {
            pll_switch_language(self::$original_lang);
        }
        self::$original_lang = null;
    }
}

WPPB_Cron::init();
