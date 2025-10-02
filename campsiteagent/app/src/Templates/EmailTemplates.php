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

    public static function alertHtml(string $parkName, string $dateRange, array $sites): string
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
            
            // Group sites by facility
            $byFacility = [];
            foreach ($weekend['sites'] as $site) {
                $facility = $site['facility_name'] ?? 'Unknown Facility';
                if (!isset($byFacility[$facility])) {
                    $byFacility[$facility] = [];
                }
                $byFacility[$facility][] = $site;
            }
            
            // Build facility sections with View links
            $facilitySections = '';
            $parkUrl = "https://reservecalifornia.com/Web/Default.aspx#!park/712";
            
            foreach ($byFacility as $facilityName => $facilitySites) {
                $siteNumbers = array_map(function($s) {
                    return htmlspecialchars((string)($s['site_number'] ?? ''));
                }, $facilitySites);
                
                $facilitySections .= "<div style='margin-left: 16px; margin-bottom: 8px;'>";
                $facilitySections .= "<div style='font-weight: 500; color: #059669; font-size: 14px;'>";
                $facilitySections .= htmlspecialchars($facilityName) . " (" . count($facilitySites) . " sites) ";
                $facilitySections .= "<a href='{$parkUrl}' style='margin-left: 8px; font-size: 12px; color: #2563eb; text-decoration: none;'>→ View</a>";
                $facilitySections .= "</div>";
                $facilitySections .= "<div style='font-size: 13px; color: #6b7280; margin-left: 8px;'>" . implode(', ', $siteNumbers) . "</div>";
                $facilitySections .= "</div>";
            }
            
            $sections .= "<div style='margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #e5e7eb;'>";
            $sections .= "<h3 style='color: #2563eb; margin: 0 0 8px 0;'>📅 {$friFormatted} - {$satFormatted}</h3>";
            $sections .= "<p style='margin: 0 0 8px 0; font-size: 14px; color: #4b5563;'><strong>" . count($weekend['sites']) . " sites available</strong></p>";
            $sections .= $facilitySections;
            $sections .= "</div>";
        }
        
        return "<p><strong>Weekend availability found!</strong></p><p>Park: <strong>{$park}</strong></p><div style='background: #eef2ff; border-left: 4px solid #4c51bf; padding: 12px; margin: 12px 0;'><strong>ℹ️ Note:</strong> Click 'View' links to go to the ReserveCalifornia booking page. You'll need to manually select the dates shown below, as direct booking links are not supported.</div><hr>{$sections}";
    }

    public static function alertText(string $parkName, string $dateRange, array $sites): string
    {
        $lines = [
            "Weekend availability found!",
            "Park: {$parkName}",
            ''
        ];
        
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
            
            // Group by facility
            $byFacility = [];
            foreach ($weekend['sites'] as $site) {
                $facility = $site['facility_name'] ?? 'Unknown Facility';
                if (!isset($byFacility[$facility])) {
                    $byFacility[$facility] = [];
                }
                $byFacility[$facility][] = $site;
            }
            
            $parkUrl = "https://reservecalifornia.com/Web/Default.aspx#!park/712";
            
            foreach ($byFacility as $facilityName => $facilitySites) {
                $siteNumbers = array_map(function($s) {
                    return $s['site_number'] ?? '';
                }, $facilitySites);
                
                $lines[] = "   {$facilityName} (" . count($facilitySites) . " sites)";
                $lines[] = "   View: {$parkUrl}";
                $lines[] = "   Sites: " . implode(', ', $siteNumbers);
            }
            
            $lines[] = '';
        }
        
        return implode("\n", $lines);
    }
}
