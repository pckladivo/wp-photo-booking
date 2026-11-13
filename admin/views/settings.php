<?php if (!defined('ABSPATH')) die('Forbidden');
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
?>
<div class="wrap wppb-wrap">
    <h1>Photo Booking Settings</h1>

    <?php
    $notice_map = array(
        'saved'        => array('success', 'Settings saved.'),
        'date_added'   => array('success', 'Date blocked.'),
        'date_removed' => array('success', 'Blocked date removed.'),
    );
    $notice = (isset($_GET['notice']) && is_string($_GET['notice'])) ? sanitize_key($_GET['notice']) : '';
    if (isset($notice_map[$notice])) :
        [$type, $msg] = $notice_map[$notice];
    ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible"><p><?php echo esc_html($msg); ?></p></div>
    <?php endif; ?>

    <!-- Tab navigation -->
    <nav class="nav-tab-wrapper wppb-tabs">
        <?php
        $tabs = array(
            'general'    => 'General',
            'emails'     => 'Email Templates',
            'automation' => 'Automation',
            'blocked'    => 'Blocked Dates',
        );
        foreach ($tabs as $slug => $label) :
            $url   = add_query_arg(array('page' => 'wppb-settings', 'tab' => $slug), admin_url('admin.php'));
            $class = ($active_tab === $slug) ? 'nav-tab nav-tab-active' : 'nav-tab';
        ?>
            <a href="<?php echo esc_url($url); ?>" class="<?php echo $class; ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </nav>

    <!-- ==========================================
         TAB: General
         ========================================== -->
    <?php if ($active_tab === 'general') : ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('wppb_save_settings'); ?>
        <input type="hidden" name="action" value="wppb_save_settings">
        <input type="hidden" name="wppb_tab" value="general">

        <h2>Booking Settings</h2>
        <table class="form-table">
            <tr>
                <th><label for="photographer_email">Photographer Email</label></th>
                <td>
                    <input type="email" multiple name="photographer_email" id="photographer_email"
                           value="<?php echo esc_attr($settings['photographer_email']); ?>" class="regular-text">
                    <p class="description">Receives booking notifications. Leave empty to use the WordPress admin email (<?php echo esc_html(get_option('admin_email')); ?>).<br>
                        For more than one recipient, separate the addresses with commas (up to <?php echo (int) WPPB_Settings::MAX_RECIPIENTS; ?>). The first one is the main address and the sender of all emails; the others get a blind copy (Bcc).</p>
                </td>
            </tr>
            <tr>
                <th><label for="min_days_ahead">Minimum Days Ahead</label></th>
                <td>
                    <input type="number" name="min_days_ahead" id="min_days_ahead"
                           value="<?php echo intval($settings['min_days_ahead']); ?>" min="0" max="365" class="small-text">
                    <p class="description">Clients cannot book within this many days from today.</p>
                </td>
            </tr>
            <tr>
                <th>Working Days</th>
                <td>
                    <?php
                    $day_names    = array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');
                    $working_days = is_array($settings['working_days']) ? $settings['working_days'] : array(1,2,3,4,5);
                    foreach ($day_names as $i => $name) : ?>
                        <label style="margin-right:14px;">
                            <input type="checkbox" name="working_days[]" value="<?php echo $i; ?>"
                                <?php checked(in_array($i, array_map('intval', $working_days))); ?>>
                            <?php echo esc_html($name); ?>
                        </label>
                    <?php endforeach; ?>
                    <p class="description">Only these days will be selectable on the booking calendar.</p>
                </td>
            </tr>
            <tr>
                <th><label for="time_slots">Time Slots</label></th>
                <td>
                    <textarea name="time_slots" id="time_slots" rows="3" class="large-text"><?php
                        $slots = is_array($settings['time_slots']) ? $settings['time_slots'] : array();
                        echo esc_textarea(implode(', ', $slots));
                    ?></textarea>
                    <p class="description">Enter times in 24-hour format separated by commas or new lines (e.g. <code>09:00, 11:00, 14:00, 16:30</code>). Times are sorted automatically. Leave empty to use date-only mode (one booking per day).</p>
                </td>
            </tr>
        </table>

        <h2>Privacy</h2>
        <p class="description" style="max-width:860px;">
            Entries contain personal data. Keep them only as long as you need them.
            A person's entries can be exported or erased under
            <a href="<?php echo esc_url(admin_url('export-personal-data.php')); ?>">Tools &rarr; Export Personal Data</a> and
            <a href="<?php echo esc_url(admin_url('erase-personal-data.php')); ?>">Erase Personal Data</a>,
            and suggested wording for your privacy policy is in the
            <a href="<?php echo esc_url(admin_url('options-privacy.php?tab=policyguide')); ?>">privacy policy guide</a>.
            Visitor IP addresses are not stored.
        </p>
        <table class="form-table">
            <tr>
                <th><label for="retention_messages_days">Delete Messages After</label></th>
                <td>
                    <input type="number" name="retention_messages_days" id="retention_messages_days"
                           value="<?php echo intval($settings['retention_messages_days']); ?>" min="0" max="36500" class="small-text">
                    days after they were received
                    <p class="description"><code>0</code> keeps messages until you delete them yourself.</p>
                </td>
            </tr>
            <tr>
                <th><label for="retention_bookings_days">Delete Bookings After</label></th>
                <td>
                    <input type="number" name="retention_bookings_days" id="retention_bookings_days"
                           value="<?php echo intval($settings['retention_bookings_days']); ?>" min="0" max="36500" class="small-text">
                    days after the session date
                    <p class="description"><code>0</code> keeps bookings until you delete them yourself. Deletion runs once a day and cannot be undone; export what you must keep (for example for bookkeeping) first.</p>
                </td>
            </tr>
        </table>

        <h2>Spam Protection</h2>
        <table class="form-table">
            <tr>
                <th><label for="rate_limit">Submission Limit</label></th>
                <td>
                    <input type="number" name="rate_limit" id="rate_limit"
                           value="<?php echo intval($settings['rate_limit']); ?>" min="0" max="1000" class="small-text">
                    submissions per visitor in 10 minutes
                    <p class="description">Applies to every form. Limits spam, misuse of the auto-reply and attempts to fill up the calendar. <code>0</code> switches the limit off.
                        Visitors are told apart by their network address: if the site runs behind a proxy or CDN that hides visitor addresses, all visitors share one allowance, so raise the number or switch it off.</p>
                </td>
            </tr>
        </table>

        <h2>Google reCAPTCHA v2</h2>
        <p class="description">
            Get your keys at <a href="https://www.google.com/recaptcha/admin/create" target="_blank">google.com/recaptcha</a>.
            Choose <strong>reCAPTCHA v2 &rarr; "I'm not a robot" Checkbox</strong>.
            Leave both fields empty to disable reCAPTCHA.
        </p>
        <table class="form-table">
            <tr>
                <th><label for="recaptcha_site_key">Site Key</label></th>
                <td>
                    <input type="text" name="recaptcha_site_key" id="recaptcha_site_key"
                           value="<?php echo esc_attr($settings['recaptcha_site_key']); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th><label for="recaptcha_secret_key">Secret Key</label></th>
                <td>
                    <input type="text" name="recaptcha_secret_key" id="recaptcha_secret_key"
                           value="<?php echo esc_attr($settings['recaptcha_secret_key']); ?>" class="regular-text">
                </td>
            </tr>
        </table>

        <?php submit_button('Save Settings'); ?>
    </form>

    <hr>
    <h2>Shortcodes</h2>
    <p>Fields, labels and emails of every form are managed under
        <a href="<?php echo esc_url(admin_url('admin.php?page=wppb-forms')); ?>">Forms</a>, where each form has its own <code>[wppb_form id="…"]</code> shortcode to paste into a page.</p>
    <p>Version: <strong><?php echo esc_html(WPPB_VERSION); ?></strong></p>

    <!-- ==========================================
         TAB: Email Templates
         ========================================== -->
    <?php elseif ($active_tab === 'emails') : ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('wppb_save_settings'); ?>
        <input type="hidden" name="action" value="wppb_save_settings">
        <input type="hidden" name="wppb_tab" value="emails">

        <h2>Transactional Email Templates</h2>
        <p class="description">Available tokens:
            <code>{first_name}</code> <code>{last_name}</code> <code>{client_email}</code>
            <code>{phone}</code> <code>{photography_type}</code> <code>{booking_date}</code>
            <code>{time_slot}</code> <code>{message}</code> <code>{site_name}</code>
            <code>{form_name}</code> <code>{all_fields}</code> <code>{field:Label}</code>
        </p>
        <p class="description" style="margin-top:4px;">These templates apply to all booking forms. The emails sent when a form is submitted are set per form under <a href="<?php echo esc_url(admin_url('admin.php?page=wppb-forms')); ?>">Forms</a>.</p>
        <p class="description" style="margin-top:4px;">Plain text or HTML both supported. Plain text lines are converted to <code>&lt;br&gt;</code> automatically.</p>

        <?php
        $template_groups = array(
            'confirmed' => 'Booking Confirmed',
            'cancelled' => 'Booking Cancelled',
        );
        foreach ($template_groups as $key => $label) : ?>
            <h3><?php echo esc_html($label); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label>Client — Subject</label></th>
                    <td><input type="text" name="email_<?php echo esc_attr($key); ?>_client_subject"
                               value="<?php echo esc_attr($settings['email_' . $key . '_client_subject']); ?>" class="large-text"></td>
                </tr>
                <tr>
                    <th><label>Client — Body</label></th>
                    <td><textarea name="email_<?php echo esc_attr($key); ?>_client_body" rows="6" class="large-text"><?php
                        echo esc_textarea($settings['email_' . $key . '_client_body']);
                    ?></textarea></td>
                </tr>
                <tr>
                    <th><label>Admin — Subject</label></th>
                    <td><input type="text" name="email_<?php echo esc_attr($key); ?>_admin_subject"
                               value="<?php echo esc_attr($settings['email_' . $key . '_admin_subject']); ?>" class="large-text"></td>
                </tr>
                <tr>
                    <th><label>Admin — Body</label></th>
                    <td><textarea name="email_<?php echo esc_attr($key); ?>_admin_body" rows="6" class="large-text"><?php
                        echo esc_textarea($settings['email_' . $key . '_admin_body']);
                    ?></textarea></td>
                </tr>
            </table>
        <?php endforeach; ?>

        <?php submit_button('Save Settings'); ?>
    </form>

    <!-- ==========================================
         TAB: Automation
         ========================================== -->
    <?php elseif ($active_tab === 'automation') : ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('wppb_save_settings'); ?>
        <input type="hidden" name="action" value="wppb_save_settings">
        <input type="hidden" name="wppb_tab" value="automation">

        <h2>Automated Emails</h2>
        <p class="description">These emails are sent automatically by WP-Cron once per day. Each email is sent only once per booking.</p>

        <h3>Session Reminder</h3>
        <p class="description">Sent to both the client and the photographer a set number of days before a <strong>confirmed</strong> session.</p>
        <table class="form-table">
            <tr>
                <th>Enable</th>
                <td>
                    <label>
                        <input type="checkbox" name="auto_session_reminder" value="1"
                            <?php checked($settings['auto_session_reminder'], '1'); ?>>
                        Send session reminders automatically
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="auto_session_reminder_days">Days Before Session</label></th>
                <td>
                    <input type="number" name="auto_session_reminder_days" id="auto_session_reminder_days"
                           value="<?php echo intval($settings['auto_session_reminder_days']); ?>" min="1" max="30" class="small-text">
                    <p class="description">Reminder fires when the session is this many days away.</p>
                </td>
            </tr>
            <tr>
                <th><label>Client — Subject</label></th>
                <td><input type="text" name="auto_session_reminder_client_subject"
                           value="<?php echo esc_attr($settings['auto_session_reminder_client_subject']); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th><label>Client — Body</label></th>
                <td><textarea name="auto_session_reminder_client_body" rows="6" class="large-text"><?php
                    echo esc_textarea($settings['auto_session_reminder_client_body']);
                ?></textarea></td>
            </tr>
            <tr>
                <th><label>Admin — Subject</label></th>
                <td><input type="text" name="auto_session_reminder_admin_subject"
                           value="<?php echo esc_attr($settings['auto_session_reminder_admin_subject']); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th><label>Admin — Body</label></th>
                <td><textarea name="auto_session_reminder_admin_body" rows="6" class="large-text"><?php
                    echo esc_textarea($settings['auto_session_reminder_admin_body']);
                ?></textarea></td>
            </tr>
        </table>

        <h3>Pending Booking Follow-up</h3>
        <p class="description">Sent to the client when a booking has been <strong>pending</strong> for more than N days without a response.</p>
        <table class="form-table">
            <tr>
                <th>Enable</th>
                <td>
                    <label>
                        <input type="checkbox" name="auto_pending_followup" value="1"
                            <?php checked($settings['auto_pending_followup'], '1'); ?>>
                        Send pending follow-up emails automatically
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="auto_pending_followup_days">Days Until Follow-up</label></th>
                <td>
                    <input type="number" name="auto_pending_followup_days" id="auto_pending_followup_days"
                           value="<?php echo intval($settings['auto_pending_followup_days']); ?>" min="1" max="30" class="small-text">
                    <p class="description">Send the follow-up after the booking has been pending for this many days.</p>
                </td>
            </tr>
            <tr>
                <th><label>Client — Subject</label></th>
                <td><input type="text" name="auto_pending_followup_subject"
                           value="<?php echo esc_attr($settings['auto_pending_followup_subject']); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th><label>Client — Body</label></th>
                <td><textarea name="auto_pending_followup_body" rows="6" class="large-text"><?php
                    echo esc_textarea($settings['auto_pending_followup_body']);
                ?></textarea></td>
            </tr>
        </table>

        <h3>Pre-Session Questionnaire</h3>
        <p class="description">Automatically send a questionnaire link to clients before their confirmed session. Build the questionnaire using the Custom Forms builder, then select it here.</p>
        <p class="description" style="margin-top:4px;">Additional token: <code>{questionnaire_link}</code></p>
        <table class="form-table">
            <tr>
                <th>Enable</th>
                <td>
                    <label>
                        <input type="checkbox" name="questionnaire_enabled" value="1"
                            <?php checked($settings['questionnaire_enabled'], '1'); ?>>
                        Send pre-session questionnaire automatically
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="questionnaire_form_id">Questionnaire Form</label></th>
                <td>
                    <select name="questionnaire_form_id" id="questionnaire_form_id">
                        <option value="0">&mdash; Select a form &mdash;</option>
                        <?php
                        $all_forms = WPPB_Forms::get_all();
                        foreach ($all_forms as $qf) :
                            if (WPPB_Forms::creates_bookings($qf)) continue; ?>
                            <option value="<?php echo intval($qf->id); ?>" <?php selected(intval($settings['questionnaire_form_id']), intval($qf->id)); ?>>
                                <?php echo esc_html($qf->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Build your questionnaire as a contact or custom form under <a href="<?php echo esc_url(admin_url('admin.php?page=wppb-forms')); ?>">Forms</a>, then select it here.</p>
                </td>
            </tr>
            <tr>
                <th><label for="questionnaire_days_before">Days Before Session</label></th>
                <td>
                    <input type="number" name="questionnaire_days_before" id="questionnaire_days_before"
                           value="<?php echo intval($settings['questionnaire_days_before']); ?>" min="1" max="30" class="small-text">
                    <p class="description">Send the questionnaire this many days before the session.</p>
                </td>
            </tr>
            <tr>
                <th><label>Client — Subject</label></th>
                <td><input type="text" name="questionnaire_email_subject"
                           value="<?php echo esc_attr($settings['questionnaire_email_subject']); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th><label>Client — Body</label></th>
                <td><textarea name="questionnaire_email_body" rows="6" class="large-text"><?php
                    echo esc_textarea($settings['questionnaire_email_body']);
                ?></textarea></td>
            </tr>
        </table>

        <?php submit_button('Save Settings'); ?>
    </form>

    <!-- ==========================================
         TAB: Blocked Dates
         ========================================== -->
    <?php elseif ($active_tab === 'blocked') : ?>

    <h2>Blocked Dates</h2>
    <p>Manually block specific dates so clients cannot book them.</p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wppb-add-blocked-form">
        <?php wp_nonce_field('wppb_add_blocked_date'); ?>
        <input type="hidden" name="action" value="wppb_add_blocked_date">
        <input type="text" name="blocked_date" id="wppb-blocked-date" placeholder="Pick a date" class="regular-text" readonly>
        <input type="text" name="reason" placeholder="Reason (optional)" class="regular-text" style="max-width:240px">
        <button type="submit" class="button button-primary">Block Date</button>
    </form>

    <?php if (!empty($blocked_dates)) : ?>
    <table class="wp-list-table widefat fixed striped" style="max-width:640px;margin-top:16px;">
        <thead>
            <tr>
                <th>Date</th>
                <th>Reason</th>
                <th style="width:80px"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($blocked_dates as $bd) : ?>
            <tr>
                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($bd->blocked_date))); ?></td>
                <td><?php echo esc_html($bd->reason ? $bd->reason : '—'); ?></td>
                <td>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('wppb_remove_blocked_date'); ?>
                        <input type="hidden" name="action" value="wppb_remove_blocked_date">
                        <input type="hidden" name="blocked_date_id" value="<?php echo intval($bd->id); ?>">
                        <button type="submit" class="button button-small">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
        <p style="color:#666">No blocked dates configured.</p>
    <?php endif; ?>

    <?php endif; ?>
</div>
