<?php
    /*
    *   Author: Generated for GleSYS API
    *   Date:   2025-11-06
    *   Purpose: Export DNS zone from GleSYS API to BIND format zone file
    */
    echo PHP_EOL;
    if (count($argv) < 4) {
        echo 'Err: 3 arguments required.'.PHP_EOL;
        echo 'Usage: php php_glesys_zone_export.php <domain> <api user> <api key> [output file]'.PHP_EOL;
        echo 'Example: php php_glesys_zone_export.php example.com CL12345 your-api-key zone.txt'.PHP_EOL;
        echo PHP_EOL;
        echo 'If output file is not specified, zone will be written to stdout.'.PHP_EOL;
        exit(1);
    }

    $domain     = $argv[1];
    $api_user   = $argv[2];
    $api_key    = $argv[3];
    $output_file = isset($argv[4]) ? $argv[4] : null;
    $api_url    = 'https://api.glesys.com/';

    $glesys = new glesys_api();
    $glesys->api_user = $api_user;
    $glesys->api_key = $api_key;
    $glesys->api_url = $api_url;

    echo 'Log: Fetching zone data for '.$domain.'...'.PHP_EOL;

    // Get domain details (SOA information)
    if (!$glesys->get_domain_details($domain)) {
        echo 'Err: Failed to fetch domain details!'.PHP_EOL;
        $error = $glesys->get_last_error();
        if ($error) {
            echo 'Err: '.$error['text'].PHP_EOL;
        }
        exit(1);
    }

    $details = $glesys->response;

    // Get all DNS records
    if (!$glesys->list_records($domain)) {
        echo 'Err: Failed to fetch DNS records!'.PHP_EOL;
        $error = $glesys->get_last_error();
        if ($error) {
            echo 'Err: '.$error['text'].PHP_EOL;
        }
        exit(1);
    }

    $records = $glesys->response['records'];

    echo 'Log: Found '.count($records).' records'.PHP_EOL;

    // Build zone file content
    $zone_content = generate_zone_file($domain, $details, $records);

    // Write to file or stdout
    if ($output_file) {
        if (file_put_contents($output_file, $zone_content) === false) {
            echo 'Err: Failed to write to file '.$output_file.PHP_EOL;
            exit(1);
        }
        echo 'Log: Zone file written to '.$output_file.PHP_EOL;
    } else {
        echo PHP_EOL;
        echo '--- Zone file content ---'.PHP_EOL;
        echo $zone_content;
        echo '--- End of zone file ---'.PHP_EOL;
    }

    echo 'Log: Export completed successfully.'.PHP_EOL;
    exit(0);

    /**
     * Generate BIND format zone file from GleSYS API data
     */
    function generate_zone_file($domain, $details, $records) {
        $output = '';

        // Add header comment
        $output .= '; Zone file for '.$domain.PHP_EOL;
        $output .= '; Exported from GleSYS API on '.date('Y-m-d H:i:s').PHP_EOL;
        $output .= ';'.PHP_EOL;

        // Extract SOA information from details (data is under 'domain' key)
        $domain_data = isset($details['domain']) ? $details['domain'] : array();
        $soa_ttl = isset($domain_data['ttl']) ? $domain_data['ttl'] : 3600;
        $primary_ns = isset($domain_data['primarynameserver']) ? $domain_data['primarynameserver'] : 'ns1.glesys.se.';
        $responsible = isset($domain_data['responsibleperson']) ? $domain_data['responsibleperson'] : 'registry.glesys.se.';
        $serial = date('Ymd').'01'; // Generate serial from current date
        $refresh = isset($domain_data['refresh']) ? $domain_data['refresh'] : 28800;
        $retry = isset($domain_data['retry']) ? $domain_data['retry'] : 7200;
        $expire = isset($domain_data['expire']) ? $domain_data['expire'] : 604800;
        $minimum = isset($domain_data['minimum']) ? $domain_data['minimum'] : 86400;

        // Ensure domain and nameserver end with dot
        $domain_fqdn = rtrim($domain, '.').'.';
        $primary_ns_fqdn = rtrim($primary_ns, '.').'.';
        $responsible_fqdn = rtrim($responsible, '.').'.';

        // Write SOA record
        $output .= '$ORIGIN '.$domain_fqdn.PHP_EOL;
        $output .= '$TTL '.$soa_ttl.PHP_EOL;
        $output .= '@    IN    SOA    '.$primary_ns_fqdn.' '.$responsible_fqdn.' ('.PHP_EOL;
        $output .= '                    '.$serial.'    ; Serial'.PHP_EOL;
        $output .= '                    '.$refresh.'    ; Refresh'.PHP_EOL;
        $output .= '                    '.$retry.'    ; Retry'.PHP_EOL;
        $output .= '                    '.$expire.'    ; Expire'.PHP_EOL;
        $output .= '                    '.$minimum.' )  ; Minimum TTL'.PHP_EOL;
        $output .= PHP_EOL;

        // Group records by type for better readability
        $records_by_type = array(
            'NS' => array(),
            'A' => array(),
            'AAAA' => array(),
            'MX' => array(),
            'CNAME' => array(),
            'TXT' => array(),
            'SRV' => array(),
            'CAA' => array(),
            'OTHER' => array()
        );

        foreach ($records as $record) {
            $type = strtoupper($record['type']);
            if (isset($records_by_type[$type])) {
                $records_by_type[$type][] = $record;
            } else {
                $records_by_type['OTHER'][] = $record;
            }
        }

        // Write records by type
        foreach ($records_by_type as $type => $type_records) {
            if (empty($type_records)) {
                continue;
            }

            $output .= '; '.$type.' Records'.PHP_EOL;

            foreach ($type_records as $record) {
                $output .= format_record($record, $domain);
            }

            $output .= PHP_EOL;
        }

        return $output;
    }

    /**
     * Format a single DNS record in BIND format
     */
    function format_record($record, $domain) {
        $host = $record['host'];
        $ttl = isset($record['ttl']) ? $record['ttl'] : '';
        $type = strtoupper($record['type']);
        $data = $record['data'];

        // Handle host field - convert to relative or @
        if ($host === $domain || $host === $domain.'.') {
            $host = '@';
        } elseif (substr($host, -strlen($domain)-1) === '.'.$domain) {
            // Remove domain suffix to make relative
            $host = substr($host, 0, -strlen($domain)-1);
        } elseif (substr($host, -strlen($domain)-2) === '.'.$domain.'.') {
            // Remove domain suffix with trailing dot
            $host = substr($host, 0, -strlen($domain)-2);
        }

        // Pad host field for alignment
        $host_padded = str_pad($host, 20);

        // Pad TTL field
        $ttl_padded = $ttl ? str_pad($ttl, 8) : str_pad('', 8);

        // Handle different record types
        switch ($type) {
            case 'MX':
                // MX records have priority in data field (format: "10 mail.example.com")
                $output = $host_padded.$ttl_padded.'IN    '.$type.'    '.$data.PHP_EOL;
                break;

            case 'TXT':
                // TXT records should be quoted
                if (substr($data, 0, 1) !== '"') {
                    $data = '"'.$data.'"';
                }
                $output = $host_padded.$ttl_padded.'IN    '.$type.'    '.$data.PHP_EOL;
                break;

            case 'SRV':
                // SRV records have priority, weight, port in data
                $output = $host_padded.$ttl_padded.'IN    '.$type.'    '.$data.PHP_EOL;
                break;

            case 'CAA':
                // CAA records need special formatting
                $output = $host_padded.$ttl_padded.'IN    '.$type.'    '.$data.PHP_EOL;
                break;

            default:
                // A, AAAA, CNAME, NS, etc.
                $output = $host_padded.$ttl_padded.'IN    '.$type.'    '.$data.PHP_EOL;
                break;
        }

        return $output;
    }

    // GleSYS API Client Class
    class glesys_api {
        public string $api_user;
        public string $api_key;
        public string $api_url;
        public ?idna_convert $punycode = null;
        public ?array $response = null;
        private ?array $last_error = null;

        function get_domain_details($domainname) {
            $args = array(
                'domainname' => $this->punycode_endoce($domainname)
            );
            $success = $this->api_request('domain/details', $args);
            return $success;
        }

        function list_records($domainname) {
            $args = array(
                'domainname' => $this->punycode_endoce($domainname)
            );
            $success = $this->api_request('domain/listrecords', $args);
            return $success;
        }

        function api_request($request, $args = array()) {
            $url = $this->api_url . $request . '/format/json';
            $ch = curl_init();

            curl_setopt_array($ch, array(
                CURLOPT_POST            => true,
                CURLOPT_POSTFIELDS      => $args,
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_SSL_VERIFYPEER  => false,
                CURLOPT_SSL_VERIFYHOST  => 0,
                CURLOPT_TIMEOUT         => 30,
                CURLOPT_URL             => $url,
                CURLOPT_USERPWD         => $this->api_user . ':' . $this->api_key,
                CURLOPT_CUSTOMREQUEST   => 'POST',
                CURLOPT_SSH_AUTH_TYPES  => CURLSSH_AUTH_ANY,
            ));

            $response = curl_exec($ch);
            if (empty($response)) {
                $this->last_error = array(
                    'code' => 0,
                    'text' => 'Empty response from API'
                );
                return false;
            }

            $decoded = json_decode($response, true);
            if (!$decoded) {
                $this->last_error = array(
                    'code' => 0,
                    'text' => 'Failed to decode JSON response'
                );
                return false;
            }

            $this->response = $decoded['response'];

            // Check return code
            if ($this->response['status']['code'] != 200) {
                $this->last_error = array(
                    'code' => $this->response['status']['code'],
                    'text' => $this->response['status']['text']
                );
                return false;
            }

            return true;
        }

        public function get_last_error() {
            return $this->last_error;
        }

        public function punycode_endoce($string) {
            if (empty($this->punycode)) {
                $this->punycode = new idna_convert();
            }
            return $this->punycode->encode($string);
        }

        public function punycode_dedoce($string) {
            if (empty($this->punycode)) {
                $this->punycode = new idna_convert();
            }
            return $this->punycode->decode($string);
        }
    }

    // Minimal IDNA/Punycode converter class
    class idna_convert {
        protected $_punycode_prefix = 'xn--';

        public function encode($input) {
            // Simple implementation - for international domains this should use full punycode
            // For now, just return as-is for ASCII domains
            if (preg_match('/^[a-zA-Z0-9.-]+$/', $input)) {
                return strtolower($input);
            }
            // For non-ASCII, would need full punycode encoding
            return $input;
        }

        public function decode($input) {
            return $input;
        }
    }
