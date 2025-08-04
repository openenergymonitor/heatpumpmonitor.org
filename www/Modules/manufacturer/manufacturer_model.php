<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class Manufacturer
{
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    /*
     * Get all manufacturers
     * @return array
     */
    public function get_all() {
        $result = $this->mysqli->query("SELECT * FROM manufacturers");
        $manufacturers = array();
        while ($row = $result->fetch_object()) {
            $manufacturers[] = $row;
        }
        return $manufacturers;
    }

    /*
     * Get names of all manufacturers
     * @return array
     */
    public function get_names() {
        $result = $this->mysqli->query("SELECT name FROM manufacturers");
        $manufacturers = array();
        while ($row = $result->fetch_object()) {
            $manufacturers[] = $row->name;
        }
        return $manufacturers;
    }

    /*
     * Get manufacturer by ID
     * @param int $id
     * @return object|null
     */
    public function get_by_id($id) {
        $stmt = $this->mysqli->prepare("SELECT * FROM manufacturers WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return $result->fetch_object();
        } else {
            return false;
        }
    }

    /*
     * Add a new manufacturer
     * @param string $name
     * @param string $website
     * @return bool
     */
    public function add($name, $website = "") {
        // Check if name is provided
        if (empty($name)) {
            return array("error" => "Missing manufacturer name");
        }

        // Check if name is unique
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) FROM manufacturers WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        if ($count > 0) {
            return array("error" => "Manufacturer already exists");
        }

        $stmt = $this->mysqli->prepare("INSERT INTO manufacturers (name, website) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $website);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /*
     * Edit an existing manufacturer
     * @param int $id
     * @param string $name
     * @param string $website
     * @return bool
     */
    public function edit($id, $name, $website = "") {
        $id = (int) $id;
        $stmt = $this->mysqli->prepare("UPDATE manufacturers SET name = ?, website = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $website, $id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /*
     * Delete a manufacturer
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        $id = (int) $id;
        $stmt = $this->mysqli->prepare("DELETE FROM manufacturers WHERE id = ?");
        $stmt->bind_param("i", $id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
}