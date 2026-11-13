<?php if (!defined('ABSPATH')) die('Forbidden');
/**
 * Single entry (booking or message).
 * Expects: $booking, $is_contact, $page, $form.
 */
$noun        = $is_contact ? 'Message' : 'Booking';
$client_name = trim($booking->first_name . ' ' . $booking->last_name);
$extra_items = WPPB_Booking::get_extra_items($booking);
?>
<div class="wrap wppb-wrap">
    <h1 class="wp-heading-inline">
        <?php echo esc_html($noun); ?> #<?php echo intval($booking->id); ?><?php if ($client_name !== '') : ?> &mdash; <?php echo esc_html($client_name); ?><?php endif; ?>
    </h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $page)); ?>" class="page-title-action">&larr; Back to list</a>
    <hr class="wp-header-end">

    <?php if (!empty($_GET['notice'])) :
        $notices = array(
            'confirmed'   => array('success', 'Booking confirmed. Confirmation emails have been sent.'),
            'cancelled'   => array('success', 'Booking cancelled. Cancellation emails have been sent.'),
            'notes_saved' => array('success', 'Admin notes saved.'),
            'status_updated' => array('success', 'Message updated.'),
        );
        $n = sanitize_text_field($_GET['notice']);
        if (isset($notices[$n])) : [$type, $msg] = $notices[$n]; ?>
            <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible"><p><?php echo esc_html($msg); ?></p></div>
        <?php endif;
    endif; ?>

    <div class="wppb-view-grid">

        <!-- Details -->
        <div class="wppb-card">
            <h2><?php echo esc_html($noun); ?> Details</h2>
            <table class="wppb-detail-table">
                <tr>
                    <th>Status</th>
                    <td><span class="wppb-badge wppb-status-<?php echo esc_attr($booking->status); ?>"><?php echo esc_html(ucfirst($booking->status)); ?></span></td>
                </tr>
                <?php if ($form) : ?>
                <tr><th>Form</th><td><?php echo esc_html($form->name); ?></td></tr>
                <?php endif; ?>
                <?php if ($booking->first_name !== '') : ?>
                <tr><th><?php echo $booking->last_name !== '' ? 'First Name' : 'Name'; ?></th><td><?php echo esc_html($booking->first_name); ?></td></tr>
                <?php endif; ?>
                <?php if ($booking->last_name !== '') : ?>
                <tr><th>Last Name</th><td><?php echo esc_html($booking->last_name); ?></td></tr>
                <?php endif; ?>
                <?php if ($booking->email !== '') : ?>
                <tr><th>Email</th><td><a href="mailto:<?php echo esc_attr($booking->email); ?>"><?php echo esc_html($booking->email); ?></a></td></tr>
                <?php endif; ?>
                <?php if ($booking->phone !== '') : ?>
                <tr><th>Phone</th><td><?php echo esc_html($booking->phone); ?></td></tr>
                <?php endif; ?>
                <?php if ($booking->photography_type !== '') : ?>
                <tr><th><?php echo $is_contact ? 'Interest' : 'Photography Type'; ?></th><td><?php echo esc_html($booking->photography_type); ?></td></tr>
                <?php endif; ?>
                <?php if (!$is_contact && !empty($booking->booking_date)) : ?>
                <tr><th>Session Date</th><td>
                    <strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($booking->booking_date))); ?></strong>
                    <?php if (!empty($booking->time_slot)) : ?>
                        at <strong><?php echo esc_html(date_i18n(get_option('time_format'), strtotime($booking->booking_date . ' ' . $booking->time_slot))); ?></strong>
                    <?php endif; ?>
                </td></tr>
                <?php endif; ?>
                <tr><th>Message</th><td><?php echo nl2br(esc_html($booking->message ? $booking->message : '—')); ?></td></tr>
                <?php foreach ($extra_items as $item) : ?>
                <tr>
                    <th><?php echo esc_html($item['label']); ?></th>
                    <td><?php echo nl2br(esc_html($item['value'] !== '' ? $item['value'] : '—')); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr><th>Submitted</th><td><?php echo esc_html($booking->created_at); ?></td></tr>
                <?php if ($booking->updated_at !== $booking->created_at) : ?>
                <tr><th>Last Updated</th><td><?php echo esc_html($booking->updated_at); ?></td></tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Actions -->
        <?php if (!$is_contact && $booking->status === 'pending') : ?>
        <div class="wppb-card wppb-actions-card">
            <h2>Actions</h2>
            <p>Review the booking details and either confirm or cancel it. Emails will be sent automatically.</p>
            <div class="wppb-action-buttons">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('wppb_confirm_booking'); ?>
                    <input type="hidden" name="action" value="wppb_confirm_booking">
                    <input type="hidden" name="booking_id" value="<?php echo intval($booking->id); ?>">
                    <button type="submit" class="button button-primary button-hero">&#10003; Confirm Booking</button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('wppb_cancel_booking'); ?>
                    <input type="hidden" name="action" value="wppb_cancel_booking">
                    <input type="hidden" name="booking_id" value="<?php echo intval($booking->id); ?>">
                    <button type="submit" class="button button-hero">&#10007; Cancel Booking</button>
                </form>
            </div>
        </div>
        <?php elseif ($is_contact) : ?>
        <div class="wppb-card wppb-actions-card">
            <h2>Actions</h2>
            <div class="wppb-action-buttons">
                <?php if ($booking->email !== '') : ?>
                    <a class="button button-primary button-hero" href="mailto:<?php echo esc_attr($booking->email); ?>">&#9993; Reply by Email</a>
                <?php endif; ?>
                <?php
                $status_actions = $booking->status === 'archived'
                    ? array('read' => 'Move Back to Inbox')
                    : array('archived' => 'Archive', 'new' => 'Mark as New');
                foreach ($status_actions as $target => $label) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('wppb_set_entry_status'); ?>
                    <input type="hidden" name="action" value="wppb_set_entry_status">
                    <input type="hidden" name="booking_id" value="<?php echo intval($booking->id); ?>">
                    <input type="hidden" name="status" value="<?php echo esc_attr($target); ?>">
                    <input type="hidden" name="return_to_view" value="1">
                    <button type="submit" class="button button-hero"><?php echo esc_html($label); ?></button>
                </form>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Admin notes -->
        <div class="wppb-card">
            <h2>Admin Notes</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('wppb_save_notes'); ?>
                <input type="hidden" name="action" value="wppb_save_notes">
                <input type="hidden" name="booking_id" value="<?php echo intval($booking->id); ?>">
                <textarea name="admin_notes" rows="6" class="large-text" placeholder="Internal notes (not visible to the client)..."><?php echo esc_textarea($booking->admin_notes ? $booking->admin_notes : ''); ?></textarea>
                <p style="margin-top:8px"><button type="submit" class="button button-primary">Save Notes</button></p>
            </form>
        </div>

        <!-- Activity log -->
        <?php $activity = WPPB_Booking::get_activity($booking->id); ?>
        <?php if (!empty($activity)) : ?>
        <div class="wppb-card wppb-activity-card">
            <h2>Activity Log</h2>
            <table class="wppb-detail-table">
                <thead>
                    <tr>
                        <th style="width:140px;color:#666;">Time</th>
                        <th style="width:140px;color:#666;">Event</th>
                        <th style="color:#666;">Detail</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($activity as $entry) : ?>
                    <tr>
                        <td style="font-size:12px;color:#999;white-space:nowrap;"><?php echo esc_html($entry->created_at); ?></td>
                        <td><span class="wppb-activity-action wppb-action-<?php echo esc_attr($entry->action); ?>"><?php echo esc_html(str_replace('_', ' ', $entry->action)); ?></span></td>
                        <td style="color:#555;"><?php echo esc_html($entry->detail); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Questionnaire -->
        <?php if (!empty($booking->questionnaire_data)) :
            $q_data = json_decode($booking->questionnaire_data, true);
        ?>
        <div class="wppb-card wppb-activity-card">
            <h2>Pre-Session Questionnaire</h2>
            <p style="color:#666;font-size:12px;margin-top:-4px;">Completed: <?php echo esc_html($booking->questionnaire_completed_at); ?></p>
            <table class="wppb-detail-table">
                <?php if (is_array($q_data)) : foreach ($q_data as $item) : ?>
                <tr>
                    <th><?php echo esc_html($item['label']); ?></th>
                    <td><?php echo nl2br(esc_html($item['value'] ? $item['value'] : '—')); ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </table>
        </div>
        <?php elseif (!empty($booking->questionnaire_token) && $booking->status === 'confirmed' && WPPB_Settings::get('questionnaire_enabled') === '1') : ?>
        <div class="wppb-card">
            <h2>Pre-Session Questionnaire</h2>
            <p>The client has not yet completed the questionnaire.</p>
            <p><small>Link: <code><?php echo esc_html(add_query_arg('wppb_questionnaire', $booking->questionnaire_token, home_url('/'))); ?></code></small></p>
        </div>
        <?php endif; ?>

        <!-- Danger zone -->
        <div class="wppb-card wppb-danger-card">
            <h2>Delete <?php echo esc_html($noun); ?></h2>
            <p>Permanently remove this <?php echo esc_html(strtolower($noun)); ?> and all its activity. This cannot be undone.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Permanently delete this <?php echo esc_js(strtolower($noun)); ?>?')">
                <?php wp_nonce_field('wppb_delete_booking'); ?>
                <input type="hidden" name="action" value="wppb_delete_booking">
                <input type="hidden" name="booking_id" value="<?php echo intval($booking->id); ?>">
                <button type="submit" class="button wppb-delete-btn">Delete <?php echo esc_html($noun); ?></button>
            </form>
        </div>

    </div>
</div>
