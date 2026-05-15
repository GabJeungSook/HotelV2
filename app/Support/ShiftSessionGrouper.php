<?php

namespace App\Support;

use App\Models\ShiftLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ShiftSessionGrouper
{
    /**
     * Group completed ShiftLog rows into report-ready session dicts.
     *
     * Buckets logs by shift_type + shift_date, then within each bucket
     * splits sub-sessions whenever there is a gap with zero active frontdesks
     * (i.e., the next log's time_in is at or after the running session's
     * max(time_out)).
     */
    public static function group(Collection $shiftLogs): array
    {
        $buckets = [];
        foreach ($shiftLogs as $log) {
            $shiftType = $log->shift ?? ShiftResolver::fromClock($log->time_in);
            $shiftDate = ShiftResolver::deriveShiftDate($log->time_in, $shiftType)->format('Y-m-d');
            $key = $shiftType . '|' . $shiftDate;
            $buckets[$key][] = $log;
        }

        $sessions = [];
        foreach ($buckets as $key => $logs) {
            usort($logs, fn ($a, $b) => $a->time_in <=> $b->time_in);
            [$shiftType, $shiftDate] = explode('|', $key, 2);

            $current = null;
            foreach ($logs as $log) {
                if ($current === null) {
                    $current = self::seed($log, $shiftType, $shiftDate);
                    continue;
                }

                if ($log->time_in < $current['max_time_out']) {
                    $current['log_ids'][]    = $log->id;
                    $current['frontdesks'][] = $log->frontdesk?->name ?? 'Unknown';
                    if ($log->time_out > $current['max_time_out']) {
                        $current['max_time_out'] = $log->time_out;
                    }
                } else {
                    $sessions[] = $current;
                    $current = self::seed($log, $shiftType, $shiftDate);
                }
            }
            if ($current !== null) {
                $sessions[] = $current;
            }
        }

        usort($sessions, fn ($a, $b) => $a['min_time_in'] <=> $b['min_time_in']);

        return array_map(fn ($s) => self::shape($s), $sessions);
    }

    private static function seed(ShiftLog $log, string $shiftType, string $shiftDate): array
    {
        return [
            'log_ids'       => [$log->id],
            'frontdesks'    => [$log->frontdesk?->name ?? 'Unknown'],
            'shift_type'    => $shiftType,
            'shift_date'    => $shiftDate,
            'min_time_in'   => $log->time_in,
            'max_time_out'  => $log->time_out,
        ];
    }

    private static function shape(array $session): array
    {
        /** @var Carbon $timeIn */
        $timeIn  = $session['min_time_in'];
        /** @var Carbon $timeOut */
        $timeOut = $session['max_time_out'];

        $frontdeskNames = implode(', ', array_unique($session['frontdesks']));

        return [
            'id'                 => $session['log_ids'][0],
            'log_ids'            => $session['log_ids'],
            'label'              => $session['shift_type'] . ' ' . $timeIn->format('M j')
                                  . ' - ' . $frontdeskNames
                                  . ' (' . $timeIn->format('g:i A') . ' - ' . $timeOut->format('g:i A') . ')',
            'frontdesks'         => $frontdeskNames,
            'shift_type'         => $session['shift_type'],
            'shift_date'         => $session['shift_date'],
            'time_in'            => $timeIn,
            'time_out'           => $timeOut,
            'time_in_formatted'  => $timeIn->format('F d, Y g:i A'),
            'time_out_formatted' => $timeOut->format('F d, Y g:i A'),
            'date_formatted'     => $timeIn->format('l, F d, Y'),
        ];
    }
}
