<?php
if (!defined('ABSPATH')) die('Forbidden');

class WPPB_Questionnaire_Frontend {

    public function __construct() {
        add_action('template_redirect', array($this, 'maybe_render'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue'));
    }

    /**
     * Detect ?wppb_questionnaire=TOKEN and render the questionnaire page.
     */
    public function maybe_render() {
        if (empty($_GET['wppb_questionnaire'])) return;

        $token   = sanitize_text_field($_GET['wppb_questionnaire']);
        $booking = WPPB_Booking::get_by_questionnaire_token($token);

        // Output a standalone page using the theme's header/footer
        status_header(200);
        get_header();

        echo '<div class="wppb-questionnaire-wrap" style="max-width:680px;margin:40px auto;padding:0 20px;">';

        if (!$booking) {
            echo '<h1>' . esc_html__('Questionnaire Not Found', 'wp-photo-booking') . '</h1>';
            echo '<p>' . esc_html__('This questionnaire link is invalid or has expired.', 'wp-photo-booking') . '</p>';
        } elseif ($booking->questionnaire_completed_at) {
            echo '<h1>' . esc_html__('Questionnaire Already Submitted', 'wp-photo-booking') . '</h1>';
            echo '<p>' . esc_html__('Thank you! You have already submitted your questionnaire for this session.', 'wp-photo-booking') . '</p>';
        } else {
            $form_id = intval(WPPB_Settings::get('questionnaire_form_id', 0));
            $form    = $form_id ? WPPB_Forms::get($form_id) : null;

            if (!$form) {
                echo '<h1>' . esc_html__('Questionnaire Unavailable', 'wp-photo-booking') . '</h1>';
                echo '<p>' . esc_html__('The questionnaire is not available at this time.', 'wp-photo-booking') . '</p>';
            } else {
                $date    = date_i18n(get_option('date_format'), strtotime($booking->booking_date));
                $time    = !empty($booking->time_slot)
                    ? ' ' . esc_html__('at', 'wp-photo-booking') . ' ' . date_i18n(get_option('time_format'), strtotime($booking->booking_date . ' ' . $booking->time_slot))
                    : '';

                echo '<h1>' . esc_html__('Pre-Session Questionnaire', 'wp-photo-booking') . '</h1>';
                echo '<p>' . sprintf(
                    esc_html__('Hi %s! Please fill out this short questionnaire to help us prepare for your %s session on %s%s.', 'wp-photo-booking'),
                    esc_html($booking->first_name),
                    esc_html($booking->photography_type),
                    esc_html($date),
                    $time
                ) . '</p>';

                echo WPPB_Form_Renderer::render_questionnaire($form, $token);
            }
        }

        echo '</div>';

        get_footer();
        exit;
    }

    public function maybe_enqueue() {
        if (empty($_GET['wppb_questionnaire'])) return;
        WPPB_Form_Renderer::enqueue(false);
    }
}
