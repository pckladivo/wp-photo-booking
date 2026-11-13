<?php if (!defined('ABSPATH')) die('Forbidden');
/**
 * Expects: $form (row or null), $form_type, $fields, $form_settings,
 *          $is_booking_form, $email_defaults.
 */
$type_label    = WPPB_Forms::types()[$form_type];
$is_custom     = $form_type === WPPB_Forms::TYPE_CUSTOM;
$has_recaptcha = WPPB_Settings::has_recaptcha();
$notify_fallback = implode(', ', WPPB_Settings::get_photographer_emails());
?>

<div class="wrap wppb-wrap">
    <h1><?php echo $form ? 'Edit ' . esc_html($type_label) : 'Add New Form'; ?></h1>

    <?php if ( isset($_GET['notice']) && $_GET['notice'] === 'saved' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Form saved.</p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
        <?php wp_nonce_field('wppb_save_form'); ?>
        <input type="hidden" name="action" value="wppb_save_form">
        <input type="hidden" name="form_type" id="wppb-form-type" value="<?php echo esc_attr($form_type); ?>">
        <?php if ( $form ) : ?>
            <input type="hidden" name="form_id" value="<?php echo esc_attr($form->id); ?>">
        <?php endif; ?>

        <table class="form-table" style="max-width:860px;">
            <tr>
                <th>Type</th>
                <td>
                    <?php if ($form) : ?>
                        <span class="wppb-badge wppb-type-<?php echo esc_attr($form_type); ?>"><?php echo esc_html($type_label); ?></span>
                    <?php else : ?>
                        <?php foreach (WPPB_Forms::types() as $t => $t_label) : ?>
                            <label style="margin-right:18px;">
                                <input type="radio" name="wppb_type_choice" value="<?php echo esc_attr($t); ?>" <?php checked($form_type, $t); ?>
                                       onchange="window.location.href = <?php echo esc_attr(wp_json_encode(admin_url('admin.php?page=wppb-form-edit&type=' . $t))); ?>;">
                                <?php echo esc_html($t_label); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <p class="description">
                        <?php if ($is_custom) : ?>
                            Starts empty; add any fields you like.
                            If the form contains a <strong>Booking date</strong> field, submissions become bookings (the date is reserved and you confirm or cancel each request).
                            Without one, submissions land in the Messages inbox.
                            <?php if ($form) : ?>
                                Right now this form creates <strong><?php echo $is_booking_form ? 'bookings' : 'messages'; ?></strong>.
                            <?php endif; ?>
                        <?php elseif ($is_booking_form) : ?>
                            Submissions become bookings: the chosen date is reserved and you confirm or cancel each request.
                            Working days, blocked dates and time slots are shared by all booking forms
                            (<a href="<?php echo esc_url(admin_url('admin.php?page=wppb-settings')); ?>">Settings</a>).
                        <?php else : ?>
                            Submissions land in the Messages inbox. No date is reserved.
                        <?php endif; ?>
                        <?php if (!$form) : ?>
                            <br>Choosing the other type reloads this page with that type's starting fields. The type cannot be changed after the form is created.
                        <?php else : ?>
                            The type is set when a form is created and cannot be changed afterwards.
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="wppb-form-name">Form Name <span style="color:#c0392b;">*</span></label></th>
                <td>
                    <input type="text" id="wppb-form-name" name="form_name" class="regular-text"
                           value="<?php echo esc_attr($form ? $form->name : ''); ?>"
                           placeholder="<?php echo $is_booking_form ? 'e.g. Wedding Booking' : 'e.g. Contact Us'; ?>" required>
                    <p class="description">For your own reference; visitors do not see it.</p>
                </td>
            </tr>
            <?php if ( $form ) : ?>
            <tr>
                <th>Shortcode</th>
                <td>
                    <code>[wppb_form id="<?php echo esc_html($form->id); ?>"]</code>
                    <p class="description">Paste it into any page or post.</p>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><label for="wppb-submit-label">Button Text</label></th>
                <td>
                    <input type="text" id="wppb-submit-label" name="submit_label" class="regular-text"
                           value="<?php echo esc_attr($form_settings['submit_label']); ?>"
                           placeholder="<?php echo $is_booking_form ? 'Submit' : 'Send'; ?>">
                </td>
            </tr>
            <tr>
                <th><label for="wppb-success-message">Success Message</label></th>
                <td>
                    <input type="text" id="wppb-success-message" name="success_message" class="large-text"
                           value="<?php echo esc_attr($form ? $form->success_message : ''); ?>"
                           placeholder="<?php echo $is_booking_form
                               ? 'Thank you! Your booking request has been received. We will contact you to confirm shortly.'
                               : 'Thank you! Your message has been sent.'; ?>">
                    <p class="description">Shown to the visitor after a successful submission.</p>
                </td>
            </tr>
            <tr>
                <th>Appearance</th>
                <td>
                    <label for="wppb-field-height">Field height</label>
                    <input type="number" id="wppb-field-height" name="field_height" class="small-text" min="24" max="120"
                           value="<?php echo esc_attr($form_settings['field_height']); ?>" placeholder="auto"> px
                    &nbsp;&nbsp;
                    <label for="wppb-field-gap">Space between fields</label>
                    <input type="number" id="wppb-field-gap" name="field_gap" class="small-text" min="0" max="80"
                           value="<?php echo esc_attr($form_settings['field_gap']); ?>" placeholder="18"> px
                    <p class="description">Leave empty to keep the default. The height applies to single-line fields and dropdowns, not to text areas. Fonts, colours and borders come from your theme.</p>
                </td>
            </tr>
            <tr>
                <th>Button</th>
                <td>
                    <label for="wppb-button-style">Style</label>
                    <select id="wppb-button-style" name="button_style">
                        <?php foreach (array('outline' => 'Outline', 'filled' => 'Filled', 'theme' => 'Theme default') as $bs => $bs_label) : ?>
                            <option value="<?php echo esc_attr($bs); ?>" <?php selected($form_settings['button_style'], $bs); ?>><?php echo esc_html($bs_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    &nbsp;&nbsp;
                    <label for="wppb-button-align">Position</label>
                    <select id="wppb-button-align" name="button_align">
                        <?php foreach (array('left' => 'Left', 'center' => 'Centre', 'right' => 'Right') as $ba => $ba_label) : ?>
                            <option value="<?php echo esc_attr($ba); ?>" <?php selected($form_settings['button_align'], $ba); ?>><?php echo esc_html($ba_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    &nbsp;&nbsp;
                    <label><input type="checkbox" name="button_full" value="1" <?php checked($form_settings['button_full'], '1'); ?>> Full width</label>
                    <p style="margin:10px 0 0;">
                        <label for="wppb-button-color" style="vertical-align:top;line-height:30px;margin-right:6px;">Colour</label>
                        <input type="text" id="wppb-button-color" name="button_color" class="wppb-color-field"
                               value="<?php echo esc_attr($form_settings['button_color']); ?>">
                    </p>
                    <p class="description"><strong>Outline</strong> is a thin frame that fills on hover; <strong>Filled</strong> is a solid button. Without a colour both use the text colour of your page.
                        <strong>Theme default</strong> leaves the button entirely to your theme. The button is as tall as the fields when a field height is set.</p>
                </td>
            </tr>
            <tr>
                <th>Spam Protection</th>
                <td>
                    <label>
                        <input type="checkbox" name="recaptcha" value="1" <?php checked($form_settings['recaptcha'], '1'); ?> <?php disabled(!$has_recaptcha); ?>>
                        Require reCAPTCHA on this form
                    </label>
                    <?php if (!$has_recaptcha) : ?>
                        <input type="hidden" name="recaptcha" value="<?php echo esc_attr($form_settings['recaptcha']); ?>">
                        <p class="description">reCAPTCHA keys are not configured yet
                            (<a href="<?php echo esc_url(admin_url('admin.php?page=wppb-settings')); ?>">Settings</a>).
                            A hidden honeypot field always protects the form.</p>
                    <?php else : ?>
                        <p class="description">A hidden honeypot field protects the form in addition.</p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <h2 style="margin-top:28px;">Form Fields</h2>
        <p class="description" style="margin-bottom:12px;max-width:860px;">
            Drag rows to reorder, or use the arrow buttons. <strong>Saves as</strong> tells the plugin what a field means,
            so it can be used in the entry list, in emails (e.g. <code>{first_name}</code>) and for replying to the client.
            Fields without it are stored as extra fields.
            <?php if ($is_booking_form || $is_custom) : ?>
                The Time field offers the free slots of the chosen date while time slots are configured in Settings; otherwise it is a plain time input for the client's preferred time.
            <?php endif; ?>
        </p>

        <div id="wppb-fields-container"></div>

        <p style="margin-top:8px;">
            <button type="button" id="wppb-add-field" class="button">+ Add Field</button>
        </p>

        <input type="hidden" id="wppb-fields-json" name="fields_json"
               value="<?php echo esc_attr(wp_json_encode($fields)); ?>">

        <h2 style="margin-top:28px;">Emails</h2>
        <p class="description wppb-token-list" style="max-width:860px;">
            Available tokens:
            <code>{first_name}</code> <code>{last_name}</code> <code>{client_email}</code> <code>{phone}</code>
            <code>{photography_type}</code> <code>{message}</code>
            <?php if ($is_booking_form || $is_custom) : ?><code>{booking_date}</code> <code>{time_slot}</code><?php endif; ?>
            <code>{site_name}</code> <code>{form_name}</code> <code>{all_fields}</code> <code>{field:Label}</code><br>
            <code>{all_fields}</code> lists every submitted field; <code>{field:Label}</code> inserts one field by its label.
            Plain text or HTML both supported.
            Leave a template empty to use the built-in text shown in grey.
            <?php if ($is_booking_form || $is_custom) : ?>
                Confirmation, cancellation and reminder emails of bookings are taken from
                <a href="<?php echo esc_url(admin_url('admin.php?page=wppb-settings&tab=emails')); ?>">Settings &rarr; Email Templates</a>.
            <?php endif; ?>
        </p>

        <h3>Notification to you</h3>
        <table class="form-table" style="max-width:860px;">
            <tr>
                <th><label for="wppb-notify-email">Send To</label></th>
                <td>
                    <input type="email" multiple id="wppb-notify-email" name="notify_email" class="regular-text"
                           value="<?php echo esc_attr($form ? $form->notify_email : ''); ?>"
                           placeholder="<?php echo esc_attr($notify_fallback); ?>">
                    <p class="description">Leave blank to use <?php echo esc_html($notify_fallback); ?>. Replying to the notification writes to the client.<br>
                        Several addresses can be entered, separated by commas: the first is the recipient, the others get a blind copy (Bcc).</p>
                </td>
            </tr>
            <tr>
                <th><label for="wppb-email-subject">Subject</label></th>
                <td>
                    <input type="text" id="wppb-email-subject" name="email_subject" class="large-text"
                           placeholder="<?php echo esc_attr($email_defaults['admin_subject']); ?>"
                           value="<?php echo esc_attr($form ? $form->email_subject : ''); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="wppb-admin-body">Body</label></th>
                <td>
                    <textarea id="wppb-admin-body" name="admin_body" rows="6" class="large-text"
                              placeholder="<?php echo esc_attr($email_defaults['admin_body']); ?>"><?php
                        echo esc_textarea($form_settings['admin_body']);
                    ?></textarea>
                </td>
            </tr>
        </table>

        <h3>Auto-reply to the client</h3>
        <table class="form-table" style="max-width:860px;">
            <tr>
                <th>Enable</th>
                <td>
                    <label>
                        <input type="checkbox" name="client_enabled" value="1" <?php checked($form_settings['client_enabled'], '1'); ?>>
                        Send an acknowledgement email to the client
                    </label>
                    <p class="description">Requires a field that saves as <strong>Client email</strong>.</p>
                </td>
            </tr>
            <tr>
                <th><label for="wppb-client-subject">Subject</label></th>
                <td>
                    <input type="text" id="wppb-client-subject" name="client_subject" class="large-text"
                           placeholder="<?php echo esc_attr($email_defaults['client_subject']); ?>"
                           value="<?php echo esc_attr($form_settings['client_subject']); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="wppb-client-body">Body</label></th>
                <td>
                    <textarea id="wppb-client-body" name="client_body" rows="6" class="large-text"
                              placeholder="<?php echo esc_attr($email_defaults['client_body']); ?>"><?php
                        echo esc_textarea($form_settings['client_body']);
                    ?></textarea>
                </td>
            </tr>
        </table>

        <?php submit_button( $form ? 'Save Form' : 'Create Form' ); ?>
    </form>
</div>
