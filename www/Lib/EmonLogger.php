<?php
/*
 All Emoncms code is released under the GNU Affero General Public License.
 See COPYRIGHT.txt and LICENSE.txt.

 ---------------------------------------------------------------------
 Emoncms - open source energy visualisation
 Part of the OpenEnergyMonitor project:
 http://openenergymonitor.org
 */

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class EmonLogger
{
    private $logfile = "";
    private $caller = "";
    private $logenabled = false;
    private $log_level = 2;
    public $stout = false;

    private $log_levels = array(
            1 =>'INFO',
            2 =>'WARN', // default
            3 =>'ERROR'
        );

    public function __construct($clientFileName)
    {
        global $settings;

        // Treat a missing log block as logging off rather than emitting undefined
        // index warnings. Installs that share this file but predate the setting,
        // and CLI entry points that build a minimal $settings, both hit this.
        $log = (isset($settings['log']) && is_array($settings['log'])) ? $settings['log'] : array();

        if (empty($log['enabled'])) {
            $this->logenabled = false;
        } else {
            $location = isset($log['location']) ? $log['location'] : sys_get_temp_dir();
            $this->logfile = $location."/emoncms.log";
            if (!empty($log['level'])) {
                $this->log_level = $log['level'];
            }
            $this->caller = basename($clientFileName);
            if (!file_exists($this->logfile)) {
                $fh = @fopen($this->logfile, "a");
                if (!$fh) {
                   error_log("Log file could not be created");
                } else {
                   @fclose($fh);
                }
            }
            $this->logenabled = is_writable($this->logfile);
        }
    }
    
    public function set($logfile, $log_level)
    {
        $this->logfile = $logfile;
        $this->logenabled = true;
        $this->log_level = $log_level;
    }

    public function info($message)
    {
        if ($this->log_level <= 1) {
            $this->write("INFO", $message);
        }
    }

    public function warn($message)
    {
        if ($this->log_level <= 2) {
            $this->write("WARN", $message);
        }
    }

    public function error($message)
    {
        if ($this->log_level <= 3) {
            $this->write("ERROR", $message);
        }
    }

    public function levels()
    {
        return $this->log_levels;
    }

    private function write($type, $message)
    {
        if (!$this->logenabled) {
            return;
        }
        
        if ($this->stout) {
            print $type." ".$message."\n";
        }

        $now = microtime(true);
        $micro = sprintf("%03d", ($now - intval($now)) * 1000 );
        $now = DateTime::createFromFormat('U', (int)$now); // Only use UTC for logs
        $now = $now->format("Y-m-d H:i:s").".$micro";
        // Clear log file if more than 256MB (temporary solution)
        if (filesize($this->logfile)>(1024*1024*256)) {
            $fh = @fopen($this->logfile, "w");
            @fclose($fh);
        }
        if ($fh = @fopen($this->logfile, "a")) {
            @fwrite($fh, $now."|$type|$this->caller|".$message."\n");
            @fclose($fh);
        }
    }
}
