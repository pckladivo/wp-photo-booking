# WP Photo Booking 1.10.0 — how to use and test it

The plugin lets you build three kinds of forms and put them on any page:

- **Booking forms** have a calendar. Each submission becomes a booking that reserves its date until you confirm or cancel it.
- **Contact forms** have no date. Each submission becomes a message in an inbox.
- **Custom forms** start empty and take any field. With a booking date field they behave like a booking form, without one like a contact form.

You choose the type under Forms → **Add New Form**. It cannot be changed after the form is created.

Everything lives under **Photo Bookings** in the WordPress admin menu:

| Menu item | What it is for |
|---|---|
| All Bookings | List of bookings; confirm, cancel, add notes, export |
| Messages | Inbox for contact form submissions |
| Calendar | Month view of bookings and blocked dates |
| Settings | Availability, time slots, reCAPTCHA, global email templates, automation |
| Forms | All your forms and their shortcodes |

---

## 1. Install or upgrade

1. Back up the database if the site already has bookings.
2. Plugins → Add New → Upload Plugin → choose `wp-photo-booking-1.10.0.zip` → Install.
3. If an older version is installed, WordPress shows a comparison screen. Click **Replace current with uploaded**.
4. Activate the plugin if it is not active.

After an upgrade, check two things:

- **All Bookings** still shows your existing bookings.
- **Forms** still lists your forms. Nothing is created automatically: a new site starts with no forms, and every form can be deleted.

The `[photo_booking]` shortcode no longer exists (since 1.10.0). Every form, booking forms included, is placed with its own `[wppb_form id="…"]` shortcode.

Emails are sent through WordPress. On a test site, install a mail logging plugin (for example WP Mail Logging) so you can read what would have been sent.

---

## 2. Set up availability (booking forms only)

Go to **Settings → General**. These settings are shared by all booking forms, because they describe one calendar.

| Setting | Effect |
|---|---|
| Photographer Email | Receives notifications. Empty means the WordPress admin email. Several addresses can be entered, separated by commas (up to 5): the first is the main address and the sender of all emails, the others get a blind copy (Bcc). |
| Minimum Days Ahead | Earliest bookable day, counted from today. The minimum is 1 (tomorrow). |
| Working Days | Only these weekdays can be picked. |
| Time Slots | Empty: one booking per day; the form's Time field then asks only for a preferred time. Filled (e.g. `09:00, 14:00`): the client also picks a time, and a day is full only when every slot is taken. |
| Submission Limit | How many submissions one visitor may send in 10 minutes, across all forms. Default 5; `0` switches it off. |
| reCAPTCHA keys | Optional. Leave both empty to switch it off everywhere. |

**Settings → Blocked Dates** lets you close single days (holidays, private days).

A date is taken while a booking on it is *pending* or *confirmed*. Cancelling the booking frees it again.

---

## 3. The booking form

### Put it on a page

Create one under **Forms → Add New Form**, type **Booking**. It starts with a suggested set of fields that you can change freely. After saving, add its shortcode (shown in the Forms list) to a page, for example:

```
[wppb_form id="3"]
```

### Change it

1. Forms → click **Booking**.
2. Edit the fields (see section 5), the button text and the success message.
3. Click **Save Form**.

The dropdown "I am interested in" holds the photography types. Edit its **Options** box, one type per line. This replaces the old "Photography Types" setting.

### Add another booking form

Forms → **Add New Form**, then choose **Booking form** as the type. It starts with the standard fields. All booking forms share the same calendar, so a date booked through one form is taken in the others too.

---

## 4. A contact form

1. Forms → **Add New Form**. Leave the type on **Contact form**.
2. Enter a **Form Name** (only you see it).
3. The form starts with Name, Email and Message. Add or change fields as needed.
4. Click **Create Form**.
5. Copy the shortcode shown at the top, e.g. `[wppb_form id="2"]`, into a page.

Submissions appear under **Messages**.

### A custom form

1. Forms → **Add New Form**, choose **Custom form**.
2. The form starts with no fields. Add whatever you need with **+ Add Field**.
3. Decide what a submission should be:
   - Add a **Booking date (calendar)** field and submissions become bookings: the date is reserved, the entry appears under All Bookings, and you confirm or cancel it.
   - Leave it out and submissions appear under Messages.
4. Click **Create Form** and use the shortcode shown at the top.

The form editor and the Forms list show which of the two a custom form currently creates. Adding or removing the date field later changes it for new submissions only; earlier entries stay where they are.

The auto-reply to the client is off by default on custom forms; enable it in the Emails section if you want it.

---

## 5. Fields

Click **+ Add Field**, choose a type in the dropdown at the top left of the row, and fill in the rest. Drag the handle or use the arrows to reorder. **× Remove** deletes the field.

### Field types

| Type | What the visitor sees |
|---|---|
| Text, Email, Phone, Number | Single-line input. Email and Number are checked for a valid value. |
| Textarea | Multi-line text |
| Dropdown, Radio, Checkboxes | A choice from the **Options** box (one per line). Checkboxes allow several. |
| Consent checkbox | One checkbox with your text, e.g. for a privacy policy. Links are allowed: `I agree to the <a href="/privacy">privacy policy</a>`. |
| Section heading | A heading between groups of fields; not an input |
| Booking date (calendar) | The availability calendar. Booking and custom forms, one per form, always required. |
| Time | Booking and custom forms. With time slots set in Settings: buttons for the free slots of the chosen day, always required. Without: a plain time input for the client's preferred time, required only if you tick Required. |

### Options per field

| Option | Meaning |
|---|---|
| Label | Text above the field |
| Placeholder | Grey hint inside the field |
| Saves as | What the field means (see below) |
| Width | Full row, or Half so that two fields sit side by side |
| Required | The form cannot be sent with this field empty |

### Layout and size

- **Two fields on one line:** set **Width** to Half on both and place them next to each other in the field order. On phones they stack.
- **Field height** and **Space between fields** are set per form, in the **Appearance** row near the top of the form editor, in pixels. Leave them empty for the default. The height applies to single-line fields and dropdowns, not to text areas.
- **Submit button:** the **Button** row sets its style (Outline, Filled, or Theme default), an optional colour, its position (left, centre, right) and full width. Without a colour it uses the page's text colour. With a field height set, the button is as tall as the fields.
- Fonts, field colours and borders come from your theme. Dropdowns and time inputs copy the look of the form's text fields.

### "Saves as"

This tells the plugin what a field means. Choose one of: First name (or full name), Last name, Client email, Phone, Type / interest, Message. Each can be used once per form.

It matters for three things:

- The name and email appear in the bookings and messages lists.
- **Client email** is where the auto-reply goes, and where your reply lands when you answer a notification.
- The value becomes available in emails as `{first_name}`, `{client_email}` and so on.

A field left on **Extra field** is still saved and shown in the entry's detail view, in the CSV export and in `{all_fields}`.

---

## 6. Emails

Each form has its own email section at the bottom of the form editor.

**Notification to you**

- **Send To**: empty means the Photographer Email from Settings. Several comma-separated addresses are allowed here too (first = recipient, others = Bcc); they replace the Settings addresses for this form.
- **Subject** and **Body**: empty means the default.
- Replying to the notification writes directly to the client.

**Auto-reply to the client**

- Tick **Enable** to send an acknowledgement. It is on by default for booking forms and off for contact forms.
- It needs a field that saves as **Client email**.

An empty template uses the built-in text, shown in grey inside the empty field. The emails sent on submission are set only here, per form; **Settings → Email Templates** holds the confirmation and cancellation emails, and **Settings → Automation** the reminders, which apply to all bookings.

### Tokens

Put these in a subject or body; they are replaced with the submitted values.

| Token | Value |
|---|---|
| `{first_name}` `{last_name}` `{client_email}` `{phone}` | Fields with the matching "Saves as" |
| `{photography_type}` `{message}` | Type / interest and Message |
| `{booking_date}` `{time_slot}` | Booking forms only |
| `{site_name}` `{form_name}` | Site title and the form's name |
| `{all_fields}` | Every submitted field as "Label: value" lines |
| `{field:Label}` | One field by its label, e.g. `{field:Topic}` |

Bodies can be plain text or HTML.

---

## 7. Working with bookings

**All Bookings** lists bookings with tabs for Pending, Confirmed and Cancelled.

- **Confirm** or **Cancel** a pending booking from the list or from its detail view. Both send emails to the client and to you.
- **View** shows all submitted fields, an admin notes box and an activity log.
- **Export CSV** downloads the current list.
- **Calendar** shows the month with bookings and blocked dates.

## 8. Working with messages

**Messages** lists contact submissions, newest first. The menu shows the number of new ones.

- Opening a message marks it as **read**.
- **Reply by Email** opens your mail program addressed to the client.
- **Archive** moves it out of the way; **Move Back to Inbox** restores it. **Mark as New** flags it as unread again.
- If you have several contact forms, a dropdown filters the list by form.
- **Export CSV** includes the extra fields as their own columns.

---

## 9. Optional features

### Pre-session questionnaire

1. Build the questionnaire as a **contact form** under Forms.
2. Settings → Automation → Pre-Session Questionnaire: enable it, select the form, set the number of days before the session.
3. A confirmed booking gets an email with a personal link that many days before the session.

To test without waiting: open a **confirmed** booking. Its detail view shows the questionnaire link; open it in a private browser window and submit. The answers then appear in the booking's detail view.

### Reminders

Settings → Automation also has a session reminder and a follow-up for bookings still pending after some days. They run once a day through WP-Cron.

### Translations (Polylang)

With Polylang active, every text typed in the form editor (labels, placeholders, options, button text, success message, auto-reply) appears under Languages → String translations, in the group **WP Photo Booking**. One form then serves all languages. This has not been tested yet.

---

## 10. Privacy (GDPR)

Entries contain personal data. The plugin gives you these tools; whether your use of them is lawful (legal basis, privacy policy, retention periods) remains your decision.

| Need | Where |
|---|---|
| Someone asks for a copy of their data | Tools → Export Personal Data. Enter their email address; their bookings, messages and questionnaire answers are included in the export file. |
| Someone asks to be deleted | Tools → Erase Personal Data. Their bookings and messages are deleted, with their activity and email log. A deleted booking frees its date. |
| Delete old entries automatically | Settings → General → Privacy: **Delete Messages After** (days since received) and **Delete Bookings After** (days since the session date). `0` keeps them. Runs once a day and cannot be undone. |
| Text for your privacy policy | Settings → Privacy → policy guide has a suggested section "WP Photo Booking". Adapt it before publishing. |
| Record consent | Add a **Consent checkbox** field to the form and mark it required. The entry stores that it was ticked, with the submission time. |

What the plugin stores and does not store:

- It stores what the visitor typed, the submission time, your admin notes and a log of status changes and emails sent.
- It does not store visitor IP addresses. Addresses recorded by earlier versions are removed on upgrade.
- For the submission limit it keeps a scrambled value derived from the visitor's address for ten minutes.
- With reCAPTCHA enabled, the visitor's browser contacts Google. Mention this in your privacy policy.

Limits to know:

- Export and erase find a person by email address. A form with no email field cannot be matched; delete such entries by hand from the list.
- Your internal admin notes are not included in the export file, but they are deleted with the entry.
- Emails already sent to you and to the client, and CSV files you exported, are outside the plugin's reach.
- Uninstalling the plugin (Plugins → Delete) removes all its tables and settings.

---

## 11. Test checklist

Work through these on a test page. Use a private browser window for the visitor side.

**Booking form**

- [ ] The page with the booking form's `[wppb_form id="…"]` shortcode shows the form; first and last name sit side by side.
- [ ] Weekends (or your non-working days), blocked dates and days before the minimum cannot be picked.
- [ ] Sending the form empty shows which fields are missing.
- [ ] A valid submission shows the success message and hides the form.
- [ ] The booking appears under All Bookings as *pending*, with all fields in its detail view.
- [ ] Two emails are sent: one to the client, one to you. Replying to yours addresses the client.
- [ ] The booked date can no longer be picked on the form.
- [ ] Confirm the booking: status changes and confirmation emails are sent.
- [ ] Cancel another booking: its date becomes available again.

**Time slots**

- [ ] Enter `09:00, 14:00` under Settings → Time Slots.
- [ ] After picking a date, two time buttons appear; the form cannot be sent without choosing one.
- [ ] Book 09:00, reload, pick the same date: only 14:00 is offered.
- [ ] Book 14:00 as well: the date is now disabled in the calendar.

**Editing a form**

- [ ] Rename a label, change the button text and the success message; the page shows the changes.
- [ ] Add an extra Text field and a Consent checkbox, mark both required; the form refuses to send without them.
- [ ] Submit: the extra field appears in the booking's detail view and in the CSV export.
- [ ] Drag a field to a new position; the order on the page follows.
- [ ] Change the Button style to Filled, pick a colour, set Position to Centre: the page follows. Theme default hands the button back to the theme.
- [ ] Set Field height to 36 and Space between fields to 8: the form becomes compact. Clear both: it returns to the default.

**Contact form**

- [ ] Create a contact form, place its shortcode on a page.
- [ ] An invalid email address is rejected.
- [ ] A valid submission shows the success message; the message appears under Messages as *new* and the menu count goes up.
- [ ] Opening it marks it *read*; Archive and Move Back to Inbox work.
- [ ] Enable the auto-reply, set a subject with `{first_name}`, submit again: the client receives it with the name filled in.
- [ ] A contact submission does not block any date in the booking calendar.

**Custom form**

- [ ] Create a custom form with a few fields and no date: a submission appears under Messages.
- [ ] Add a Booking date field and save: the editor says the form now creates bookings, and the page shows the calendar.
- [ ] A submission now appears under All Bookings as *pending*, and its date is taken on the other booking forms too.

**Privacy**

- [ ] Submit a booking and a message with the same email address. Tools → Export Personal Data for that address: the export file lists both.
- [ ] Tools → Erase Personal Data for that address: both entries are gone and the booked date is free again.
- [ ] Set "Delete Messages After" to 1 day; a message older than that is removed at the next daily run.

**Spam protection**

- [ ] With reCAPTCHA keys set, the checkbox appears and the form cannot be sent without it.
- [ ] Untick "Require reCAPTCHA on this form" for one form: it disappears there only.
- [ ] Send a form six times in a row: the sixth is refused with "Too many submissions". While testing, raise the Submission Limit in Settings or set it to 0.

---

## 12. If something does not work

| Symptom | Likely cause |
|---|---|
| "Form not found." on the page | Wrong `id` in the shortcode, or the form was deleted. Check the shortcode in the Forms list. |
| The form shows an error right after clicking the button, on every try | The page is served from a cache with an expired security token. Exclude the page from caching or clear the cache. |
| "Too many submissions. Please try again later." | The submission limit was reached (default 5 per visitor in 10 minutes). Wait, or raise it under Settings → General. If every visitor gets this, the site is behind a proxy or CDN that hides visitor addresses: raise the limit or set it to 0. |
| "… is too long" | Single-line fields accept 300 characters, text areas 5000. |
| No emails arrive | WordPress cannot send mail on this server. Install an SMTP plugin; use a mail log to see what was generated. |
| Auto-reply is not sent | The form has no field that saves as **Client email**, or the auto-reply is not enabled. |
| The Time field is a plain time input instead of buttons | No time slots are set under Settings → General. The time is then only the client's preference and does not limit bookings: it is still one booking per day. |
| The form looks unstyled or cramped | The plugin leaves fonts, colours and borders to the theme. Old theme CSS that targeted `#wppb-booking-form` or `#wppb-date` no longer applies; those IDs were replaced by classes such as `.wppb-form`, `.wppb-field`, `.wppb-date`. |
