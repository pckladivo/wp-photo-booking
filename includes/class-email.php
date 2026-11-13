<?php
if (!defined('ABSPATH')) die('Forbidden');

class WPPB_Email {

    /**
     * Emails for a fresh submission: a notification to the photographer and,
     * if enabled for the form, an acknowledgement to the client.
     *
     * A template left empty in the form falls back to default_templates().
     */
    /**
     * Built-in templates for a new submission, used where the form leaves a
     * template empty. Shown as grey example text in the form editor.
     */
    public static function default_templates($is_booking) {
        if ($is_booking) {
            return array(
                'client_subject' => __('Your booking request has been received — {site_name}', 'wp-photo-booking'),
                'client_body'    => __("Hi {first_name},\n\nThank you for your booking request!\n\nDate: {booking_date}\n\nWe will review your request and confirm it shortly.\n\nBest regards,\n{site_name}", 'wp-photo-booking'),
                'admin_subject'  => '[{site_name}] New booking request: {first_name} {last_name}',
                'admin_body'     => "New booking request from the form \"{form_name}\":\n\n{all_fields}",
            );
        }
        return array(
            'client_subject' => __('We have received your message — {site_name}', 'wp-photo-booking'),
            'client_body'    => __("Hi {first_name},\n\nThank you for your message. We will get back to you as soon as possible.\n\n{site_name}", 'wp-photo-booking'),
            'admin_subject'  => '[{site_name}] New submission: {form_name}',
            'admin_body'     => "New submission from the form \"{form_name}\":\n\n{all_fields}",
        );
    }

    public static function send_new_entry($entry_id) {
        $entry = WPPB_Booking::get($entry_id);
        if (!$entry) return;

        $form       = WPPB_Forms::get($entry->form_id);
        $settings   = WPPB_Forms::get_settings($form);
        $is_booking = $entry->entry_type === 'booking';
        $tokens     = self::get_tokens($entry, $form);

        $defaults = self::default_templates($is_booking);

        $sent_to = array();

        // Client — in the language they submitted in
        if ($settings['client_enabled'] === '1' && is_email($entry->email)) {
            self::switch_lang($entry->booking_lang);
            self::send(
                $entry->email,
                $settings['client_subject'] !== '' ? WPPB_Forms::t($settings['client_subject']) : $defaults['client_subject'],
                $settings['client_body'] !== '' ? WPPB_Forms::t($settings['client_body']) : $defaults['client_body'],
                $tokens
            );
            self::restore_lang();
            $sent_to[] = 'client';
        }

        // Photographer — replying goes straight to the client
        $admin_to = $form ? WPPB_Settings::parse_emails($form->notify_email) : array();
        if (!$admin_to) $admin_to = WPPB_Settings::get_photographer_emails();
        self::send(
            $admin_to,
            ($form && $form->email_subject !== '') ? $form->email_subject : $defaults['admin_subject'],
            $settings['admin_body'] !== '' ? $settings['admin_body'] : $defaults['admin_body'],
            $tokens,
            $entry->email
        );
        $sent_to[] = 'admin';

        WPPB_Booking::log_activity(
            $entry_id,
            'email_sent',
            ($is_booking ? 'New booking' : 'New message') . ' emails sent to ' . implode(' and ', $sent_to)
        );
    }

    public static function send_booking_confirmed($booking_id) {
        $booking = WPPB_Booking::get($booking_id);
        if (!$booking) return;

        $tokens = self::get_tokens($booking);

        self::switch_lang($booking->booking_lang);
        self::send(
            $booking->email,
            WPPB_Settings::get('email_confirmed_client_subject'),
            WPPB_Settings::get('email_confirmed_client_body'),
            $tokens
        );
        self::restore_lang();

        self::send(
            WPPB_Settings::get_photographer_emails(),
            WPPB_Settings::get('email_confirmed_admin_subject'),
            WPPB_Settings::get('email_confirmed_admin_body'),
            $tokens
        );

        WPPB_Booking::log_activity($booking_id, 'email_sent', 'Confirmation emails sent to client and admin');
    }

    public static function send_booking_cancelled($booking_id) {
        $booking = WPPB_Booking::get($booking_id);
        if (!$booking) return;

        $tokens = self::get_tokens($booking);

        self::switch_lang($booking->booking_lang);
        self::send(
            $booking->email,
            WPPB_Settings::get('email_cancelled_client_subject'),
            WPPB_Settings::get('email_cancelled_client_body'),
            $tokens
        );
        self::restore_lang();

        self::send(
            WPPB_Settings::get_photographer_emails(),
            WPPB_Settings::get('email_cancelled_admin_subject'),
            WPPB_Settings::get('email_cancelled_admin_body'),
            $tokens
        );

        WPPB_Booking::log_activity($booking_id, 'email_sent', 'Cancellation emails sent to client and admin');
    }

    // -----------------------------------------------
    // Polylang language helpers
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

    // -----------------------------------------------
    // Helpers
    // -----------------------------------------------

    /**
     * Replacement tokens for an entry. Values are plain text; send() escapes them.
     * Besides the fixed tokens, every submitted field is available as {field:Label}.
     */
    public static function get_tokens($booking, $form = null) {
        $has_date = !empty($booking->booking_date);

        $time_label = '';
        if ($has_date && !empty($booking->time_slot)) {
            $time_label = date_i18n(get_option('time_format'), strtotime($booking->booking_date . ' ' . $booking->time_slot));
        }

        $questionnaire_url = '';
        if (!empty($booking->questionnaire_token)) {
            $questionnaire_url = add_query_arg('wppb_questionnaire', $booking->questionnaire_token, home_url('/'));
        }

        if (!$form && !empty($booking->form_id)) {
            $form = WPPB_Forms::get($booking->form_id);
        }

        $tokens = array(
            '{first_name}'          => $booking->first_name,
            '{last_name}'           => $booking->last_name,
            '{client_email}'        => $booking->email,
            '{phone}'               => $booking->phone,
            '{photography_type}'    => $booking->photography_type,
            '{booking_date}'        => $has_date ? date_i18n(get_option('date_format'), strtotime($booking->booking_date)) : '',
            '{time_slot}'           => $time_label,
            '{message}'             => $booking->message ? $booking->message : '—',
            '{site_name}'           => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            '{form_name}'           => $form ? $form->name : '',
            '{questionnaire_link}'  => $questionnaire_url,
        );

        $lines = array();
        foreach (WPPB_Booking::get_items($booking) as $item) {
            $value   = $item['value'] !== '' ? $item['value'] : '—';
            $lines[] = $item['label'] . ': ' . $value;
            $tokens['{field:' . $item['label'] . '}'] = $item['value'];
        }
        $tokens['{all_fields}'] = implode("\n", $lines);

        return $tokens;
    }

    public static function send_questionnaire($booking_id) {
        $booking = WPPB_Booking::get($booking_id);
        if (!$booking) return;

        $tokens = self::get_tokens($booking);

        self::switch_lang($booking->booking_lang);
        self::send(
            $booking->email,
            WPPB_Settings::get('questionnaire_email_subject'),
            WPPB_Settings::get('questionnaire_email_body'),
            $tokens
        );
        self::restore_lang();

        WPPB_Booking::log_activity($booking_id, 'auto_email', 'Pre-session questionnaire email sent to client');
    }

    /**
     * Send an email. The template is either plain text (line breaks are kept)
     * or HTML (when it contains block-level tags). Token values come from
     * visitors, so they are always escaped before they reach the HTML body.
     *
     * @param string|array $to       One address, or a list: the first is the
     *                               recipient, the others get a blind copy (Bcc).
     * @param string       $reply_to Optional Reply-To address.
     */
    public static function send($to, $subject, $body, $tokens, $reply_to = '') {
        $bcc = array();
        if (is_array($to)) {
            $bcc = array_values(array_filter($to, 'is_email'));
            $to  = array_shift($bcc);
        }
        if (!$to || !is_email($to)) return;

        // strtr() replaces in a single pass, so a token typed by a visitor
        // into a field is never expanded a second time.
        $subject = trim(preg_replace('/[\r\n]+/', ' ', strtr($subject, $tokens)));

        if (preg_match('/<(p|br|div|table|ul|ol|h[1-6])\b/i', $body)) {
            $body = strtr($body, array_map(function ($value) {
                return nl2br(esc_html((string) $value));
            }, $tokens));
        } else {
            $body = nl2br(esc_html(strtr($body, $tokens)));
        }

        $from_email = WPPB_Settings::get_photographer_email();
        $from_name  = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $headers    = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        );
        if ($reply_to && is_email($reply_to)) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }
        foreach ($bcc as $address) {
            $headers[] = 'Bcc: ' . $address;
        }
        wp_mail($to, $subject, $body, $headers);
    }
}
