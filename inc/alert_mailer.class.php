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

        $bodyText = self::renderTemplate((string) ($alert->fields['mail_content'] ?? ''), $context);
        $bodyText = trim($bodyText);
        if ($bodyText === '') {
            $bodyText = trim((string) ($alert->fields['description'] ?? ''));
        }

        $bodyHtml = nl2br(htmlescape($bodyText));

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
}