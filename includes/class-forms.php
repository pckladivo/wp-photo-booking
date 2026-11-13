<?php
if (!defined('ABSPATH')) die('Forbidden');

/**
 * Form definitions: storage, field schema and per-form settings.
 *
 * A field is an array:
 *   id          unique key within the form (used as the input name)
 *   type        one of field_types()
 *   label       visible label (heading text / consent text for those types)
 *   placeholder optional
 *   required    bool
 *   options     newline separated list (select, radio, checkbox)
 *   role        which entry column the value is saved to ('' = extra field)
 *   width       'full' or 'half'
 */
class WPPB_Forms {

    const TYPE_CONTACT = 'contact';
    const TYPE_BOOKING = 'booking';
    /** Starts empty and allows every field; behaves as a booking form once it has a date field. */
    const TYPE_CUSTOM  = 'custom';

    /** Form types, keyed by type => admin label. */
    public static function types() {
        return array(
            self::TYPE_CONTACT => 'Contact form',
            self::TYPE_BOOKING => 'Booking form',
            self::TYPE_CUSTOM  => 'Custom form',
        );
    }

    public static function valid_type( $type ) {
        return isset( self::types()[ $type ] ) ? $type : self::TYPE_CONTACT;
    }


    /** Polylang string group. */
    const PLL_GROUP = 'WP Photo Booking';

    // -----------------------------------------------
    // Schema
    // -----------------------------------------------

    /** Field types available in the builder, keyed by type => admin label. */
    public static function field_types( $form_type = self::TYPE_BOOKING ) {
        $types = array(
            'text'     => 'Text',
            'email'    => 'Email',
            'tel'      => 'Phone',
            'number'   => 'Number',
            'textarea' => 'Textarea',
            'select'   => 'Dropdown',
            'radio'    => 'Radio',
            'checkbox' => 'Checkboxes',
            'consent'  => 'Consent checkbox',
            'heading'  => 'Section heading',
        );
        if ( $form_type !== self::TYPE_CONTACT ) {
            $types['booking_date'] = 'Booking date (calendar)';
            $types['time_slot']    = 'Time';
        }
        return $types;
    }

    /** Roles a field can take, keyed by role => admin label. */
    public static function roles() {
        return array(
            'first_name'       => 'First name (or full name)',
            'last_name'        => 'Last name',
            'email'            => 'Client email',
            'phone'            => 'Phone',
            'photography_type' => 'Type / interest',
            'message'          => 'Message',
        );
    }

    /** Field types that can carry a role. */
    public static function role_capable_types() {
        return array( 'text', 'email', 'tel', 'number', 'textarea', 'select', 'radio' );
    }

    public static function default_settings( $form_type ) {
        return array(
            'submit_label'   => '',
            'recaptcha'      => '1',
            // Appearance in pixels; '' leaves it to the theme / plugin default.
            'field_height'   => '',
            'field_gap'      => '',
            // Submit button: 'outline', 'filled' or 'theme' (leave it to the theme)
            'button_style'   => 'outline',
            'button_color'   => '',
            'button_align'   => 'left',
            'button_full'    => '0',
            'admin_body'     => '',
            'client_enabled' => $form_type === self::TYPE_BOOKING ? '1' : '0',
            'client_subject' => '',
            'client_body'    => '',
        );
    }

    private static function make_field( $id, $type, $label, $placeholder = '', $role = '', $width = 'full', $options = '' ) {
        return array(
            'id'          => $id,
            'type'        => $type,
            'label'       => $label,
            'placeholder' => $placeholder,
            'required'    => true,
            'options'     => $options,
            'role'        => $role,
            'width'       => $width,
        );
    }

    /** Starting fields of a new booking form; everything can be changed in the builder. */
    public static function default_booking_fields() {
        return array(
            self::make_field( 'photography_type', 'select', 'I am interested in', 'Select an option', 'photography_type', 'full',
                implode( "\n", WPPB_Settings::get_photography_types() ) ),
            self::make_field( 'first_name',   'text',         'First Name',     'First Name',             'first_name', 'half' ),
            self::make_field( 'last_name',    'text',         'Last Name',      'Last Name',              'last_name',  'half' ),
            self::make_field( 'email',        'email',        'Email',          'Email',                  'email' ),
            self::make_field( 'phone',        'tel',          'Contact Number', 'Contact Number',         'phone' ),
            self::make_field( 'booking_date', 'booking_date', 'Date',           'Click to select a date', 'booking_date' ),
            self::make_field( 'time_slot',    'time_slot',    'Time',           '',                       'time_slot' ),
            self::make_field( 'message',      'textarea',     'Remarks',        'Remarks',                'message' ),
        );
    }

    /** Starting point for a new contact form. */
    public static function default_contact_fields() {
        return array(
            self::make_field( 'name',    'text',     'Name',    '', 'first_name' ),
            self::make_field( 'email',   'email',    'Email',   '', 'email' ),
            self::make_field( 'message', 'textarea', 'Message', '', 'message' ),
        );
    }

    /**
     * Clean a list of raw field definitions (from the builder) for a form type.
     * Guarantees unique IDs, unique roles and, for booking forms, a date field.
     */
    public static function sanitize_fields( $fields, $form_type ) {
        $types    = self::field_types( $form_type );
        $roles    = self::roles();
        $role_ok  = self::role_capable_types();
        $clean    = array();
        $used_ids = array();
        $used     = array();

        foreach ( (array) $fields as $f ) {
            if ( ! is_array( $f ) ) continue;

            $type = isset( $f['type'] ) ? (string) $f['type'] : 'text';
            if ( ! isset( $types[ $type ] ) ) {
                // Booking-only types have no meaning on a contact form.
                if ( in_array( $type, array( 'booking_date', 'time_slot' ), true ) ) continue;
                $type = 'text';
            }

            $role      = '';
            $singleton = $type === 'booking_date' || $type === 'time_slot';
            if ( $singleton ) {
                $role = $type;
            } elseif ( in_array( $type, $role_ok, true ) && isset( $f['role'], $roles[ $f['role'] ] ) ) {
                $role = $f['role'];
            }
            if ( $role !== '' ) {
                if ( isset( $used[ $role ] ) ) {
                    if ( $singleton ) continue; // only one date / time slot field per form
                    $role = '';
                } else {
                    $used[ $role ] = true;
                }
            }

            $id = sanitize_key( $f['id'] ?? '' );
            if ( $id === '' || isset( $used_ids[ $id ] ) ) {
                $n = count( $clean ) + 1;
                do { $id = 'field_' . $n++; } while ( isset( $used_ids[ $id ] ) );
            }
            $used_ids[ $id ] = true;

            // Consent text may link to a privacy policy.
            $label = $type === 'consent'
                ? wp_kses( $f['label'] ?? '', array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) )
                : sanitize_text_field( $f['label'] ?? '' );

            $clean[] = array(
                'id'          => $id,
                'type'        => $type,
                'label'       => $label,
                'placeholder' => sanitize_text_field( $f['placeholder'] ?? '' ),
                'required'    => $type === 'booking_date' ? true : ! empty( $f['required'] ),
                'options'     => sanitize_textarea_field( $f['options'] ?? '' ),
                'role'        => $role,
                'width'       => ( $f['width'] ?? '' ) === 'half' ? 'half' : 'full',
            );
        }

        if ( $form_type === self::TYPE_BOOKING && ! isset( $used['booking_date'] ) ) {
            $id = isset( $used_ids['booking_date'] ) ? 'booking_date_field' : 'booking_date';
            $clean[] = self::make_field( $id, 'booking_date', 'Date', 'Click to select a date', 'booking_date' );
        }

        return $clean;
    }

    /** Options of a select / radio / checkbox field as a clean list. */
    public static function field_options( $field ) {
        return array_values( array_filter( array_map( 'trim', explode( "\n", $field['options'] ?? '' ) ), 'strlen' ) );
    }

    // -----------------------------------------------
    // Storage
    // -----------------------------------------------

    /** Pass a form type to list only forms of that type. */
    public static function get_all( $type = '' ) {
        global $wpdb;
        $sql = "SELECT * FROM " . WPPB_TABLE_FORMS;
        if ( $type ) {
            $sql .= $wpdb->prepare( " WHERE form_type = %s", $type );
        }
        return $wpdb->get_results( $sql . " ORDER BY created_at DESC, id DESC" );
    }

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . WPPB_TABLE_FORMS . " WHERE id = %d",
            intval( $id )
        ) );
    }

    /** True for forms of the booking type (a date field is guaranteed). */
    public static function is_booking( $form ) {
        return $form && $form->form_type === self::TYPE_BOOKING;
    }

    /**
     * Whether a submission of this form reserves a date. Always the case for
     * booking forms; for custom forms only when they contain a date field.
     */
    public static function creates_bookings( $form ) {
        if ( ! $form ) return false;
        if ( $form->form_type === self::TYPE_BOOKING ) return true;
        if ( $form->form_type !== self::TYPE_CUSTOM ) return false;
        foreach ( self::get_fields( $form ) as $field ) {
            if ( ( $field['type'] ?? '' ) === 'booking_date' ) return true;
        }
        return false;
    }

    /**
     * Insert or update a form.
     * $data['fields'] must be a PHP array; this method encodes it.
     * 'form_type' and 'settings' are optional and left untouched on update when omitted.
     * Returns the form ID (0 if a new form could not be inserted).
     */
    public static function save( $data, $id = 0 ) {
        global $wpdb;

        $row = array(
            'name'            => sanitize_text_field( $data['name'] ),
            'fields'          => wp_json_encode( $data['fields'] ),
            'notify_email'    => implode( ', ', WPPB_Settings::parse_emails( $data['notify_email'], 200 ) ),
            'email_subject'   => sanitize_text_field( $data['email_subject'] ),
            'success_message' => sanitize_textarea_field( $data['success_message'] ),
        );
        if ( isset( $data['form_type'] ) ) {
            $row['form_type'] = self::valid_type( $data['form_type'] );
        }
        if ( isset( $data['settings'] ) ) {
            $row['settings'] = wp_json_encode( (array) $data['settings'] );
        }

        if ( $id ) {
            $wpdb->update( WPPB_TABLE_FORMS, $row, array( 'id' => intval( $id ) ) );
            return intval( $id );
        }

        $row['created_at'] = current_time( 'mysql' );
        if ( ! $wpdb->insert( WPPB_TABLE_FORMS, $row ) ) return 0;
        return intval( $wpdb->insert_id );
    }

    public static function delete( $id ) {
        global $wpdb;
        $wpdb->delete( WPPB_TABLE_FORMS, array( 'id' => intval( $id ) ) );
    }

    /** Decode the fields JSON from a form row into a PHP array. */
    public static function get_fields( $form ) {
        if ( ! $form ) return array();
        $fields = json_decode( $form->fields, true );
        return is_array( $fields ) ? $fields : array();
    }

    /**
     * Fields as they are actually shown and submitted.
     *
     * The time field belongs to a booking: it is dropped from forms that do not
     * create bookings. While time slots are configured it is the slot picker and
     * is added to booking forms that lack one, because a slot is then mandatory;
     * without configured slots it is a plain "preferred time" input.
     */
    public static function get_active_fields( $form ) {
        $fields    = self::get_fields( $form );
        $booking   = self::creates_bookings( $form );
        $has_slots = $booking && WPPB_Settings::has_time_slots();
        $out       = array();
        $has_slot_field = false;

        foreach ( $fields as $field ) {
            $type = $field['type'] ?? 'text';
            if ( $type === 'time_slot' ) {
                if ( ! $booking ) continue;
                $has_slot_field = true;
            }
            $out[] = $field;
        }

        if ( $has_slots && ! $has_slot_field ) {
            $slot   = self::make_field( 'time_slot', 'time_slot', __( 'Time', 'wp-photo-booking' ), '', 'time_slot' );
            $result = array();
            $placed = false;
            foreach ( $out as $field ) {
                $result[] = $field;
                if ( ( $field['type'] ?? '' ) === 'booking_date' ) {
                    $result[] = $slot;
                    $placed   = true;
                }
            }
            $out = $placed ? $result : array_merge( $out, array( $slot ) );
        }

        return $out;
    }

    /** Per-form settings merged over the defaults for the form's type. */
    public static function get_settings( $form ) {
        $defaults = self::default_settings( $form ? $form->form_type : self::TYPE_CONTACT );
        if ( ! $form || empty( $form->settings ) ) return $defaults;
        $settings = json_decode( $form->settings, true );
        return is_array( $settings ) ? array_merge( $defaults, $settings ) : $defaults;
    }

    // -----------------------------------------------
    // Translation of form content (Polylang)
    // -----------------------------------------------

    /** Translate a string entered in the builder, when Polylang is active. */
    public static function t( $string ) {
        if ( $string === '' || $string === null ) return '';
        return function_exists( 'pll__' ) ? pll__( $string ) : $string;
    }

    /** Make every builder string of every form translatable in Polylang. */
    public static function register_strings() {
        if ( ! function_exists( 'pll_register_string' ) ) return;

        foreach ( self::get_all() as $form ) {
            $name     = 'Form ' . $form->id . ': ';
            $settings = self::get_settings( $form );

            foreach ( self::get_fields( $form ) as $field ) {
                $fid = $field['id'] ?? '';
                if ( ! empty( $field['label'] ) )       pll_register_string( $name . $fid . ' label', $field['label'], self::PLL_GROUP );
                if ( ! empty( $field['placeholder'] ) ) pll_register_string( $name . $fid . ' placeholder', $field['placeholder'], self::PLL_GROUP );
                foreach ( self::field_options( $field ) as $opt ) {
                    pll_register_string( $name . $fid . ' option', $opt, self::PLL_GROUP );
                }
            }

            $single = array(
                'success message' => $form->success_message,
                'submit label'    => $settings['submit_label'],
                'client subject'  => $settings['client_subject'],
            );
            foreach ( $single as $key => $value ) {
                if ( $value !== '' ) pll_register_string( $name . $key, $value, self::PLL_GROUP );
            }
            if ( $settings['client_body'] !== '' ) {
                pll_register_string( $name . 'client body', $settings['client_body'], self::PLL_GROUP, true );
            }
        }
    }
}
