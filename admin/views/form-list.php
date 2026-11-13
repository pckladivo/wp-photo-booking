<?php if (!defined('ABSPATH')) die('Forbidden');
/**
 * Expects: $forms.
 */
$new_url = admin_url('admin.php?page=wppb-form-edit');
?>

<div class="wrap wppb-wrap">
    <h1 class="wp-heading-inline">Forms</h1>
    <a href="<?php echo esc_url($new_url); ?>" class="page-title-action">Add New Form</a>

    <?php
    $notice = isset($_GET['notice']) ? sanitize_text_field($_GET['notice']) : '';
    if ($notice === 'saved' || $notice === 'deleted') : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo $notice === 'saved' ? 'Form saved.' : 'Form deleted.'; ?></p>
        </div>
    <?php endif; ?>

    <p class="description" style="margin-top:12px;">
        <strong>Booking forms</strong> include a calendar and create bookings you confirm or cancel.
        <strong>Contact forms</strong> collect messages into the Messages inbox; they can also serve as the pre-session questionnaire.
        <strong>Custom forms</strong> start empty and take any field: with a booking date field they create bookings, without one they create messages.
    </p>

    <table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
        <thead>
            <tr>
                <th style="width:22%;">Name</th>
                <th style="width:10%;">Type</th>
                <th style="width:28%;">Shortcode</th>
                <th style="width:20%;">Notify Email</th>
                <th style="width:7%;">Fields</th>
                <th style="width:13%;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ( empty($forms) ) : ?>
            <tr>
                <td colspan="6">
                    No forms yet. <a href="<?php echo esc_url($new_url); ?>">Create your first form.</a>
                </td>
            </tr>
        <?php else : ?>
            <?php foreach ( $forms as $form ) :
                $fields     = WPPB_Forms::get_fields( $form );
                $fallback   = implode(', ', WPPB_Settings::get_photographer_emails());
                $type_names = array('contact' => 'Contact', 'booking' => 'Booking', 'custom' => 'Custom');
                $edit_url   = admin_url('admin.php?page=wppb-form-edit&id=' . $form->id);
            ?>
            <tr>
                <td><strong><a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html( $form->name ); ?></a></strong></td>
                <td><span class="wppb-badge wppb-type-<?php echo esc_attr($form->form_type); ?>"><?php echo esc_html( $type_names[$form->form_type] ?? $form->form_type ); ?></span>
                    <?php if ($form->form_type === WPPB_Forms::TYPE_CUSTOM) : ?>
                        <br><small><?php echo WPPB_Forms::creates_bookings($form) ? 'creates bookings' : 'creates messages'; ?></small>
                    <?php endif; ?></td>
                <td>
                    <code>[wppb_form id="<?php echo esc_html( $form->id ); ?>"]</code>
                </td>
                <td><?php echo esc_html( $form->notify_email ?: $fallback ); ?></td>
                <td><?php echo count( $fields ); ?></td>
                <td style="white-space:nowrap;">
                    <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">Edit</a>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>"
                          style="display:inline;"
                          onsubmit="return confirm('Delete this form? Entries already received are kept. This cannot be undone.');">
                        <?php wp_nonce_field('wppb_delete_form'); ?>
                        <input type="hidden" name="action"  value="wppb_delete_form">
                        <input type="hidden" name="form_id" value="<?php echo esc_attr( $form->id ); ?>">
                        <button type="submit" class="button button-small wppb-delete-btn">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
