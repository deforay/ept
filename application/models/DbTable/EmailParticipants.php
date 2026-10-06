<?php

/**
 * One row per email written on Email Participants: the subject and message as
 * typed, the shipment ids (shipment_code) and the audiences (receivers). The
 * recipients are worked out when the email is queued, not when it is saved.
 *
 * scheduled_at holds wall-clock time in the application timezone. Due checks
 * compare it against PHP's clock, which runs in that timezone on web and CLI
 * alike, rather than MySQL's NOW(), whose timezone can differ.
 */
class Application_Model_DbTable_EmailParticipants extends Zend_Db_Table_Abstract
{
    protected $_name = 'email_participants';
    protected $_primary = 'id';

    /**
     * Not yet queued. 'paused' alone is not enough to allow an edit: an email
     * paused partway through sending is also 'paused', but has dispatched_at
     * set and its messages already written.
     */
    public const EDITABLE_STATUSES = ['scheduled', 'paused'];

    /**
     * Saves a new email. With $scheduledAt it waits as 'scheduled'; without
     * it, it is saved as 'dispatching' for the caller to queue straight away.
     */
    public function createEmail(array $data, ?string $scheduledAt, string $adminEmail): int
    {
        return (int) $this->insert([
            'subject'        => (string) $data['subject'],
            'content'        => (string) $data['message'],
            'receivers'      => implode(',', (array) $data['sendMail']),
            'shipment_code'  => implode(',', (array) $data['shipments']),
            'status'         => $scheduledAt !== null ? 'scheduled' : 'dispatching',
            'scheduled_at'   => $scheduledAt,
            'initiated_by'   => $adminEmail,
            'date_initiated' => new Zend_Db_Expr('NOW()'),
        ]);
    }

    /**
     * Rewrites a scheduled or paused email. Saving it with a send time puts a
     * paused email back on the schedule, since that time is what the admin
     * just confirmed. Returns false when it has already been queued or
     * cancelled, so a save racing the dispatch job changes nothing.
     */
    public function updateScheduledEmail(int $id, array $data, string $scheduledAt, string $adminEmail): bool
    {
        return $this->update([
            'status'        => 'scheduled',
            'subject'       => (string) $data['subject'],
            'content'       => (string) $data['message'],
            'receivers'     => implode(',', (array) $data['sendMail']),
            'shipment_code' => implode(',', (array) $data['shipments']),
            'scheduled_at'  => $scheduledAt,
            'updated_by'    => $adminEmail,
            'updated_at'    => new Zend_Db_Expr('NOW()'),
        ], $this->editableWhere($id)) === 1;
    }

    /**
     * Moves an email from one of $from to $to. Only one caller can win the
     * move, which is what keeps two dispatch runs from queuing it twice.
     */
    public function changeStatus(int $id, array $from, string $to, ?string $adminEmail = null, array $extra = []): bool
    {
        $set = array_merge(['status' => $to], $extra);
        if ($adminEmail !== null) {
            $set['updated_by'] = $adminEmail;
            $set['updated_at'] = new Zend_Db_Expr('NOW()');
        }
        $where = [
            'id = ?' => $id,
            'status IN (?)' => $from,
        ];
        return $this->update($set, $where) === 1;
    }

    /** @return int[] ids of scheduled emails whose time has come, oldest first */
    public function fetchDueIds(string $now): array
    {
        $sql = $this->getAdapter()->select()
            ->from($this->_name, ['id'])
            ->where('status = ?', 'scheduled')
            ->where('scheduled_at <= ?', $now)
            ->order(['scheduled_at ASC', 'id ASC']);
        return array_map('intval', $this->getAdapter()->fetchCol($sql));
    }

    public function fetchEmail(int $id): ?array
    {
        $row = $this->getAdapter()->fetchRow(
            $this->getAdapter()->select()->from($this->_name)->where('id = ?', $id)
        );
        return $row ?: null;
    }

    /**
     * Emails for the Scheduled or History tab, newest first, each with its
     * shipment codes and how many of its queued messages are in each state.
     * Message counts thin out after 30 days as housekeeping prunes sent mail;
     * queued_count keeps the original total.
     *
     * @param 'scheduled'|'history' $view
     */
    public function fetchEmailList(string $view, int $limit = 200): array
    {
        $db = $this->getAdapter();
        $sql = $db->select()->from($this->_name);
        if ($view === 'scheduled') {
            // Waiting, or queued and still going out
            $sql->where("status IN ('scheduled', 'paused', 'dispatching')
                OR (status = 'queued' AND EXISTS (SELECT 1 FROM temp_mail tm
                    WHERE tm.email_participant_id = email_participants.id
                    AND tm.status IN ('pending', 'picked-to-process', 'held')))")
                ->order(['scheduled_at ASC', 'id ASC']);
        } else {
            $sql->where("status IN ('queued', 'cancelled', 'failed')")->order('id DESC');
        }
        $rows = $db->fetchAll($sql->limit($limit));
        if (empty($rows)) {
            return [];
        }

        $ids = array_column($rows, 'id');
        $progress = [];
        $counts = $db->fetchAll(
            $db->select()->from('temp_mail', ['email_participant_id', 'status', 'n' => new Zend_Db_Expr('COUNT(*)')])
                ->where('email_participant_id IN (?)', $ids)
                ->group(['email_participant_id', 'status'])
        );
        foreach ($counts as $c) {
            $progress[$c['email_participant_id']][$c['status']] = (int) $c['n'];
        }

        $shipmentIds = [];
        foreach ($rows as $row) {
            foreach (explode(',', (string) $row['shipment_code']) as $sid) {
                if (ctype_digit(trim($sid))) {
                    $shipmentIds[(int) $sid] = true;
                }
            }
        }
        $codes = $shipmentIds
            ? $db->fetchPairs($db->select()->from('shipment', ['shipment_id', 'shipment_code'])
                ->where('shipment_id IN (?)', array_keys($shipmentIds)))
            : [];

        foreach ($rows as &$row) {
            $row['progress'] = $progress[$row['id']] ?? [];
            $row['shipment_codes'] = [];
            foreach (explode(',', (string) $row['shipment_code']) as $sid) {
                $sid = trim($sid);
                if ($sid !== '') {
                    $row['shipment_codes'][] = $codes[$sid] ?? $sid;
                }
            }
        }
        return $rows;
    }

    /**
     * Holds ('pending' → 'held') or releases ('held' → 'pending') the queued
     * messages of an email that is partway out. A message the mail job has
     * already picked up is past stopping and goes out either way.
     */
    public function moveQueuedMessages(int $id, array $from, string $to): int
    {
        $set = ['status' => $to, 'updated_at' => new Zend_Db_Expr('NOW()')];
        if ($to === 'cancelled') {
            $set['failure_reason'] = 'Cancelled by an admin before it was sent';
        }
        return $this->getAdapter()->update('temp_mail', $set, [
            'email_participant_id = ?' => $id,
            'status IN (?)' => $from,
        ]);
    }

    private function editableWhere(int $id): array
    {
        return [
            'id = ?' => $id,
            'status IN (?)' => self::EDITABLE_STATUSES,
            'dispatched_at IS NULL',
        ];
    }
}
