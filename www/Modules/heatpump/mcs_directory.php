<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

// Search the MCS product directory (https://mcscertified.com/product-directory/)
// for air source heat pumps and read their declared SCOP by flow temperature.
//
// There is no documented API. The directory page embeds a nonce and calls a
// WordPress AJAX endpoint that returns each product as JSON. SCOPValues is a
// PHP-serialised array of SCOP (average climate) at every flow temperature from
// 35 to 65 °C, with 0 where none is declared.
//
// Used by the admin product search on the heat pump page and by
// scripts/update_mcs_scop.php. Never called when a page is viewed.
class McsDirectory
{
    const DIRECTORY_URL = "https://mcscertified.com/product-directory/";
    const AJAX_URL = "https://mcscertified.com/wp-admin/admin-ajax.php";
    const USER_AGENT = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36";
    // The directory returns about 12 products a page; broad terms are cut short
    const MAX_PAGES = 20;

    private $nonce = null;

    private function get($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code != 200) return false;
        return $body;
    }

    private function nonce()
    {
        if ($this->nonce === null) {
            $html = $this->get(self::DIRECTORY_URL);
            if ($html && preg_match('/"nonce":"(\w+)"/', $html, $m)) $this->nonce = $m[1];
            else $this->nonce = false;
        }
        return $this->nonce;
    }

    // All air source heat pump products matching $term (name, model number,
    // certification number or manufacturer). Returns an array of products as
    // parsed by product(), or false if the directory can't be read.
    public function search($term)
    {
        $nonce = $this->nonce();
        if (!$nonce) return false;

        $products = array();
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $query = http_build_query(array(
                'action' => 'filter_products',
                'nonce' => $nonce,
                'form_type' => 'products',
                'search' => $term,
                'manufacturer' => '',
                'technology_type' => array('Air Source Heat Pump'),
                'results_per_page' => 100,
                'page' => $page
            ));
            $body = $this->get(self::AJAX_URL . "?" . $query);
            if (!$body) return false;
            $result = json_decode($body, true);
            if (!isset($result['data']['data'])) return false;

            foreach ($result['data']['data'] as $p) $products[] = self::product($p);

            $total_pages = isset($result['data']['pagination']['total_pages']) ? (int) $result['data']['pagination']['total_pages'] : 1;
            if ($page >= $total_pages) break;
        }
        return $products;
    }

    // One product by its MCS ProductID, found by searching for its
    // certification or model number. Returns null if it's no longer listed,
    // false if the directory can't be read.
    public function find($product_id, $term)
    {
        $products = $this->search($term);
        if ($products === false) return false;
        foreach ($products as $p) {
            if ($p['product_id'] == $product_id) return $p;
        }
        return null;
    }

    // The fields kept from a directory record
    public static function product($p)
    {
        return array(
            'product_id' => (int) $p['ProductID'],
            'name' => isset($p['Name']) ? $p['Name'] : '',
            'model_number' => isset($p['ModelNumber']) ? $p['ModelNumber'] : '',
            'certification_number' => isset($p['Code']) ? $p['Code'] : '',
            'manufacturer' => isset($p['Manufacturer']) ? $p['Manufacturer'] : '',
            'certified_to' => isset($p['CertifiedTo']) ? $p['CertifiedTo'] : '',
            'last_updated' => isset($p['LastUpdated']) ? $p['LastUpdated'] : '',
            'scop' => self::parse_scop(isset($p['SCOPValues']) ? $p['SCOPValues'] : '')
        );
    }

    // SCOPValues, e.g. a:31:{i:35;d:4.88;i:36;d:4.81;...}, to array(flow °C => SCOP)
    // for the declared temperatures only. Parsed with a regex rather than
    // unserialize() since it comes from a third party.
    public static function parse_scop($serialised)
    {
        $scop = array();
        if (preg_match_all('/i:(\d+);d:([\d.]+);/', (string) $serialised, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                if ((float) $row[2] > 0) $scop[(int) $row[1]] = (float) $row[2];
            }
        }
        ksort($scop);
        return $scop;
    }
}
