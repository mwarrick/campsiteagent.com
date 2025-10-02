<?php

namespace CampsiteAgent\Services;

class WeekendDetector
{
    public function hasWeekend(array $siteAvailabilityByDate): bool
    {
        // Expect array like ['2025-10-10' => true, '2025-10-11' => false, ...]
        // Find any Friday that has following Saturday also available
        $dates = array_keys($siteAvailabilityByDate);
        sort($dates);
        foreach ($dates as $date) {
            $ts = strtotime($date);
            if (!$ts) { continue; }
            $dow = (int)date('w', $ts); // 5 = Friday, 6 = Saturday
            if ($dow === 5) {
                $fri = date('Y-m-d', $ts);
                $sat = date('Y-m-d', $ts + 86400);
                // Must check === true, not just !empty(), because false is also "not empty"
                if (($siteAvailabilityByDate[$fri] ?? false) === true && 
                    ($siteAvailabilityByDate[$sat] ?? false) === true) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Get all weekend date pairs (Fri-Sat) that are available
     * @param array $siteAvailabilityByDate ['2025-10-10' => true, ...]
     * @return array [['fri' => '2025-10-10', 'sat' => '2025-10-11'], ...]
     */
    public function getWeekendDates(array $siteAvailabilityByDate): array
    {
        $weekends = [];
        $dates = array_keys($siteAvailabilityByDate);
        sort($dates);
        
        foreach ($dates as $date) {
            $ts = strtotime($date);
            if (!$ts) { continue; }
            $dow = (int)date('w', $ts); // 5 = Friday, 6 = Saturday
            
            if ($dow === 5) {
                $fri = date('Y-m-d', $ts);
                $sat = date('Y-m-d', $ts + 86400);
                
                // Must check === true, not just !empty()
                if (($siteAvailabilityByDate[$fri] ?? false) === true && 
                    ($siteAvailabilityByDate[$sat] ?? false) === true) {
                    $weekends[] = [
                        'fri' => $fri,
                        'sat' => $sat
                    ];
                }
            }
        }
        
        return $weekends;
    }
}
