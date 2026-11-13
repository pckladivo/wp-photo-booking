<?php
if (!defined('ABSPATH')) die('Forbidden');

/**
 * AJAX endpoints behind the public forms.
 */
class WPPB_Form_Handler {

    public function __construct() {
        foreach ( array( 'submit_form', 'get_available_slots', 'submit_questionnaire' ) as $action ) {
            add_action( 'wp_ajax_wppb_' . $action,        array( $this, $action ) );
            add_action( 'wp_ajax_nopriv_wppb_' . $action, array( $this, $action ) );
        }
    }

    private static function fail( $message ) {
        wp_send_json_error( array( 'message' => $message ) );
    }

    /**
     * Submissions allowed per visitor address within the window. Limits spam,
     * use of the auto-reply to mail third parties, and filling up the calendar.
     */
    const RATE_WINDOW = 600; // seconds

    private static function rate_key() {
        // The connection address only: forwarded-for headers can be forged.
        // Keyed hash: the stored value cannot be turned back into the address.
        return 'wppb_rl_' . wp_hash( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    }

    /** Stop when this visitor has already sent too much. Administrators are exempt. */
    private static function enforce_rate_limit() {
        if ( current_user_can( 'manage_options' ) ) return;
        $limit = (int) apply_filters( 'wppb_rate_limit', (int) WPPB_Settings::get( 'rate_limit' ) );
        if ( $limit > 0 && (int) get_transient( self::rate_key() ) >= $limit ) {
            self::fail( __( 'Too many submissions. Please try again later.', 'wp-photo-booking' ) );
        }
    }

    private static function count_submission() {
        if ( current_user_can( 'manage_options' ) ) return;
        $key = self::rate_key();
        set_transient( $key, (int) get_transient( $key ) + 1, (int) apply_filters( 'wppb_rate_window', self::RATE_WINDOW ) );
    }

    private static function submitted_fields() {
        $key = WPPB_Form_Renderer::FIELDS_KEY;
        return ( isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ) ? wp_unslash( $_POST[ $key ] ) : array();
    }

    /**
     * Submit a booking or contact form.
     */
    public function submit_form() {
        check_ajax_referer( WPPB_Form_Renderer::NONCE, 'nonce' );

        $form = WPPB_Forms::get( intval( $_POST['form_id'] ?? 0 ) );
        if ( ! $form ) self::fail( __( 'Form not found.', 'wp-photo-booking' ) );

        $is_booking = WPPB_Forms::creates_bookings( $form );
        $success    = $form->success_message !== ''
            ? WPPB_Forms::t( $form->success_message )
            : ( $is_booking
                ? __( 'Thank you! Your booking request has been received. We will contact you to confirm shortly.', 'wp-photo-booking' )
                : __( 'Thank you! Your message has been sent.', 'wp-photo-booking' ) );

        // Honeypot: pretend it worked, store nothing.
        if ( ! empty( $_POST['wppb_website'] ) ) {
            wp_send_json_success( array( 'message' => $success ) );
        }

        self::enforce_rate_limit();

        if ( WPPB_Form_Renderer::uses_recaptcha( $form ) ) {
            $this->verify_recaptcha();
        }

        $items = WPPB_Form_Renderer::collect( WPPB_Forms::get_active_fields( $form ), self::submitted_fields() );
        if ( is_wp_error( $items ) ) self::fail( $items->get_error_message() );

        if ( $is_booking ) {
            $date = '';
            $slot = '';
            foreach ( $items as $item ) {
                if ( $item['role'] === 'booking_date' ) $date = $item['value'];
                if ( $item['role'] === 'time_slot' )    $slot = $item['value'];
            }

            if ( $date === '' ) self::fail( __( 'Please select a date.', 'wp-photo-booking' ) );

            if ( WPPB_Settings::has_time_slots() ) {
                if ( $slot === '' ) self::fail( __( 'Please select a time slot.', 'wp-photo-booking' ) );
                if ( ! in_array( $slot, WPPB_Settings::get_time_slots(), true ) ) {
                    self::fail( __( 'Invalid time slot selected.', 'wp-photo-booking' ) );
                }
            }

            // Check and insert under one lock, so two simultaneous requests
            // cannot both get the same date. Released when the request ends.
            if ( ! WPPB_Booking::lock() ) {
                self::fail( __( 'The booking system is busy. Please try again.', 'wp-photo-booking' ) );
            }

            if ( ! WPPB_Booking::is_date_available( $date, $slot ) ) {
                self::fail( __( 'This date/time is not available. Please choose another.', 'wp-photo-booking' ) );
            }
        }

        $entry_id = WPPB_Booking::create_entry( $form, $items );
        WPPB_Booking::unlock();
        if ( ! $entry_id ) {
            self::fail( __( 'Could not save your submission. Please try again.', 'wp-photo-booking' ) );
        }

        self::count_submission();
        WPPB_Email::send_new_entry( $entry_id );

        wp_send_json_success( array( 'message' => $success ) );
    }

    private function verify_recaptcha() {
        $token = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';
        if ( $token === '' ) {
            self::fail( __( 'Please complete the reCAPTCHA verification.', 'wp-photo-booking' ) );
        }
        $verify = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret'   => WPPB_Settings::get( 'recaptcha_secret_key' ),
                'response' => $token,
            ),
        ) );
        if ( is_wp_error( $verify ) ) {
            self::fail( __( 'Could not verify reCAPTCHA. Please try again.', 'wp-photo-booking' ) );
        }
        $result = json_decode( wp_remote_retrieve_body( $verify ), true );
        if ( empty( $result['success'] ) ) {
            self::fail( __( 'reCAPTCHA verification failed. Please try again.', 'wp-photo-booking' ) );
        }
    }

    /**
     * Return the free time slots of one date.
     */
    public function get_available_slots() {
        check_ajax_referer( WPPB_Form_Renderer::NONCE, 'nonce' );

        $date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            self::fail( __( 'Invalid date format.', 'wp-photo-booking' ) );
        }

        $slots = array();
        foreach ( WPPB_Booking::get_available_slots( $date ) as $slot ) {
            $slots[] = array(
                'value' => $slot,
                'label' => date_i18n( get_option( 'time_format' ), strtotime( $date . ' ' . $slot ) ),
            );
        }
        wp_send_json_success( array( 'slots' => $slots ) );
    }

    /**
     * Submit the pre-session questionnaire of one booking.
     */
    public function submit_questionnaire() {
        check_ajax_referer( WPPB_Form_Renderer::NONCE, 'nonce' );

        $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
        if ( $token === '' ) self::fail( __( 'Invalid questionnaire link.', 'wp-photo-booking' ) );

        $booking = WPPB_Booking::get_by_questionnaire_token( $token );
        if ( ! $booking ) self::fail( __( 'Questionnaire not found.', 'wp-photo-booking' ) );
        if ( $booking->questionnaire_completed_at ) {
            self::fail( __( 'This questionnaire has already been submitted.', 'wp-photo-booking' ) );
        }

        // Honeypot
        if ( ! empty( $_POST['wppb_website'] ) ) {
            wp_send_json_success( array( 'message' => __( 'Thank you! Your questionnaire has been submitted.', 'wp-photo-booking' ) ) );
        }

        $form = WPPB_Forms::get( intval( WPPB_Settings::get( 'questionnaire_form_id', 0 ) ) );
        if ( ! $form ) self::fail( __( 'Questionnaire is not configured.', 'wp-photo-booking' ) );

        $fields = array();
        foreach ( WPPB_Forms::get_fields( $form ) as $field ) {
            if ( in_array( $field['type'] ?? '', array( 'booking_date', 'time_slot' ), true ) ) continue;
            $fields[] = $field;
        }

        $items = WPPB_Form_Renderer::collect( $fields, self::submitted_fields() );
        if ( is_wp_error( $items ) ) self::fail( $items->get_error_message() );

        $values = array();
        foreach ( $items as $item ) {
            $values[] = array( 'label' => $item['label'], 'value' => $item['value'] );
        }
        WPPB_Booking::save_questionnaire( $booking->id, wp_json_encode( $values ) );

        wp_send_json_success( array( 'message' => __( 'Thank you! Your questionnaire has been submitted.', 'wp-photo-booking' ) ) );
    }
}
