<?php
if (!defined('ABSPATH')) die('Forbidden');

/**
 * Renders every public form of the plugin: booking forms, contact forms
 * and the pre-session questionnaire. Also owns the shortcodes and assets.
 */
class WPPB_Form_Renderer {

    const NONCE = 'wppb_form';

    /** POST key that holds the submitted field values, keyed by field ID. */
    const FIELDS_KEY = 'wppb_fields';

    /** Longest accepted value, in characters, for single-line and multi-line fields. */
    const MAX_LENGTH_LINE = 300;
    const MAX_LENGTH_TEXT = 5000;

    private static $base_enqueued    = false;
    private static $booking_enqueued = false;

    public function __construct() {
        add_shortcode( 'wppb_form',     array( $this, 'shortcode_form' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
    }

    // -----------------------------------------------
    // Shortcodes
    // -----------------------------------------------

    public function shortcode_form( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts );
        $form = WPPB_Forms::get( intval( $atts['id'] ) );
        if ( ! $form ) return '<p>' . esc_html__( 'Form not found.', 'wp-photo-booking' ) . '</p>';
        return self::render( $form );
    }

    // -----------------------------------------------
    // Assets
    // -----------------------------------------------

    /**
     * Load the stylesheet in <head> when the page content contains one of our
     * shortcodes. Forms placed elsewhere (widgets, page builders) still work:
     * render() enqueues everything it needs on demand.
     */
    public function maybe_enqueue() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) return;
        if ( ! has_shortcode( $post->post_content, 'wppb_form' ) ) return;

        // The calendar stylesheet is only needed by forms with a date field.
        $booking = false;
        if ( preg_match_all( '/\[wppb_form[^\]]*\bid=["\']?(\d+)/', $post->post_content, $m ) ) {
            foreach ( $m[1] as $id ) {
                $form = WPPB_Forms::get( intval( $id ) );
                if ( $form && WPPB_Forms::creates_bookings( $form ) ) $booking = true;
            }
        }
        self::enqueue( $booking );
    }

    public static function enqueue( $booking = false, $recaptcha = false ) {
        if ( ! self::$base_enqueued ) {
            self::$base_enqueued = true;
            wp_enqueue_style( 'wppb-frontend', WPPB_URL . 'assets/css/frontend.css', array(), WPPB_VERSION );
            wp_enqueue_script( 'wppb-form', WPPB_URL . 'assets/js/form.js', array( 'jquery' ), WPPB_VERSION, true );
            wp_localize_script( 'wppb-form', 'wppb_form', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( self::NONCE ),
                'i18n'     => array(
                    /* translators: %s: field label */
                    'required'           => __( '%s is required.', 'wp-photo-booking' ),
                    'recaptcha_required' => __( 'Please complete the reCAPTCHA verification.', 'wp-photo-booking' ),
                    'loading_slots'      => __( 'Loading available times...', 'wp-photo-booking' ),
                    'no_slots_available' => __( 'No time slots available for this date.', 'wp-photo-booking' ),
                    'network_error'      => __( 'Network error. Please check your connection and try again.', 'wp-photo-booking' ),
                    'generic_error'      => __( 'An error occurred. Please try again.', 'wp-photo-booking' ),
                    'sending'            => __( 'Sending…', 'wp-photo-booking' ),
                ),
            ) );
        }

        if ( $recaptcha ) {
            wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true );
        }

        if ( $booking && ! self::$booking_enqueued ) {
            self::$booking_enqueued = true;
            wp_enqueue_style( 'wppb-jquery-ui', WPPB_URL . 'assets/css/jquery-ui.min.css', array(), '1.13.2' );
            wp_enqueue_script( 'jquery-ui-datepicker' );

            $booked   = WPPB_Booking::get_booked_dates();
            $blocked  = array_column( WPPB_Booking::get_blocked_dates(), 'blocked_date' );
            $disabled = array_values( array_unique( array_merge( $booked, $blocked ) ) );

            wp_add_inline_script( 'wppb-form', 'var wppb_booking = ' . wp_json_encode( array(
                'disabled_dates' => $disabled,
                'working_days'   => array_values( WPPB_Settings::get_working_days() ),
                'min_days_ahead' => max( 1, intval( WPPB_Settings::get( 'min_days_ahead' ) ) ),
            ) ) . ';', 'before' );
        }
    }

    // -----------------------------------------------
    // Rendering
    // -----------------------------------------------

    /** Full markup of a builder form. */
    public static function render( $form ) {
        $settings  = WPPB_Forms::get_settings( $form );
        $booking   = WPPB_Forms::creates_bookings( $form );
        $recaptcha = self::uses_recaptcha( $form );
        $submit    = $settings['submit_label'] !== ''
            ? WPPB_Forms::t( $settings['submit_label'] )
            : ( $booking ? __( 'Submit', 'wp-photo-booking' ) : __( 'Send', 'wp-photo-booking' ) );

        self::enqueue( $booking, $recaptcha );

        return self::form_html( array(
            'wrap_id'   => 'wppb-form-wrap-' . intval( $form->id ),
            'look'      => $settings,
            'class'     => 'wppb-form-' . $form->form_type,
            'action'    => 'wppb_submit_form',
            'hidden'    => array( 'form_id' => intval( $form->id ) ),
            'fields'    => WPPB_Forms::get_active_fields( $form ),
            'prefix'    => 'wppb_f' . intval( $form->id ) . '_',
            'submit'    => $submit,
            'recaptcha' => $recaptcha,
        ) );
    }

    /** Markup of the pre-session questionnaire for one booking. */
    public static function render_questionnaire( $form, $token ) {
        self::enqueue( false );

        $fields = array();
        foreach ( WPPB_Forms::get_fields( $form ) as $field ) {
            // A questionnaire never books anything.
            if ( in_array( $field['type'] ?? '', array( 'booking_date', 'time_slot' ), true ) ) continue;
            $fields[] = $field;
        }

        return self::form_html( array(
            'wrap_id'   => 'wppb-questionnaire-wrap',
            'look'      => WPPB_Forms::get_settings( $form ),
            'class'     => 'wppb-form-questionnaire',
            'action'    => 'wppb_submit_questionnaire',
            'hidden'    => array( 'token' => $token ),
            'fields'    => $fields,
            'prefix'    => 'wppb_q_',
            'submit'    => __( 'Submit Questionnaire', 'wp-photo-booking' ),
            'recaptcha' => false,
        ) );
    }

    public static function uses_recaptcha( $form ) {
        $settings = WPPB_Forms::get_settings( $form );
        return WPPB_Settings::has_recaptcha() && $settings['recaptcha'] === '1';
    }

    private static function form_html( $args ) {
        ob_start();
        ?>
        <?php
        // Per-form appearance, handed to the stylesheet as custom properties.
        $wrap_class = 'wppb-wrap wppb-form-wrap';
        $wrap_style = '';
        if ( $args['look']['field_height'] !== '' ) {
            $wrap_class .= ' wppb-has-field-height';
            $wrap_style .= '--wppb-field-height:' . intval( $args['look']['field_height'] ) . 'px;';
        }
        if ( $args['look']['field_gap'] !== '' ) {
            $wrap_style .= '--wppb-field-gap:' . intval( $args['look']['field_gap'] ) . 'px;';
        }

        $button_style = in_array( $args['look']['button_style'], array( 'outline', 'filled', 'theme' ), true ) ? $args['look']['button_style'] : 'outline';
        $wrap_class  .= ' wppb-btn-' . $button_style . ' wppb-btn-align-' . sanitize_html_class( $args['look']['button_align'] );
        if ( $args['look']['button_full'] === '1' ) $wrap_class .= ' wppb-btn-full';
        $button_color = sanitize_hex_color( $args['look']['button_color'] );
        if ( $button_color ) {
            $wrap_style .= '--wppb-btn-color:' . $button_color . ';--wppb-btn-contrast:' . self::contrast_color( $button_color ) . ';';
        }
        // Block themes style buttons through wp-element-button; only wanted when the theme is in charge.
        $button_class = 'wppb-btn wppb-submit-btn' . ( $button_style === 'theme' ? ' wp-element-button' : '' );
        ?>
        <div class="<?php echo esc_attr( $wrap_class ); ?>" id="<?php echo esc_attr( $args['wrap_id'] ); ?>"<?php echo $wrap_style !== '' ? ' style="' . esc_attr( $wrap_style ) . '"' : ''; ?>>
            <div class="wppb-notice wppb-notice-success" role="status" style="display:none;"></div>
            <div class="wppb-notice wppb-notice-error" role="alert" style="display:none;"></div>

            <form class="wppb-form <?php echo esc_attr( $args['class'] ); ?>" data-action="<?php echo esc_attr( $args['action'] ); ?>" novalidate>
                <?php foreach ( $args['hidden'] as $name => $value ) : ?>
                    <input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
                <?php endforeach; ?>

                <!-- Honeypot: hidden from real users, bots fill it -->
                <div style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                    <label><?php esc_html_e( 'Leave empty', 'wp-photo-booking' ); ?>
                        <input type="text" name="wppb_website" value="" tabindex="-1" autocomplete="off">
                    </label>
                </div>

                <div class="wppb-fields">
                    <?php foreach ( $args['fields'] as $field ) self::field_html( $field, $args['prefix'] ); ?>
                </div>

                <?php if ( $args['recaptcha'] ) : ?>
                    <div class="wppb-field wppb-recaptcha-field">
                        <div class="g-recaptcha" data-sitekey="<?php echo esc_attr( WPPB_Settings::get( 'recaptcha_site_key' ) ); ?>"></div>
                    </div>
                <?php endif; ?>

                <div class="wppb-submit-row">
                    <button type="submit" class="<?php echo esc_attr( $button_class ); ?>"><?php echo esc_html( $args['submit'] ); ?></button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function field_html( $field, $prefix ) {
        $fid         = $field['id'] ?? '';
        $type        = $field['type'] ?? 'text';
        $label       = WPPB_Forms::t( $field['label'] ?? '' );
        $placeholder = WPPB_Forms::t( $field['placeholder'] ?? '' );
        // The time field is a slot picker while slots are configured, else a free time input.
        $slot_mode   = $type === 'time_slot' && WPPB_Settings::has_time_slots();
        $free_time   = $type === 'time_slot' && ! $slot_mode;
        $required    = ! empty( $field['required'] ) || $type === 'booking_date' || $slot_mode;
        $options     = WPPB_Forms::field_options( $field );
        $input_id    = $prefix . $fid;
        $name        = self::FIELDS_KEY . '[' . $fid . ']';
        $req_attr    = $required ? ' required' : '';
        $star        = $required ? ' <span class="wppb-req">*</span>' : '';

        if ( $type === 'heading' ) {
            echo '<div class="wppb-field wppb-field-heading"><h3 class="wppb-heading">' . esc_html( $label ) . '</h3></div>';
            return;
        }

        $classes = 'wppb-field wppb-field-' . $type;
        if ( ( $field['width'] ?? '' ) === 'half' ) $classes .= ' wppb-half';
        if ( $slot_mode )                           $classes .= ' wppb-time-slot-field';
        ?>
        <div class="<?php echo esc_attr( $classes ); ?>"
             data-type="<?php echo esc_attr( $free_time ? 'time' : $type ); ?>"
             data-label="<?php echo esc_attr( wp_strip_all_tags( $label ) ); ?>"
             data-required="<?php echo $required ? '1' : '0'; ?>">

            <?php if ( $type === 'consent' ) : ?>
                <label class="wppb-option-label">
                    <input type="checkbox" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1"<?php echo $req_attr; ?>>
                    <span><?php echo wp_kses( $label, array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); echo $star; ?></span>
                </label>

            <?php elseif ( $slot_mode || $type === 'radio' || $type === 'checkbox' ) : ?>
                <span class="wppb-field-label"><?php echo esc_html( $label ); echo $star; ?></span>
            <?php else : ?>
                <label for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $label ); echo $star; ?></label>
            <?php endif; ?>

            <?php if ( $type === 'textarea' ) : ?>
                <textarea id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="4" maxlength="<?php echo intval( self::MAX_LENGTH_TEXT ); ?>"
                          placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php echo $req_attr; ?>></textarea>

            <?php elseif ( $type === 'select' ) : ?>
                <select id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $req_attr; ?>>
                    <option value=""><?php echo esc_html( $placeholder !== '' ? $placeholder : __( 'Select…', 'wp-photo-booking' ) ); ?></option>
                    <?php foreach ( $options as $opt ) : ?>
                        <option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( WPPB_Forms::t( $opt ) ); ?></option>
                    <?php endforeach; ?>
                </select>

            <?php elseif ( $type === 'radio' || $type === 'checkbox' ) : ?>
                <div class="wppb-options" role="group" aria-label="<?php echo esc_attr( $label ); ?>">
                    <?php foreach ( $options as $i => $opt ) : ?>
                        <label class="wppb-option-label">
                            <?php if ( $type === 'radio' ) : ?>
                                <input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $opt ); ?>"<?php echo ( $required && $i === 0 ) ? ' required' : ''; ?>>
                            <?php else : ?>
                                <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $opt ); ?>">
                            <?php endif; ?>
                            <span><?php echo esc_html( WPPB_Forms::t( $opt ) ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

            <?php elseif ( $type === 'booking_date' ) : ?>
                <input type="text" class="wppb-date" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $name ); ?>"
                       placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off" readonly>

            <?php elseif ( $free_time ) : ?>
                <input type="time" class="wppb-time" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $name ); ?>" step="900"<?php echo $req_attr; ?>>

            <?php elseif ( $slot_mode ) : ?>
                <div class="wppb-time-slot-container">
                    <p class="wppb-slot-hint"><?php esc_html_e( 'Select a date first to see available times.', 'wp-photo-booking' ); ?></p>
                </div>
                <input type="hidden" class="wppb-time-slot" name="<?php echo esc_attr( $name ); ?>" value="">

            <?php elseif ( $type !== 'consent' ) : /* text, email, tel, number */
                // Themes style text inputs but often not tel / number ones, so those are
                // rendered as text with the matching on-screen keyboard; the server validates.
                $input_type = $type === 'email' ? 'email' : 'text';
                $inputmode  = $type === 'tel' ? ' inputmode="tel"' : ( $type === 'number' ? ' inputmode="decimal"' : '' );
            ?>
                <input type="<?php echo esc_attr( $input_type ); ?>"<?php echo $inputmode; ?> id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $name ); ?>"
                       placeholder="<?php echo esc_attr( $placeholder ); ?>" maxlength="<?php echo intval( self::MAX_LENGTH_LINE ); ?>"<?php echo self::autocomplete_attr( $field ); echo $req_attr; ?>>
            <?php endif; ?>
        </div>
        <?php
    }

    /** Black or white, whichever reads better on the given #rrggbb / #rgb colour. */
    private static function contrast_color( $hex ) {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        // Perceived brightness (ITU-R BT.601)
        return ( ( $r * 299 + $g * 587 + $b * 114 ) / 1000 ) > 150 ? '#111111' : '#ffffff';
    }

    private static function autocomplete_attr( $field ) {
        $map = array(
            'first_name' => 'given-name',
            'last_name'  => 'family-name',
            'email'      => 'email',
            'phone'      => 'tel',
        );
        $role = $field['role'] ?? '';
        return isset( $map[ $role ] ) ? ' autocomplete="' . esc_attr( $map[ $role ] ) . '"' : '';
    }

    // -----------------------------------------------
    // Reading a submission
    // -----------------------------------------------

    /**
     * Validate and sanitize submitted values against a field list.
     *
     * @param array $fields Field definitions.
     * @param array $input  Raw, unslashed values keyed by field ID.
     * @return array|WP_Error List of array( id, type, role, label, value ).
     */
    public static function collect( $fields, $input ) {
        $input = is_array( $input ) ? $input : array();
        $items = array();

        foreach ( $fields as $field ) {
            $id       = $field['id'] ?? '';
            $type     = $field['type'] ?? 'text';
            $label    = wp_strip_all_tags( WPPB_Forms::t( $field['label'] ?? '' ) );
            $required = ! empty( $field['required'] );
            $raw      = $input[ $id ] ?? '';

            if ( $type === 'heading' ) continue;
            if ( $label === '' ) $label = $id;

            if ( $type === 'checkbox' ) {
                $chosen = is_array( $raw ) ? array_map( 'sanitize_text_field', $raw ) : array();
                $value  = implode( ', ', array_intersect( WPPB_Forms::field_options( $field ), $chosen ) );
            } elseif ( is_array( $raw ) ) {
                $value = '';
            } elseif ( $type === 'consent' ) {
                $value = $raw === '1' ? __( 'Yes', 'wp-photo-booking' ) : '';
            } elseif ( $type === 'textarea' ) {
                $value = sanitize_textarea_field( $raw );
            } else {
                $value = sanitize_text_field( $raw );
            }

            // A slot (when slots are configured) is checked by the handler against
            // the configured and still free slots; a free time is checked here.
            $slot_mode = $type === 'time_slot' && WPPB_Settings::has_time_slots();
            if ( $required && $value === '' && ! $slot_mode ) {
                /* translators: %s: field label */
                return new WP_Error( 'required', sprintf( __( '%s is required.', 'wp-photo-booking' ), $label ) );
            }

            $max = $type === 'textarea' ? self::MAX_LENGTH_TEXT : self::MAX_LENGTH_LINE;
            if ( $type !== 'checkbox' && mb_strlen( $value ) > $max ) {
                /* translators: 1: field label, 2: maximum number of characters */
                return new WP_Error( 'too_long', sprintf( __( '%1$s is too long (maximum %2$d characters).', 'wp-photo-booking' ), $label, $max ) );
            }

            if ( $value !== '' ) {
                $invalid = false;
                if ( $type === 'email' ) {
                    $invalid = ! is_email( $value );
                } elseif ( $type === 'number' ) {
                    $invalid = ! is_numeric( $value );
                } elseif ( $type === 'select' || $type === 'radio' ) {
                    $invalid = ! in_array( $value, WPPB_Forms::field_options( $field ), true );
                } elseif ( $type === 'booking_date' ) {
                    $invalid = ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
                } elseif ( $type === 'time_slot' && ! $slot_mode ) {
                    $invalid = ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value );
                }
                if ( $invalid ) {
                    /* translators: %s: field label */
                    return new WP_Error( 'invalid', sprintf( __( 'Please enter a valid value for "%s".', 'wp-photo-booking' ), $label ) );
                }
            }

            $items[] = array(
                'id'    => $id,
                'type'  => $type,
                'role'  => $field['role'] ?? '',
                'label' => $label,
                'value' => $value,
            );
        }

        return $items;
    }
}
