<?php if (!defined('ABSPATH')) die('Forbidden');
/**
 * List of entries of one type.
 * Expects: $entry_type, $is_contact, $page, $statuses, $status_filter, $search,
 *          $form_filter, $forms (id => name, offered in the filter),
 *          $form_names (id => name, all forms), $bookings, $counts.
 */
$noun     = $is_contact ? 'message' : 'booking';
$base_url = admin_url('admin.php?page=' . $page);
$list_url = function ($args = array()) use ($base_url, $form_filter) {
    if ($form_filter && !isset($args['form_id'])) $args['form_id'] = $form_filter;
    return add_query_arg(array_filter($args), $base_url);
};
$view_url = function ($id) use ($base_url) {
    return add_query_arg(array('action' => 'view', 'id' => intval($id)), $base_url);
};
$notices = array(
    'deleted'        => ucfirst($noun) . ' deleted.',
    'status_updated' => 'Message updated.',
);
?>
<div class="wrap wppb-wrap">
    <h1 class="wp-heading-inline"><?php echo $is_contact ? 'Messages' : 'Photo Bookings'; ?></h1>

    <?php $notice = (isset($_GET['notice']) && is_string($_GET['notice'])) ? sanitize_key($_GET['notice']) : '';
    if (isset($notices[$notice])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notices[$notice]); ?></p></div>
    <?php endif; ?>

    <!-- Status tabs -->
    <ul class="subsubsub">
        <?php
        $tabs = array('' => array('All', array_sum($counts)));
        foreach ($statuses as $st) {
            $tabs[$st] = array(ucfirst($st), $counts[$st]);
        }
        $last = array_key_last($tabs);
        foreach ($tabs as $st => $tab) : ?>
            <li>
                <a href="<?php echo esc_url($list_url(array('status' => $st))); ?>"<?php echo ($status_filter === $st) ? ' class="current"' : ''; ?>>
                    <?php echo esc_html($tab[0]); ?> <span class="count">(<?php echo intval($tab[1]); ?>)</span>
                </a><?php echo ($st !== $last) ? ' |' : ''; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <br class="clear">

    <!-- Search + filter + export toolbar -->
    <div class="wppb-list-toolbar">
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="wppb-search-form">
            <input type="hidden" name="page" value="<?php echo esc_attr($page); ?>">
            <?php if ($status_filter) : ?>
                <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
            <?php endif; ?>
            <?php if (count($forms) > 1) : ?>
                <select name="form_id">
                    <option value="0">All forms</option>
                    <?php foreach ($forms as $fid => $fname) : ?>
                        <option value="<?php echo intval($fid); ?>" <?php selected($form_filter, intval($fid)); ?>><?php echo esc_html($fname); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>"
                   placeholder="<?php echo $is_contact ? 'Search name, email or message…' : 'Search name or email…'; ?>" class="regular-text">
            <?php submit_button(count($forms) > 1 ? 'Filter' : 'Search', 'secondary', '', false); ?>
            <?php if ($search || $form_filter) : ?>
                <a href="<?php echo esc_url(add_query_arg(array_filter(array('status' => $status_filter)), $base_url)); ?>" class="button">Clear</a>
            <?php endif; ?>
        </form>

        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array_filter(array(
                'action'     => 'wppb_export_csv',
                'entry_type' => $entry_type,
                'status'     => $status_filter,
                'form_id'    => $form_filter,
            )), admin_url('admin-post.php')), 'wppb_export_csv')); ?>" class="button">
            &#8595; Export CSV
        </a>
    </div>

    <?php if (empty($bookings)) : ?>
        <p>No <?php echo esc_html($noun); ?>s found.</p>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped wppb-bookings-table">
        <thead>
            <tr>
                <th style="width:40px">#</th>
                <th>Name</th>
                <th>Email</th>
                <?php if ($is_contact) : ?>
                    <th>Form</th>
                    <th style="width:28%">Message</th>
                    <th>Received</th>
                <?php else : ?>
                    <th>Phone</th>
                    <th>Type</th>
                    <th>Date</th>
                <?php endif; ?>
                <th style="width:90px">Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bookings as $b) :
            $name = trim($b->first_name . ' ' . $b->last_name);
            if ($name === '') $name = $b->email !== '' ? $b->email : '(no name)';
            $unread = $is_contact && $b->status === 'new';
        ?>
            <tr<?php echo $unread ? ' class="wppb-unread"' : ''; ?>>
                <td><?php echo intval($b->id); ?></td>
                <td><strong><a href="<?php echo esc_url($view_url($b->id)); ?>"><?php echo esc_html($name); ?></a></strong></td>
                <td><?php echo esc_html($b->email); ?></td>
                <?php if ($is_contact) : ?>
                    <td><?php echo esc_html($form_names[$b->form_id] ?? '—'); ?></td>
                    <td><?php echo esc_html(wp_html_excerpt($b->message, 90, '…')); ?></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($b->created_at))); ?></td>
                <?php else : ?>
                    <td><?php echo esc_html($b->phone); ?></td>
                    <td><?php echo esc_html($b->photography_type); ?></td>
                    <td>
                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($b->booking_date))); ?>
                        <?php if (!empty($b->time_slot)) : ?>
                            <br><small><?php echo esc_html(date_i18n(get_option('time_format'), strtotime($b->booking_date . ' ' . $b->time_slot))); ?></small>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
                <td><span class="wppb-badge wppb-status-<?php echo esc_attr($b->status); ?>"><?php echo esc_html(ucfirst($b->status)); ?></span></td>
                <td class="wppb-actions">
                    <a href="<?php echo esc_url($view_url($b->id)); ?>" class="button button-small">View</a>

                    <?php if (!$is_contact && $b->status === 'pending') : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                            <?php wp_nonce_field('wppb_confirm_booking'); ?>
                            <input type="hidden" name="action" value="wppb_confirm_booking">
                            <input type="hidden" name="booking_id" value="<?php echo intval($b->id); ?>">
                            <button type="submit" class="button button-small button-primary">Confirm</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                            <?php wp_nonce_field('wppb_cancel_booking'); ?>
                            <input type="hidden" name="action" value="wppb_cancel_booking">
                            <input type="hidden" name="booking_id" value="<?php echo intval($b->id); ?>">
                            <button type="submit" class="button button-small">Cancel</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($is_contact && $b->status !== 'archived') : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                            <?php wp_nonce_field('wppb_set_entry_status'); ?>
                            <input type="hidden" name="action" value="wppb_set_entry_status">
                            <input type="hidden" name="booking_id" value="<?php echo intval($b->id); ?>">
                            <input type="hidden" name="status" value="archived">
                            <button type="submit" class="button button-small">Archive</button>
                        </form>
                    <?php endif; ?>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline" onsubmit="return confirm('Delete this <?php echo esc_js($noun); ?> permanently?')">
                        <?php wp_nonce_field('wppb_delete_booking'); ?>
                        <input type="hidden" name="action" value="wppb_delete_booking">
                        <input type="hidden" name="booking_id" value="<?php echo intval($b->id); ?>">
                        <button type="submit" class="button button-small wppb-delete-btn">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="wppb-list-count" style="color:#666;font-size:12px;margin-top:8px;"><?php echo count($bookings); ?> <?php echo esc_html($noun); ?><?php echo count($bookings) !== 1 ? 's' : ''; ?> shown.</p>
    <?php endif; ?>
</div>
