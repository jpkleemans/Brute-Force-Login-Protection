<?php

class Htaccess
{
    /**
     * Path to .htaccess file
     * 
     * @var string
     */
    private $path;

    /**
     * Default .htaccess lines before custom lines
     * 
     * @var array
     */
    private $header = array(
        '<Files "*">',
        'order deny,allow'
    );

    /**
     * Default .htaccess lines after custom lines
     * 
     * @var array
     */
    private $footer = array(
        '</Files>'
    );

    /**
     * Initialize $path.
     * 
     * @param string $dir
     */
    public function setPath($dir)
    {
        $this->path = $dir . '/.htaccess';
    }

    /**
     * Check if .htaccess file is found, readable and writeable.
     * 
     * @return array
     */
    public function checkRequirements()
    {
        $status = array(
            'found' => false,
            'readable' => false,
            'writeable' => false
        );

        if (file_exists($this->path)) { // File found
            $status['found'] = true;
        }
        if (is_readable($this->path)) { // File readable
            $status['readable'] = true;
        }
        if (is_writeable($this->path)) { // File writeable
            $status['writeable'] = true;
        }

        return $status;
    }

    /**
     * Return array of denied IP addresses from .htaccess.
     * 
     * @return array
     */
    public function getDeniedIPs()
    {
        $lines = $this->getLines('deny from ');

        foreach ($lines as $key => $line) {
            $lines[$key] = substr($line, 10);
        }

        return $lines;
    }

    /**
     * Add 'deny from $IP' to .htaccess.
     * 
     * @param string $IP
     * @return boolean
     */
    public function denyIP($IP)
    {
        if (!filter_var($IP, FILTER_VALIDATE_IP)) {
            return false;
        }

        return $this->addLine('deny from ' . $IP);
    }

    /**
     * Remove 'deny from $IP' from .htaccess.
     * 
     * @param string $IP
     * @return boolean
     */
    public function undenyIP($IP)
    {
        if (!filter_var($IP, FILTER_VALIDATE_IP)) {
            return false;
        }

        return $this->removeLine('deny from ' . $IP);
    }

    /**
     * Edit ErrorDocument 403 line in .htaccess.
     * 
     * @param string $message
     * @return boolean
     */
    public function edit403Message($message)
    {
        if (empty($message)) {
            return $this->remove403Message();
        }

        $line = 'ErrorDocument 403 "' . $message . '"';

        $otherLines = $this->getLines('ErrorDocument 403 ', true, true);

        $insertion = array_merge($this->header, array($line), $otherLines, $this->footer);

        return $this->insert($insertion);
    }

    /**
     * Remove ErrorDocument 403 line from .htaccess.
     * 
     * @return boolean
     */
    public function remove403Message()
    {
        return $this->removeLine('', 'ErrorDocument 403 ');
    }

    /**
     * Comment out all BFLP lines in .htaccess.
     * 
     * @return boolean
     */
    public function commentLines()
    {
        $currentLines = $this->getLines(array('deny from ', 'ErrorDocument 403 '));

        $insertion = array();
        foreach ($currentLines as $line) {
            $insertion[] = '#' . $line;
        }

        return $this->insert($insertion);
    }

    /**
     * Uncomment all commented BFLP lines in .htaccess.
     * 
     * @return boolean
     */
    public function uncommentLines()
    {
        $currentLines = $this->getLines(array('#deny from ', '#ErrorDocument 403 '));

        $lines = array();
        foreach ($currentLines as $line) {
            $lines[] = substr($line, 1);
        }

        $insertion = array_merge($this->header, $lines, $this->footer);

        return $this->insert($insertion);
    }

    /**
     * Return array of (prefixed) lines from .htaccess.
     * 
     * @param string $prefixes
     * @return array
     */
    private function getLines($prefixes = false, $onlyBody = false, $exceptPrefix = false)
    {
        $allLines = $this->extract();

        if ($onlyBody) {
            $allLines = array_diff($allLines, $this->header, $this->footer);
        }

        if (!$prefixes) {
            return $allLines;
        }

        if (!is_array($prefixes)) {
            $prefixes = array($prefixes);
        }

        $prefixedLines = array();
        foreach ($allLines as $line) {
            foreach ($prefixes as $prefix) {
                if (strpos($line, $prefix) === 0) {
                    $prefixedLines[] = $line;
                }
            }
        }

        if ($exceptPrefix) {
            $prefixedLines = array_diff($allLines, $prefixedLines);
        }

        return $prefixedLines;
    }

    /**
     * Add single line to .htaccess.
     * 
     * @param string $line
     * @return boolean
     */
    private function addLine($line)
    {
        $insertion = array_merge($this->header, $this->getLines(false, true), array($line), $this->footer);

        return $this->insert(array_unique($insertion));
    }

    /**
     * Remove single line from .htaccess.
     * 
     * @param string $line
     * @param string $prefix
     * @return boolean
     */
    private function removeLine($line, $prefix = false)
    {
        $insertion = $this->getLines();

        if ($prefix !== false) {
            $lineKey = false;
            $prefixLength = strlen($prefix);
            foreach ($insertion as $key => $line) {
                if (substr($line, 0, $prefixLength) === $prefix) {
                    $lineKey = $key;
                    break;
                }
            }
        } else {
            $lineKey = array_search($line, $insertion);
        }

        if ($lineKey === false) {
            return true;
        }

        unset($insertion[$lineKey]);

        return $this->insert($insertion);
    }

    /**
     * Return array of strings from between BEGIN and END markers from .htaccess.
     * 
     * @return array Array of strings from between BEGIN and END markers from .htaccess.
     */
    private function extract()
    {
        $marker = 'Brute Force Login Protection';

        $result = array();

        if (!file_exists($this->path)) {
            return $result;
        }

        if ($markerdata = explode("\n", implode('', file($this->path)))) {
            $state = false;
            foreach ($markerdata as $markerline) {
                if (strpos($markerline, '# END ' . $marker) !== false) {
                    $state = false;
                }
                if ($state) {
                    $result[] = $markerline;
                }
                if (strpos($markerline, '# BEGIN ' . $marker) !== false) {
                    $state = true;
                }
            }
        }

        return $result;
    }

    /**
     * Insert an array of strings into .htaccess, placing it between BEGIN and END markers.
     * Replace existing marked info. Retain surrounding data.
     * Create file if none exists.
     *
     * @param string $insertion
     * @return bool True on write success, false on failure.
     */
    private function insert($insertion)
    {
        $marker = 'Brute Force Login Protection';

        if (!file_exists($this->path) || is_writeable($this->path)) {
            if (!file_exists($this->path)) {
                $markerdata = '';
            } else {
                $markerdata = explode("\n", implode('', file($this->path)));
            }

            $newContent = '';

            $foundit = false;
            if ($markerdata) {
                $lineCount = count($markerdata);

                $state = true;
                foreach ($markerdata as $n => $markerline) {
                    if (strpos($markerline, '# BEGIN ' . $marker) !== false) {
                        $state = false;
                    }

                    if ($state) { // Non-BFLP lines
                        if ($n + 1 < $lineCount) {
                            $newContent .= "{$markerline}\n";
                        } else {
                            $newContent .= "{$markerline}";
                        }
                    }

                    if (strpos($markerline, '# END ' . $marker) !== false) {
                        $newContent .= "# BEGIN {$marker}\n";
                        if (is_array($insertion)) {
                            foreach ($insertion as $insertline) {
                                $newContent .= "{$insertline}\n";
                            }
                        }
                        $newContent .= "# END {$marker}\n";

                        $state = true;
                        $foundit = true;
                    }
                }

                if ($state === false) { // If BEGIN marker found but missing END marker
                    return false;
                }
            }

            if (!$foundit) {
                $newContent .= "\n# BEGIN {$marker}\n";
                foreach ($insertion as $insertline) {
                    $newContent .= "{$insertline}\n";
                }
                $newContent .= "# END {$marker}\n";
            }

            return file_put_contents($this->path, $newContent, LOCK_EX);
        } else {
            return false;
        }
    }
}
