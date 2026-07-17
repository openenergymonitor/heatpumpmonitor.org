<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class Signature
{
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    /*
     * Get all episodes for a system, ordered by time
     * @param int $system_id
     * @return array
     */
    public function get_episodes($system_id) {
        $system_id = (int) $system_id;
        $result = $this->mysqli->query("SELECT * FROM signature_episodes WHERE system_id = $system_id ORDER BY start_time");
        $episodes = array();
        while ($row = $result->fetch_object()) {
            $episodes[] = $row;
        }
        return $episodes;
    }
}
