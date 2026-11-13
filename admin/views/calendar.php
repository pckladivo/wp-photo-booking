<?php if (!defined('ABSPATH')) die('Forbidden');

$today     = current_time('Y-m-d');
$cal_month = isset($_GET['cal_month']) ? intval($_GET['cal_month']) : intval(date('n'));
$cal_year  = isset($_GET['cal_year'])  ? intval($_GET['cal_year'])  : intval(date('Y'));

// Clamp values
if ($cal_month < 1)  { $cal_month = 12; $cal_year--; }
if ($cal_month > 12) { $cal_month = 1;  $cal_year++; }

$prev_month = $cal_month - 1;
$prev_year  = $cal_year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }

$next_month = $cal_month + 1;
$next_year  = $cal_year;
if ($next_month > 12) { $next_month = 1; $next_year++; }

$days_in_month = intval(date('t', mktime(0, 0, 0, $cal_month, 1, $cal_year)));
$first_dow     = intval(date('w', mktime(0, 0, 0, $cal_month, 1, $cal_year)));

// Fetch bookings for this month
$date_from = sprintf('%04d-%02d-01', $cal_year, $cal_month);
$date_to   = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $days_in_month);
$bookings  = WPPB_Booking::get_all(array('date_from' => $date_from, 'date_to' => $date_to));

// Group bookings by date
$by_date = array();
foreach ($bookings as $b) {
    $by_date[$b->booking_date][] = $b;
}

// Blocked dates
$blocked_dates = array_column(WPPB_Booking::get_blocked_dates(), 'blocked_date');

$base_url   = admin_url('admin.php?page=wppb-calendar');
$month_name = date_i18n('F Y', mktime(0, 0, 0, $cal_month, 1, $cal_year));
$day_labels = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
?>
<div class="wrap wppb-wrap">
    <h1 class="wp-heading-inline">Booking Calendar</h1>

    <div class="wppb-calendar-nav">
        <a href="<?php echo esc_url($base_url . '&cal_month=' . $prev_month . '&cal_year=' . $prev_year); ?>" class="button">&larr; Previous</a>
        <span class="wppb-calendar-title"><?php echo esc_html($month_name); ?></span>
        <a href="<?php echo esc_url($base_url . '&cal_month=' . $next_month . '&cal_year=' . $next_year); ?>" class="button">Next &rarr;</a>
        <?php if ($cal_month !== intval(date('n')) || $cal_year !== intval(date('Y'))) : ?>
            <a href="<?php echo esc_url($base_url); ?>" class="button" style="margin-left:8px;">Today</a>
        <?php endif; ?>
    </div>

    <table class="wppb-calendar-grid">
        <thead>
            <tr>
                <?php foreach ($day_labels as $dl) : ?>
                    <th><?php echo esc_html($dl); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
            <?php
            // Empty cells before the first day
            for ($i = 0; $i < $first_dow; $i++) {
                echo '<td class="wppb-cal-empty"></td>';
            }

            $cell = $first_dow;
            for ($day = 1; $day <= $days_in_month; $day++) {
                $date_str   = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $day);
                $is_today   = ($date_str === $today);
                $is_blocked = in_array($date_str, $blocked_dates);
                $day_bookings = isset($by_date[$date_str]) ? $by_date[$date_str] : array();

                $classes = array('wppb-cal-day');
                if ($is_today) $classes[] = 'wppb-cal-today';
                if ($is_blocked) $classes[] = 'wppb-cal-blocked';
                if (!empty($day_bookings)) $classes[] = 'wppb-cal-has-bookings';
                ?>
                <td class="<?php echo esc_attr(implode(' ', $classes)); ?>">
                    <div class="wppb-cal-day-num"><?php echo $day; ?></div>
                    <?php if ($is_blocked) : ?>
                        <span class="wppb-cal-blocked-label">Blocked</span>
                    <?php endif; ?>
                    <?php foreach ($day_bookings as $b) :
                        $status_class = 'wppb-cal-status-' . esc_attr($b->status);
                        $time_label   = !empty($b->time_slot) ? esc_html($b->time_slot) . ' ' : '';
                        $view_url     = admin_url('admin.php?page=wppb-bookings&action=view&id=' . $b->id);
                    ?>
                        <a href="<?php echo esc_url($view_url); ?>" class="wppb-cal-booking <?php echo $status_class; ?>" title="<?php echo esc_attr($b->first_name . ' ' . $b->last_name . ' — ' . ucfirst($b->status)); ?>">
                            <?php echo $time_label; ?><?php echo esc_html(mb_substr($b->first_name, 0, 1) . '. ' . $b->last_name); ?>
                        </a>
                    <?php endforeach; ?>
                </td>
                <?php
                $cell++;
                if ($cell % 7 === 0 && $day < $days_in_month) {
                    echo '</tr><tr>';
                }
            }

            // Empty cells after the last day
            $remaining = (7 - ($cell % 7)) % 7;
            for ($i = 0; $i < $remaining; $i++) {
                echo '<td class="wppb-cal-empty"></td>';
            }
            ?>
            </tr>
        </tbody>
    </table>

    <!-- Legend -->
    <div class="wppb-cal-legend">
        <span class="wppb-cal-legend-item"><span class="wppb-cal-dot wppb-cal-status-pending"></span> Pending</span>
        <span class="wppb-cal-legend-item"><span class="wppb-cal-dot wppb-cal-status-confirmed"></span> Confirmed</span>
        <span class="wppb-cal-legend-item"><span class="wppb-cal-dot wppb-cal-status-cancelled"></span> Cancelled</span>
        <span class="wppb-cal-legend-item"><span class="wppb-cal-dot wppb-cal-blocked-dot"></span> Blocked</span>
    </div>
</div>
