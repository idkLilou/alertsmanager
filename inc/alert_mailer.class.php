<?php

/**
 * -------------------------------------------------------------------------
 * Alerts Manager plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Alerts Manager.
 *
 * Alerts Manager is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Alerts Manager is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Alerts Manager. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2024-2026 by Alerts Manager plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/idkLilou/alertsmanager
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    echo "Sorry. You can't access directly to this file";
    return;
}

class PluginAlertsmanagerOutlookMailer extends GLPIMailer
{
    private ?array $calendarInvite;

    public function __construct(?array $calendarInvite = null)
    {
        parent::__construct();
        $this->calendarInvite = $calendarInvite;
    }

    public function send()
    {
        if (
            is_array($this->calendarInvite)
            && !empty($this->calendarInvite['path'])
            && is_file((string) $this->calendarInvite['path'])
        ) {
            $this->getEmail()->attachFromPath(
                (string) $this->calendarInvite['path'],
                (string) ($this->calendarInvite['name'] ?? basename((string) $this->calendarInvite['path']))
            );
        }

        return parent::send();
    }
}

class PluginAlertsmanagerAlertMailer
{
    public static function sendAlert(int $alertId, array $context = []): array
    {
        $alert = new PluginAlertsmanagerAlert();
        if (!$alert->getFromDB($alertId)) {
            return [
                'success'    => false,
                'sent'       => 0,
                'recipients' => [],
                'errors'     => [sprintf('Alert %d not found', $alertId)],
            ];
        }

        return self::sendAlertItem($alert, $context);
    }

    public static function sendAlertItem(PluginAlertsmanagerAlert $alert, array $context = []): array
    {
        $context = self::buildNotificationContext($alert, $context);
        $recipientEmails = self::getRecipientEmails($alert);
        if ($recipientEmails === []) {
            return [
                'success'    => false,
                'sent'       => 0,
                'recipients' => [],
                'errors'     => ['No valid recipient email found'],
            ];
        }

        $sender = self::getSenderData($alert);
        $subject = self::renderTemplate((string) ($alert->fields['mail_subject'] ?? ''), $context);
        if ($subject === '') {
            $subject = trim((string) ($alert->fields['name'] ?? ''));
        }
        $subject = self::buildSubject($alert, $subject, $context);

        $bodyText = self::renderTemplate((string) ($alert->fields['mail_content'] ?? ''), $context);
        $bodyText = trim($bodyText);
        if ($bodyText === '') {
            $bodyText = trim((string) ($alert->fields['description'] ?? ''));
        }

        [$bodyText, $bodyHtml] = self::buildBody($alert, $bodyText, $context);

        $sent = 0;
        $errors = [];
        foreach ($recipientEmails as $recipientEmail) {
            $result = self::queueAndSend(
                $alert,
                $sender,
                $recipientEmail,
                $subject,
                $bodyText,
                $bodyHtml,
                $context
            );

            if ($result['success']) {
                $sent++;
                continue;
            }

            $errors[] = $recipientEmail . ': ' . $result['error'];
        }

        return [
            'success'    => $sent > 0 && $errors === [],
            'sent'       => $sent,
            'recipients' => $recipientEmails,
            'errors'     => $errors,
        ];
    }

    public static function getRecipientEmails(PluginAlertsmanagerAlert $alert): array
    {
        if (!method_exists($alert, 'getRecipientEmails')) {
            return [];
        }

        $emails = $alert->getRecipientEmails();
        if (!is_array($emails)) {
            return [];
        }

        $normalized = [];
        foreach ($emails as $email) {
            $email = trim((string) $email);
            if ($email === '') {
                continue;
            }

            if (class_exists('NotificationMailing') && !NotificationMailing::isUserAddressValid($email)) {
                continue;
            }

            $normalized[strtolower($email)] = $email;
        }

        return array_values($normalized);
    }

    public static function getSenderData(PluginAlertsmanagerAlert $alert): array
    {
        global $CFG_GLPI;

        $sender = [];
        if (class_exists('Config') && method_exists('Config', 'getEmailSender')) {
            $sender = (array) Config::getEmailSender();
        }

        $email = trim((string) ($sender['email'] ?? ''));
        $name = trim((string) ($sender['name'] ?? ''));

        if ($email === '') {
            $email = (string) ($CFG_GLPI['admin_email'] ?? '');
        }
        if ($name === '') {
            $name = (string) ($CFG_GLPI['admin_email_name'] ?? '');
        }
        if ($name === '') {
            $name = trim((string) ($alert->fields['name'] ?? ''));
        }

        return [
            'email' => $email,
            'name'  => $name,
        ];
    }

    private static function queueAndSend(
        PluginAlertsmanagerAlert $alert,
        array $sender,
        string $recipientEmail,
        string $subject,
        string $bodyText,
        string $bodyHtml,
        array $context
    ): array {
        $calendarInvite = self::buildCalendarInvite($alert, $sender, $recipientEmail, $subject, $context);

        try {
            if ($calendarInvite !== null) {
                NotificationEventMailing::setMailer(new PluginAlertsmanagerOutlookMailer($calendarInvite));
            }

            $mailing = new NotificationMailing();
            $queued = $mailing->sendNotification([
                '_itemtype'                 => PluginAlertsmanagerAlert::class,
                '_items_id'                 => (int) $alert->getID(),
                '_notificationtemplates_id' => 0,
                '_entities_id'              => (int) $alert->getEntityID(),
                'from'                      => (string) ($sender['email'] ?? ''),
                'fromname'                  => (string) ($sender['name'] ?? ''),
                'to'                        => $recipientEmail,
                'toname'                    => $recipientEmail,
                'subject'                   => $subject,
                'content_text'              => $bodyText,
                'content_html'              => $bodyHtml,
                'event'                     => (string) ($context['event'] ?? 'alertsmanager_send'),
            ]);

            if (!$queued) {
                return [
                    'success' => false,
                    'error'   => 'Unable to queue mail',
                ];
            }

            $queuedNotification = self::findLastQueuedNotification((int) $alert->getID(), $recipientEmail, $subject);
            if ($queuedNotification === null) {
                return [
                    'success' => false,
                    'error'   => 'Mail queued but queued notification could not be found',
                ];
            }

            if (!$queuedNotification->sendById((int) $queuedNotification->getID())) {
                return [
                    'success' => false,
                    'error'   => 'Mail queued but sending failed',
                ];
            }

            return [
                'success' => true,
                'error'   => '',
            ];
        } finally {
            NotificationEventMailing::setMailer(null);
            if (is_array($calendarInvite) && !empty($calendarInvite['path']) && is_file((string) $calendarInvite['path'])) {
                @unlink((string) $calendarInvite['path']);
            }
        }
    }

    private static function buildCalendarInvite(PluginAlertsmanagerAlert $alert, array $sender, string $recipientEmail, string $subject, array $context): ?array
    {
        $eventDate = self::extractCalendarEventDate($context);
        if ($eventDate === null) {
            return null;
        }

        $senderEmail = trim((string) ($sender['email'] ?? ''));
        $senderName = trim((string) ($sender['name'] ?? ''));

        $title = trim((string) ($context['item_name'] ?? $context['trigger_item_name'] ?? $alert->fields['name'] ?? $subject));
        if ($title === '') {
            $title = (string) $alert->fields['name'];
        }

        $descriptionParts = [];
        $entityName = trim((string) ($context['entity_name'] ?? ''));
        if ($entityName !== '') {
            $descriptionParts[] = sprintf('Entite: %s', $entityName);
        }
        $itemUrl = trim((string) ($context['item_url'] ?? $context['trigger_item_url'] ?? ''));
        if ($itemUrl !== '') {
            $descriptionParts[] = $itemUrl;
        }
        $descriptionParts[] = sprintf('Alerte envoyee a: %s', $recipientEmail);

        // create an all-day event: DTSTART/DTEND as dates (DTEND is non-inclusive, next day)
        $startDate = $eventDate->setTime(0, 0, 0);
        $endDate = $startDate->modify('+1 day');

        $inviteBody = self::renderIcs([
            'uid'         => sprintf('alertsmanager-%d-%s-%s', (int) $alert->getID(), sha1($recipientEmail), $eventDate->format('Ymd')),
            'title'       => $title,
            'description' => implode("\n", $descriptionParts),
            'start'       => $startDate,
            'end'         => $endDate,
            'all_day'     => true,
            'recipient'   => $recipientEmail,
            'organizer'   => $senderEmail,
            'organizer_name' => $senderName,
        ]);

        $filePath = GLPI_TMP_DIR . '/' . uniqid('alertsmanager_', true) . '.ics';
        if (@file_put_contents($filePath, $inviteBody) === false) {
            return null;
        }

        return [
            'path' => $filePath,
            'name' => self::sanitizeFilename($title) . '.ics',
        ];
    }

    private static function extractCalendarEventDate(array $context): ?DateTimeImmutable
    {
        foreach (['trigger_field_value', 'field_value', 'trigger_date', 'trigger_due_date', 'due_date'] as $key) {
            if (empty($context[$key])) {
                continue;
            }

            try {
                return new DateTimeImmutable((string) $context[$key]);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private static function renderIcs(array $data): string
    {
        $utc = new DateTimeZone('UTC');
        $recipient = trim((string) ($data['recipient'] ?? ''));
        $organizer = trim((string) ($data['organizer'] ?? ''));
        $organizerName = trim((string) ($data['organizer_name'] ?? ''));
        $organizerLabel = $organizerName !== '' ? $organizerName : $organizer;

        $lines = [
            'BEGIN:VCALENDAR',
            'PRODID:-//Alerts Manager//GLPI//FR',
            'VERSION:2.0',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            'UID:' . self::escapeIcsValue((string) $data['uid']),
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        ];

        // all-day event -> use DATE value type and mark for Microsoft Outlook
        if (!empty($data['all_day'])) {
            $lines[] = 'X-MICROSOFT-CDO-ALLDAYEVENT:TRUE';
            $lines[] = 'DTSTART;VALUE=DATE:' . $data['start']->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $data['end']->format('Ymd');
        } else {
            $lines[] = 'DTSTART:' . $data['start']->setTimezone($utc)->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . $data['end']->setTimezone($utc)->format('Ymd\THis\Z');
        }

        $lines = array_merge($lines, [
            'SUMMARY:' . self::escapeIcsValue((string) $data['title']),
            'DESCRIPTION:' . self::escapeIcsValue((string) $data['description']),
            ($organizer !== '' ? 'ORGANIZER;CN=' . self::escapeIcsValue($organizerLabel) . ':mailto:' . self::escapeIcsValue($organizer) : null),
            ($recipient !== '' ? 'ATTENDEE;CN=' . self::escapeIcsValue($recipient) . ';ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION:mailto:' . self::escapeIcsValue($recipient) : null),
            'STATUS:CONFIRMED',
            'TRANSP:OPAQUE',
            'BEGIN:VALARM',
            (!empty($data['all_day']) ? 'TRIGGER:-P0D' : 'TRIGGER:-PT0M'),
            'ACTION:DISPLAY',
            'DESCRIPTION:' . self::escapeIcsValue((string) $data['title']),
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        $lines = array_values(array_filter($lines, static fn($line) => is_string($line) && $line !== ''));

        return implode("\r\n", $lines) . "\r\n";
    }

    private static function escapeIcsValue(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(["\r\n", "\r", "\n"], '\\n', $value);
        $value = str_replace(';', '\\;', $value);
        $value = str_replace(',', '\\,', $value);

        return $value;
    }

    private static function sanitizeFilename(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value);
        $value = trim((string) $value, '-_.');

        return $value !== '' ? $value : 'outlook-invite';
    }

    private static function findLastQueuedNotification(int $alertId, string $recipientEmail, string $subject): ?QueuedNotification
    {
        global $DB;

        $rows = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => QueuedNotification::getTable(),
            'WHERE'  => [
                'itemtype'  => PluginAlertsmanagerAlert::class,
                'items_id'  => $alertId,
                'recipient' => $recipientEmail,
                'name'      => $subject,
            ],
            'ORDER'  => ['id DESC'],
            'LIMIT'  => 1,
        ]);

        foreach ($rows as $row) {
            $queuedNotification = new QueuedNotification();
            if ($queuedNotification->getFromDB((int) ($row['id'] ?? 0))) {
                return $queuedNotification;
            }
        }

        return null;
    }

    private static function renderTemplate(string $value, array $context): string
    {
        if ($value === '' || $context === []) {
            return $value;
        }

        $replacements = [];
        foreach ($context as $key => $contextValue) {
            if (is_scalar($contextValue) || $contextValue === null) {
                $replacements['{{' . $key . '}}'] = (string) $contextValue;
                $replacements['##' . $key . '##'] = (string) $contextValue;
            }
        }

        return strtr($value, $replacements);
    }

    private static function buildNotificationContext(PluginAlertsmanagerAlert $alert, array $context): array
    {
        $context['alert_name'] = trim((string) ($alert->fields['name'] ?? ''));
        $context['alert_subject'] = trim((string) ($alert->fields['mail_subject'] ?? ''));

        if (!isset($context['trigger_item_name'])) {
            $context['trigger_item_name'] = trim((string) ($context['trigger_item_name'] ?? ''));
        }

        if (!isset($context['trigger_item_url'])) {
            $context['trigger_item_url'] = trim((string) ($context['trigger_item_url'] ?? ''));
        }

        if (!isset($context['trigger_item_type_label'])) {
            $context['trigger_item_type_label'] = trim((string) ($context['trigger_item_type_label'] ?? ''));
        }

        if (!isset($context['entity_name'])) {
            $context['entity_name'] = trim((string) ($context['entity_name'] ?? ''));
        }

        return $context;
    }

    private static function buildSubject(PluginAlertsmanagerAlert $alert, string $subject, array $context): string
    {
        $prefix = sprintf('[Alerte %s]', trim((string) ($alert->fields['name'] ?? '')));
        $subject = trim($subject);

        if ($subject === '') {
            return $prefix;
        }

        return $prefix . ' ' . $subject;
    }

    private static function buildBody(PluginAlertsmanagerAlert $alert, string $bodyText, array $context): array
    {
        $bodyText = trim($bodyText);
        $itemName = trim((string) ($context['trigger_item_name'] ?? ''));
        $itemUrl = trim((string) ($context['trigger_item_url'] ?? ''));
        $entityName = trim((string) ($context['entity_name'] ?? ''));
        $alertName = trim((string) ($alert->fields['name'] ?? ''));

        $headerParts = [];
        if ($itemName !== '') {
            $headerParts[] = sprintf('[Alerte sur : %s]', $itemName);
        } else {
            $headerParts[] = sprintf('[Alerte %s]', $alertName);
        }

        if ($entityName !== '') {
            $headerParts[] = sprintf('Entite: %s', $entityName);
        }

        if ($itemUrl !== '') {
            $headerParts[] = $itemUrl;
        }

        $headerText = implode(' - ', $headerParts);
        $headerHtml = '<div style="margin:0 0 16px;padding:16px 18px;border:1px solid #dbe1ea;border-radius:14px;background:#f8fbff;">';
        $headerHtml .= '<div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#637085;">Alerte</div>';
        $headerHtml .= '<div style="margin-top:6px;font-size:20px;line-height:1.35;font-weight:700;color:#1f2937;">' . htmlescape($headerParts[0]) . '</div>';

        if ($entityName !== '' || $alertName !== '') {
            $headerHtml .= '<div style="margin-top:8px;font-size:13px;line-height:1.5;color:#4b5563;">';
            if ($entityName !== '') {
                $headerHtml .= '<span style="display:inline-block;margin:0 8px 8px 0;padding:3px 8px;border-radius:999px;background:#e8eef7;color:#29415f;font-weight:600;">' . htmlescape(sprintf('Entite: %s', $entityName)) . '</span>';
            }
            if ($alertName !== '') {
                $headerHtml .= '<span style="display:inline-block;margin:0 8px 8px 0;padding:3px 8px;border-radius:999px;background:#eef6ea;color:#315a27;font-weight:600;">' . htmlescape($alertName) . '</span>';
            }
            $headerHtml .= '</div>';
        }

        if ($itemUrl !== '') {
            $label = $itemName !== '' ? $itemName : $itemUrl;
            $headerHtml .= '<div style="margin-top:10px;">';
            $headerHtml .= '<a href="' . htmlescape($itemUrl) . '" target="_blank" style="display:inline-block;padding:10px 14px;border-radius:10px;background:#0f62fe;color:#ffffff;text-decoration:none;font-weight:600;">';
            $headerHtml .= htmlescape(sprintf('Ouvrir %s', $label));
            $headerHtml .= '</a>';
            $headerHtml .= '</div>';
        }

        $headerHtml .= '</div>';

        $contentHtml = self::renderMailContentHtml($bodyText);
        $contentText = self::renderMailContentText($bodyText);

        $html = $headerHtml;
        if ($contentHtml !== '') {
            $html .= '<div style="padding:0 2px;line-height:1.6;color:#1f2937;">' . $contentHtml . '</div>';
        }

        $text = $headerText;
        if ($contentText !== '') {
            $text .= "\n\n" . $contentText;
        }

        return [$text, $html];
    }

    private static function renderMailContentHtml(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (strip_tags($decoded) !== $decoded) {
            return $decoded;
        }

        return nl2br(htmlescape($decoded));
    }

    private static function renderMailContentText(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $decoded);
        $decoded = preg_replace('/<\/p\s*>/i', "\n\n", $decoded);
        $decoded = strip_tags($decoded);
        $decoded = preg_replace('/\r\n?/', "\n", (string) $decoded);
        $decoded = preg_replace('/\n{3,}/', "\n\n", (string) $decoded);

        return trim((string) $decoded);
    }
}