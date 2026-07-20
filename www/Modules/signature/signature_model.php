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

    /*
     * Get a list of system ids with a count of signatures (episodes) for each
     * @return array of objects with system_id and count
     */
    public function get_system_counts() {
        $result = $this->mysqli->query("SELECT system_id, COUNT(*) AS count FROM signature_episodes GROUP BY system_id ORDER BY system_id");
        $systems = array();
        while ($row = $result->fetch_object()) {
            $row->system_id = (int) $row->system_id;
            $row->count = (int) $row->count;
            $systems[] = $row;
        }
        return $systems;
    }
}
