<?php

namespace CampsiteAgent\Services;

/**
 * Wrapper for browser-based scraping via Node.js/Puppeteer
 */
class BrowserScraper
{
    private string $scriptPath;
    private int $timeout;
    private ?string $nodePath;

    public function __construct(?string $nodePath = null, int $timeout = 60)
    {
        $this->scriptPath = __DIR__ . '/../../bin/scrape-via-browser.js';
        $this->timeout = $timeout;
        $this->nodePath = $nodePath ?: $this->findNodePath();
    }

    /**
     * Find Node.js executable path
     */
    private function findNodePath(): ?string
    {
        // Try common locations
        $paths = [
            '/usr/bin/node',
            '/usr/local/bin/node',
            '/opt/homebrew/bin/node',
            'node', // In PATH
        ];

        foreach ($paths as $path) {
            if ($path === 'node') {
                // Check if node is in PATH
                $output = [];
                $return = 0;
                @exec('which node 2>/dev/null', $output, $return);
                if ($return === 0 && !empty($output)) {
                    return trim($output[0]);
                }
            } else {
                if (file_exists($path) && is_executable($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * Fetch facilities for a park using browser
     * 
     * @param string $placeId Park PlaceId
     * @return array Array of facilities [['FacilityId' => '...', 'Name' => '...', 'PlaceId' => '...'], ...]
     */
    public function fetchFacilities(string $placeId): array
    {
        if (!$this->nodePath) {
            throw new \RuntimeException('Node.js not found. Please install Node.js or set NODE_PATH environment variable.');
        }

        if (!file_exists($this->scriptPath)) {
            throw new \RuntimeException("Browser scraper script not found: {$this->scriptPath}");
        }

        $command = escapeshellarg($this->nodePath) . ' ' . escapeshellarg($this->scriptPath) . 
                   ' facilities ' . escapeshellarg($placeId) . ' 2>&1';

        $output = [];
        $return = 0;
        $startTime = time();

        // Execute with timeout
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        // Unset DISPLAY in environment to prevent X11 errors in headless mode
        // Get all environment variables
        $env = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($value)) {
                $env[$key] = $value;
            }
        }
        foreach ($_ENV as $key => $value) {
            if (is_string($value)) {
                $env[$key] = $value;
            }
        }
        // Remove DISPLAY
        unset($env['DISPLAY']);
        
        $process = proc_open($command, $descriptorspec, $pipes, null, $env);
        
        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start browser scraper process');
        }

        // Set non-blocking mode
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $status = proc_get_status($process);

        while ($status['running']) {
            // Check timeout
            if (time() - $startTime > $this->timeout) {
                proc_terminate($process, 9); // SIGKILL
                throw new \RuntimeException("Browser scraper timed out after {$this->timeout} seconds");
            }

            // Read output
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            usleep(100000); // 100ms
            $status = proc_get_status($process);
        }

        // Read remaining output
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        // Log all output for debugging
        error_log("BrowserScraper stdout length: " . strlen($stdout) . " bytes");
        error_log("BrowserScraper stderr length: " . strlen($stderr) . " bytes");
        
        if (!empty($stderr)) {
            error_log("BrowserScraper stderr (facilities for park {$placeId}): " . substr($stderr, 0, 3000));
        }
        
        if (!empty($stdout)) {
            error_log("BrowserScraper stdout (first 500 chars): " . substr($stdout, 0, 500));
            error_log("BrowserScraper stdout (last 500 chars): " . substr($stdout, -500));
        }
        
        // Parse JSON output
        $lines = explode("\n", trim($stdout));
        $jsonLine = '';
        
        // Find the JSON line (should be the last non-empty line)
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;
            if ($line[0] === '{' || $line[0] === '[') {
                $jsonLine = $line;
                break;
            }
        }

        if (empty($jsonLine)) {
            $errorMsg = !empty($stderr) ? $stderr : $stdout;
            error_log("BrowserScraper: No JSON found. Full stdout: " . substr($stdout, 0, 2000));
            error_log("BrowserScraper: Full stderr: " . substr($stderr, 0, 2000));
            throw new \RuntimeException("Browser scraper returned no JSON. Output: " . substr($errorMsg, 0, 500));
        }
        
        error_log("BrowserScraper: Found JSON line (length: " . strlen($jsonLine) . "): " . substr($jsonLine, 0, 200));

        $data = json_decode($jsonLine, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("BrowserScraper: JSON parse error: " . json_last_error_msg());
            error_log("BrowserScraper: JSON line that failed: " . substr($jsonLine, 0, 500));
            throw new \RuntimeException("Failed to parse JSON from browser scraper: " . json_last_error_msg() . ". Output: " . substr($jsonLine, 0, 500));
        }

        // Check for error in response
        if (isset($data['error'])) {
            error_log("BrowserScraper: Error in response: " . $data['error']);
            throw new \RuntimeException("Browser scraper error: " . $data['error']);
        }

        error_log("BrowserScraper: Successfully parsed JSON, returned " . (is_array($data) ? count($data) : 'non-array') . " items");
        
        // Check if data has Facility.Units structure
        if (is_array($data) && isset($data['Facility']) && isset($data['Facility']['Units'])) {
            error_log("BrowserScraper: Data has Facility.Units structure with " . count($data['Facility']['Units']) . " units");
        } else if (is_array($data) && isset($data['Facility'])) {
            error_log("BrowserScraper: Data has Facility but no Units. Facility keys: " . implode(', ', array_keys($data['Facility'])));
        } else if (is_array($data) && count($data) > 0) {
            $firstKey = array_key_first($data);
            $firstValue = $data[$firstKey];
            error_log("BrowserScraper: First key: {$firstKey}, type: " . gettype($firstValue));
            if ($firstValue !== null) {
                error_log("BrowserScraper: First item (first 500 chars): " . substr(json_encode($firstValue), 0, 500));
            } else {
                error_log("BrowserScraper: First item is null");
            }
            error_log("BrowserScraper: Data top-level keys: " . implode(', ', array_keys($data)));
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Fetch availability for a facility using browser
     * 
     * @param string $placeId Park PlaceId
     * @param string $facilityId Facility ID
     * @param string $startDate Start date YYYY-MM-DD
     * @param int $nights Number of nights
     * @return array Grid data with Facility.Units structure
     */
    public function fetchAvailability(string $placeId, string $facilityId, string $startDate, int $nights): array
    {
        if (!$this->nodePath) {
            throw new \RuntimeException('Node.js not found. Please install Node.js or set NODE_PATH environment variable.');
        }

        if (!file_exists($this->scriptPath)) {
            throw new \RuntimeException("Browser scraper script not found: {$this->scriptPath}");
        }

        $command = escapeshellarg($this->nodePath) . ' ' . escapeshellarg($this->scriptPath) . 
                   ' availability ' . 
                   escapeshellarg($placeId) . ' ' .
                   escapeshellarg($facilityId) . ' ' .
                   escapeshellarg($startDate) . ' ' .
                   escapeshellarg((string)$nights) . ' 2>&1';

        $output = [];
        $return = 0;
        $startTime = time();

        // Execute with timeout
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        // Unset DISPLAY in environment to prevent X11 errors in headless mode
        // Get all environment variables
        $env = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($value)) {
                $env[$key] = $value;
            }
        }
        foreach ($_ENV as $key => $value) {
            if (is_string($value)) {
                $env[$key] = $value;
            }
        }
        // Remove DISPLAY
        unset($env['DISPLAY']);
        
        $process = proc_open($command, $descriptorspec, $pipes, null, $env);
        
        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start browser scraper process');
        }

        // Set non-blocking mode
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $status = proc_get_status($process);

        while ($status['running']) {
            // Check timeout
            if (time() - $startTime > $this->timeout) {
                proc_terminate($process, 9); // SIGKILL
                throw new \RuntimeException("Browser scraper timed out after {$this->timeout} seconds");
            }

            // Read output
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            usleep(100000); // 100ms
            $status = proc_get_status($process);
        }

        // Read remaining output
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        // Log all output for debugging
        error_log("BrowserScraper stdout length: " . strlen($stdout) . " bytes");
        error_log("BrowserScraper stderr length: " . strlen($stderr) . " bytes");
        
        if (!empty($stderr)) {
            error_log("BrowserScraper stderr (facilities for park {$placeId}): " . substr($stderr, 0, 3000));
        }
        
        if (!empty($stdout)) {
            error_log("BrowserScraper stdout (first 500 chars): " . substr($stdout, 0, 500));
            error_log("BrowserScraper stdout (last 500 chars): " . substr($stdout, -500));
        }
        
        // Parse JSON output
        $lines = explode("\n", trim($stdout));
        $jsonLine = '';
        
        // Find the JSON line (should be the last non-empty line)
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;
            if ($line[0] === '{' || $line[0] === '[') {
                $jsonLine = $line;
                break;
            }
        }

        if (empty($jsonLine)) {
            $errorMsg = !empty($stderr) ? $stderr : $stdout;
            error_log("BrowserScraper: No JSON found. Full stdout: " . substr($stdout, 0, 2000));
            error_log("BrowserScraper: Full stderr: " . substr($stderr, 0, 2000));
            throw new \RuntimeException("Browser scraper returned no JSON. Output: " . substr($errorMsg, 0, 500));
        }
        
        error_log("BrowserScraper: Found JSON line (length: " . strlen($jsonLine) . "): " . substr($jsonLine, 0, 200));

        $data = json_decode($jsonLine, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("BrowserScraper: JSON parse error: " . json_last_error_msg());
            error_log("BrowserScraper: JSON line that failed: " . substr($jsonLine, 0, 500));
            throw new \RuntimeException("Failed to parse JSON from browser scraper: " . json_last_error_msg() . ". Output: " . substr($jsonLine, 0, 500));
        }

        // Check for error in response
        if (isset($data['error'])) {
            error_log("BrowserScraper: Error in response: " . $data['error']);
            throw new \RuntimeException("Browser scraper error: " . $data['error']);
        }

        error_log("BrowserScraper: Successfully parsed JSON, returned " . (is_array($data) ? count($data) : 'non-array') . " items");
        
        // Check if data has Facility.Units structure
        if (is_array($data) && isset($data['Facility']) && isset($data['Facility']['Units'])) {
            error_log("BrowserScraper: Data has Facility.Units structure with " . count($data['Facility']['Units']) . " units");
        } else if (is_array($data) && isset($data['Facility'])) {
            error_log("BrowserScraper: Data has Facility but no Units. Facility keys: " . implode(', ', array_keys($data['Facility'])));
        } else if (is_array($data) && count($data) > 0) {
            $firstKey = array_key_first($data);
            $firstValue = $data[$firstKey];
            error_log("BrowserScraper: First key: {$firstKey}, type: " . gettype($firstValue));
            if ($firstValue !== null) {
                error_log("BrowserScraper: First item (first 500 chars): " . substr(json_encode($firstValue), 0, 500));
            } else {
                error_log("BrowserScraper: First item is null");
            }
            error_log("BrowserScraper: Data top-level keys: " . implode(', ', array_keys($data)));
        }

        return is_array($data) ? $data : [];
    }
}

