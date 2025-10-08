<?php

namespace CampsiteAgent\Templates;

class EmailTemplates
{
    public static function verificationSubject(): string
    {
        return 'Verify your Campsite Agent email';
    }

    public static function verificationHtml(string $verifyUrl, string $firstName = ''): string
    {
        $name = $firstName ? htmlspecialchars($firstName) : 'there';
        $url = htmlspecialchars($verifyUrl);
        return "<p>Hi {$name},</p><p>Please verify your email for Campsite Agent by clicking the link below:</p><p><a href=\"{$url}\">Verify Email</a></p><p>If you did not request this, you can ignore this message.</p>";
    }

    public static function verificationText(string $verifyUrl, string $firstName = ''): string
    {
        $name = $firstName ?: 'there';
        return "Hi {$name},\n\nPlease verify your email for Campsite Agent: {$verifyUrl}\n\nIf you did not request this, you can ignore this message.";
    }

    public static function loginSubject(): string
    {
        return 'Your Campsite Agent login link';
    }

    public static function loginHtml(string $loginUrl, string $firstName = ''): string
    {
        $name = $firstName ? htmlspecialchars($firstName) : 'there';
        $url = htmlspecialchars($loginUrl);
        return "<p>Hi {$name},</p><p>Your passwordless login link is below:</p><p><a href=\"{$url}\">Log in to Campsite Agent</a></p><p>This link will expire soon.</p>";
    }

    public static function loginText(string $loginUrl, string $firstName = ''): string
    {
        $name = $firstName ?: 'there';
        return "Hi {$name},\n\nYour passwordless login link: {$loginUrl}\n\nThis link will expire soon.";
    }

    public static function alertSubject(string $parkName, string $dateRange): string
    {
        return "Weekend availability found for {$dateRange}: {$parkName}";
    }

    public static function alertHtml(string $parkName, string $dateRange, array $sites, array $favoriteSiteIds = [], string $disableUrl = '', ?string $parkWebsiteUrl = null): string
    {
        $park = htmlspecialchars($parkName);
        $range = htmlspecialchars($dateRange);
        
        // Group sites by weekend date
        $byWeekend = [];
        foreach ($sites as $site) {
            $weekendDates = $site['weekend_dates'] ?? [];
            foreach ($weekendDates as $weekend) {
                $key = $weekend['fri'] . '|' . $weekend['sat'];
                if (!isset($byWeekend[$key])) {
                    $byWeekend[$key] = [
                        'fri' => $weekend['fri'],
                        'sat' => $weekend['sat'],
                        'sites' => []
                    ];
                }
                $byWeekend[$key]['sites'][] = $site;
            }
        }
        
        // Sort by date
        ksort($byWeekend);
        
        // Build HTML
        $sections = '';
        foreach ($byWeekend as $weekend) {
            $friFormatted = date('D, M j', strtotime($weekend['fri']));
            $satFormatted = date('D, M j, Y', strtotime($weekend['sat']));
            
            // Group sites by park then facility
            $byPark = [];
            foreach ($weekend['sites'] as $site) {
                $pName = $site['park_name'] ?? $parkName;
                $fName = $site['facility_name'] ?? 'Unknown Facility';
                if (!isset($byPark[$pName])) { $byPark[$pName] = []; }
                if (!isset($byPark[$pName][$fName])) { $byPark[$pName][$fName] = []; }
                $byPark[$pName][$fName][] = $site;
            }
            ksort($byPark, SORT_NATURAL | SORT_FLAG_CASE);
            
            // Build sections Park → Facility → Sites
            $parkSections = '';
            foreach ($byPark as $pName => $facilities) {
                $parkSections .= "<div style='margin-left: 8px; margin-bottom: 6px; font-weight:600; color:#1f2937;'>" . htmlspecialchars($pName) . "</div>";
                ksort($facilities, SORT_NATURAL | SORT_FLAG_CASE);
                foreach ($facilities as $facilityName => $facilitySites) {
                    // Sort and dedupe site numbers
                    usort($facilitySites, function($a, $b) {
                        $an = (string)($a['site_number'] ?? '');
                        $bn = (string)($b['site_number'] ?? '');
                        return strnatcasecmp($an, $bn);
                    });
                    $numbers = [];
                    foreach ($facilitySites as $s) {
                        $num = (string)($s['site_number'] ?? '');
                        if ($num === '') continue;
                        if (in_array($num, $numbers, true)) continue;
                        $numbers[] = $num;
                    }
                    // Mark favorites
                    $rendered = array_map(function($sNum) use ($facilitySites, $favoriteSiteIds) {
                        // Determine if any site in this facility with this number is a favorite by site_id
                        $isFav = false;
                        foreach ($facilitySites as $s) {
                            if ((string)($s['site_number'] ?? '') === $sNum) {
                                if (!empty($s['site_id']) && in_array((int)$s['site_id'], $favoriteSiteIds, true)) { $isFav = true; break; }
                            }
                        }
                        $safeNum = htmlspecialchars($sNum);
                        return $isFav ? "<span style='color:#d97706; font-weight:600;'>★ {$safeNum}</span>" : $safeNum;
                    }, $numbers);

                    $parkNumber = null;
                    // Try to extract a park_number from any site in this facility group
                    foreach ($facilitySites as $s) {
                        if (!empty($s['park_number'])) { $parkNumber = (string)$s['park_number']; break; }
                        // Fallback for common fields
                        if (!empty($s['park_number']) === false && !empty($s['park_id'])) {
                            // No direct fallback without lookup; keep null
                        }
                    }
                    // If facility external ID present, include it for deeper context (when supported by site)
                    $facilityId = null;
                    foreach ($facilitySites as $s) { if (!empty($s['facility_external_id'])) { $facilityId = (string)$s['facility_external_id']; break; } }
                    if ($parkNumber && $facilityId) {
                        $parkUrl = "https://reservecalifornia.com/Web/Default.aspx#!park/" . htmlspecialchars($parkNumber) . "/" . htmlspecialchars($facilityId);
                    } elseif ($parkNumber) {
                        $parkUrl = "https://reservecalifornia.com/Web/Default.aspx#!park/" . htmlspecialchars($parkNumber);
                    } else {
                        $parkUrl = "https://reservecalifornia.com/Web/Default.aspx#!";
                    }
                    $parkSections .= "<div style='margin-left: 16px; margin-bottom: 8px;'>";
                    $parkSections .= "<div style='font-weight: 500; color: #059669; font-size: 14px;'>";
                    $parkSections .= htmlspecialchars($facilityName) . " (" . count($numbers) . " sites) ";
                    $parkSections .= "<a href='{$parkUrl}' style='margin-left: 8px; font-size: 12px; color: #2563eb; text-decoration: none;'>→ View</a>";
                    $parkSections .= "</div>";
                    $parkSections .= "<div style='font-size: 13px; color: #6b7280; margin-left: 8px;'>" . implode(', ', $rendered) . "</div>";
                    $parkSections .= "</div>";
                }
            }
            
            $sections .= "<div style='margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #e5e7eb;'>";
            $sections .= "<h3 style='color: #2563eb; margin: 0 0 8px 0;'>📅 {$friFormatted} - {$satFormatted}</h3>";
            $sections .= "<p style='margin: 0 0 8px 0; font-size: 14px; color: #4b5563;'><strong>" . count($weekend['sites']) . " sites available</strong></p>";
            $sections .= $parkSections;
            $sections .= "</div>";
        }
        
        $disableLink = $disableUrl ? "<a href='" . htmlspecialchars($disableUrl) . "' style='color: #dc2626; text-decoration: none;'>Disable all alerts</a>" : "Disable all alerts";
        
        // Make park name clickable if website URL is available
        $parkDisplay = $parkWebsiteUrl 
            ? "<a href='" . htmlspecialchars($parkWebsiteUrl) . "' style='color: #2563eb; text-decoration: none;'><strong>{$park}</strong></a>"
            : "<strong>{$park}</strong>";
        
        return "<p><strong>Weekend availability found!</strong></p><p style='font-size: 12px; color: #6b7280; margin: 0 0 12px 0;'>Don't want to receive these alerts? {$disableLink}</p><p>Park: {$parkDisplay}</p><div style='background: #eef2ff; border-left: 4px solid #4c51bf; padding: 12px; margin: 12px 0;'><strong>ℹ️ Note:</strong> Click 'View' links to go to the ReserveCalifornia booking page. You'll need to manually select the dates shown below, as direct booking links are not supported.</div><hr>{$sections}<div style='margin-top: 20px; padding-top: 16px; border-top: 1px solid #e5e7eb; text-align: center;'><p style='font-size: 12px; color: #6b7280; margin: 0;'>Don't want to receive these alerts? {$disableLink}</p></div>";
    }

    public static function alertText(string $parkName, string $dateRange, array $sites, array $favoriteSiteIds = [], string $disableUrl = '', ?string $parkWebsiteUrl = null): string
    {
        $lines = [
            "Weekend availability found!",
        ];
        
        if ($disableUrl) {
            $lines[] = "Don't want to receive these alerts? Disable all alerts: {$disableUrl}";
        }
        
        $parkDisplay = $parkWebsiteUrl ? "{$parkName} ({$parkWebsiteUrl})" : $parkName;
        $lines[] = "Park: {$parkDisplay}";
        $lines[] = '';
        
        // Group sites by weekend date
        $byWeekend = [];
        foreach ($sites as $site) {
            $weekendDates = $site['weekend_dates'] ?? [];
            foreach ($weekendDates as $weekend) {
                $key = $weekend['fri'] . '|' . $weekend['sat'];
                if (!isset($byWeekend[$key])) {
                    $byWeekend[$key] = [
                        'fri' => $weekend['fri'],
                        'sat' => $weekend['sat'],
                        'sites' => []
                    ];
                }
                $byWeekend[$key]['sites'][] = $site;
            }
        }
        
        // Sort by date
        ksort($byWeekend);
        
        // Build text
        $lines[] = 'NOTE: Click View links to go to ReserveCalifornia. You\'ll need to manually select the dates shown below.';
        $lines[] = '';
        
        foreach ($byWeekend as $weekend) {
            $friFormatted = date('D, M j', strtotime($weekend['fri']));
            $satFormatted = date('D, M j, Y', strtotime($weekend['sat']));
            
            $lines[] = "📅 {$friFormatted} - {$satFormatted}";
            $lines[] = "   " . count($weekend['sites']) . " sites available";
            
            // Group by park then facility
            $byPark = [];
            foreach ($weekend['sites'] as $site) {
                $pName = $site['park_name'] ?? $parkName;
                $fName = $site['facility_name'] ?? 'Unknown Facility';
                if (!isset($byPark[$pName])) { $byPark[$pName] = []; }
                if (!isset($byPark[$pName][$fName])) { $byPark[$pName][$fName] = []; }
                $byPark[$pName][$fName][] = $site;
            }
            ksort($byPark, SORT_NATURAL | SORT_FLAG_CASE);

            foreach ($byPark as $pName => $facilities) {
                $lines[] = "   {$pName}";
                ksort($facilities, SORT_NATURAL | SORT_FLAG_CASE);
                foreach ($facilities as $facilityName => $facilitySites) {
                    usort($facilitySites, function($a, $b) {
                        $an = (string)($a['site_number'] ?? '');
                        $bn = (string)($b['site_number'] ?? '');
                        return strnatcasecmp($an, $bn);
                    });
                    // Deduplicate
                    $numbers = [];
                    foreach ($facilitySites as $s) {
                        $num = (string)($s['site_number'] ?? '');
                        if ($num === '') continue;
                        if (in_array($num, $numbers, true)) continue;
                        $numbers[] = $num;
                    }
                    // Mark favorites
                    $rendered = array_map(function($sNum) use ($facilitySites, $favoriteSiteIds) {
                        $isFav = false;
                        foreach ($facilitySites as $s) {
                            if ((string)($s['site_number'] ?? '') === $sNum) {
                                if (!empty($s['site_id']) && in_array((int)$s['site_id'], $favoriteSiteIds, true)) { $isFav = true; break; }
                            }
                        }
                        return $isFav ? ("★ " . $sNum) : $sNum;
                    }, $numbers);
                    // Include park link with number if available
                    $parkNumber = null;
                    foreach ($facilitySites as $s) { if (!empty($s['park_number'])) { $parkNumber = (string)$s['park_number']; break; } }
                    $parkUrlText = $parkNumber ? ("https://reservecalifornia.com/Web/Default.aspx#!park/" . $parkNumber) : "https://reservecalifornia.com/Web/Default.aspx#!";
                    $lines[] = "   {$facilityName} (" . count($numbers) . " sites)";
                    $lines[] = "   View: {$parkUrlText}";
                    $lines[] = "   Sites: " . implode(', ', $rendered);
                }
            }
            
            $lines[] = '';
        }
        
        if ($disableUrl) {
            $lines[] = '';
            $lines[] = "Don't want to receive these alerts? Disable all alerts: {$disableUrl}";
        }
        
        return implode("\n", $lines);
    }
}
