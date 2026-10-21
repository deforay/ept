<?php

declare(strict_types=1);

namespace EptTestHarness\Reports;

use EptTestHarness\Config;
use EptTestHarness\Db;

/**
 * Lets bin/reports generate real reports and leave the database and the
 * downloads folder as it found them.
 *
 * generate-shipment-reports.php writes to shipment, the report_generated flag
 * of shipment_participant_map and queue_report_generation, and adds rows to
 * notify, temp_mail (participant notices) and audit_log. Restoring uses UPDATE
 * and deletes only rows added during the run. It never uses REPLACE, which
 * MySQL runs as a delete and would trip or cascade foreign keys.
 */
final class Sandbox
{
    /** Tables the run appends to, with their primary key and creation time column */
    private const APPENDED = [
        'notify'                  => ['id', 'created_on'],
        'temp_mail'               => ['temp_id', 'queued_on'],
        'audit_log'               => ['audit_log_id', 'created_on'],
        'queue_report_generation' => ['id', 'requested_on'],
    ];

    /** @var array<int, array<string, mixed>> */
    private array $shipments = [];
    /** @var array<int, string|null> map_id => report_generated */
    private array $reportFlags = [];
    /** @var array<int, array<string, mixed>> */
    private array $queueRows = [];
    /** @var array<string, int> */
    private array $maxIds = [];
    private string $startedAt = '';
    /** @var array<string, string> report path => where it was set aside */
    private array $setAside = [];

    /** @param int[] $shipmentIds */
    public function __construct(private Db $db, private Config $config, private array $shipmentIds, private string $workDir) {}

    /** @return array<int, string> shipment_id => shipment_code */
    public function shipmentCodes(): array
    {
        return array_map(static fn (array $row): string => (string) $row['shipment_code'], $this->shipments);
    }

    public function snapshot(): void
    {
        $in = implode(',', array_map('intval', $this->shipmentIds));
        foreach ($this->db->all("SELECT * FROM shipment WHERE shipment_id IN ($in)") as $row) {
            $this->shipments[(int) $row['shipment_id']] = $row;
        }
        foreach ($this->db->all("SELECT map_id, report_generated FROM shipment_participant_map WHERE shipment_id IN ($in)") as $row) {
            $this->reportFlags[(int) $row['map_id']] = $row['report_generated'];
        }
        foreach ($this->db->all('SELECT * FROM queue_report_generation') as $row) {
            $this->queueRows[(int) $row['id']] = $row;
        }
        foreach (self::APPENDED as $table => [$key]) {
            $this->maxIds[$table] = (int) $this->db->scalar("SELECT COALESCE(MAX($key), 0) FROM $table");
        }
        $this->startedAt = (string) $this->db->scalar('SELECT NOW()');
    }

    public function restore(): void
    {
        foreach (self::APPENDED as $table => [$key, $createdColumn]) {
            $this->db->exec(
                "DELETE FROM $table WHERE $key > ? AND ($createdColumn IS NULL OR $createdColumn >= ?)",
                [$this->maxIds[$table], $this->startedAt]
            );
        }
        foreach ($this->queueRows as $id => $row) {
            $this->updateRow('queue_report_generation', 'id', $id, $row);
        }
        foreach ($this->shipments as $id => $row) {
            $this->updateRow('shipment', 'shipment_id', $id, $row);
        }
        $byValue = [];
        foreach ($this->reportFlags as $mapId => $flag) {
            $byValue[$flag === null ? "\0null" : $flag][] = $mapId;
        }
        foreach ($byValue as $flag => $mapIds) {
            foreach (array_chunk($mapIds, 1000) as $chunk) {
                $marks = implode(',', array_fill(0, count($chunk), '?'));
                $this->db->exec(
                    "UPDATE shipment_participant_map SET report_generated = ? WHERE map_id IN ($marks)",
                    array_merge([$flag === "\0null" ? null : $flag], $chunk)
                );
            }
        }
    }

    /** Moves existing reports for these shipments out of the way, so the run cannot overwrite them */
    public function setAsideReports(): void
    {
        $stash = $this->workDir . '/set-aside';
        @mkdir($stash, 0775, true);
        foreach ($this->shipmentCodes() as $code) {
            foreach ([$code, $code . '.zip'] as $name) {
                $path = $this->reportsDir() . '/' . $name;
                if (file_exists($path)) {
                    rename($path, $stash . '/' . $name);
                    $this->setAside[$path] = $stash . '/' . $name;
                }
            }
        }
    }

    /** Removes what the run generated and puts the earlier reports back */
    public function restoreReports(): void
    {
        foreach ($this->shipmentCodes() as $code) {
            foreach ([$code, $code . '.zip'] as $name) {
                self::remove($this->reportsDir() . '/' . $name);
            }
        }
        foreach ($this->setAside as $original => $stashed) {
            rename($stashed, $original);
        }
        $this->setAside = [];
    }

    public function reportsDir(): string
    {
        return $this->config->repoRoot . '/downloads/reports';
    }

    public static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    self::remove($path . '/' . $entry);
                }
            }
            rmdir($path);
        }
    }

    /** @param array<string, mixed> $row */
    private function updateRow(string $table, string $key, int $id, array $row): void
    {
        unset($row[$key]);
        $sets = implode(', ', array_map(static fn (string $column): string => "`$column` = ?", array_keys($row)));
        $this->db->exec("UPDATE $table SET $sets WHERE $key = ?", array_merge(array_values($row), [$id]));
    }
}
