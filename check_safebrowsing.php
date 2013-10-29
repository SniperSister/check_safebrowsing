#!/usr/bin/php
<?php
/**
 * @version    1.0.0
 * @copyright  Copyright (C) 2013 David Jardin
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       http://www.djumla.de
 */

$config = array(
    "type" => "Ispcp",
    "apikey" => "",
    "dbhost" => "127.0.0.1",
    "dbuser" => "root",
    "db" => "test",
    "dbpasswd" => ""
);

/**
 * Class CheckSafebrowsing
 *
 * @since  1.0.0
 */
class CheckSafebrowsing
{
    private $config;

    private $response;

    private $info;

    private $infections = array();

    const LOOKUPURL = "https://sb-ssl.google.com/safebrowsing/api/lookup?client=api&apikey=%s&appver=1.0&pver=3.0";

    /**
     * pass config
     *
     * @param   array  $config  config array
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * execute app
     *
     * @return void
     */
    public function execute()
    {
        $this->makeRequest();

        switch ($this->parseResponse())
        {
            case 0:
                print "OK - no infections";
                exit(0);

            break;

            case 2:
                print "CRITICAL - Infected domains: " . implode(", ", $this->infections);
                exit(2);

            break;

            case 3:
                print "UNKNOWN - and error happened. Google status code: " . $this->info['http_code'];
                exit(3);

            break;
        }
    }

    /**
     * fire request
     *
     * @return void
     */
    private function makeRequest()
    {
        $dataProvider = "getDomains" . $this->config['type'];
        $domains = $this->$dataProvider();

        $url = sprintf(self::LOOKUPURL, $this->config['apikey']);

        $body = count($domains) . "\n";
        $body .= implode("\n", $domains);

        // Open connection
        $ch = curl_init();

        // Set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute post
        $this->response = curl_exec($ch);
        $this->info = curl_getinfo($ch);

        // Close connection
        curl_close($ch);
    }

    /**
     * parse response
     *
     * @return mixed response code
     */
    private function parseResponse()
    {
        switch ($this->info['http_code'])
        {
            case "200":
                $results = explode("\n", $this->response);

                $dataProvider = "getDomains" . $this->config['type'];
                $domains = $this->$dataProvider();

                foreach ($results as $i => $result)
                {
                    if ($result !== "ok")
                    {
                        $this->infections[] = $domains[$i];
                    }
                }

                return 2;
            break;

            case "400":
            case "401":
            case "503":
                return 3;

            case "204":
                return 0;
        }

        return false;
    }

    /**
     * return domains on an ispcp server
     *
     * @return array
     */
    private function getDomainsIspcp()
    {
        $dsn = 'mysql:dbname=' . $this->config['db'] . ';host=' . $this->config['dbhost'];
        $db = new PDO($dsn, $this->config['dbuser'], $this->config['dbpasswd']);

        $domains = array();

        $domainlist = $db->query("SELECT domain_name FROM domain WHERE domain_status='ok'");
        $aliaslist = $db->query("SELECT alias_name FROM domain_aliasses WHERE alias_status='ok'");

        foreach ($domainlist as $domain)
        {
            $domains[] = "http://www." . $domain[0] . "/";
        }

        foreach ($aliaslist as $alias)
        {
            $domains[] = "http://www." . $alias[0] . "/";
        }

        return $domains;
    }

    /**
     * return domains on an froxlor server
     *
     * @return array
     */
    private function getDomainsFroxlor()
    {
        $dsn = 'mysql:dbname=' . $this->config['db'] . ';host=' . $this->config['dbhost'];
        $db = new PDO($dsn, $this->config['dbuser'], $this->config['dbpasswd']);

        $domains = array();

        $domainlist = $db->query("SELECT domain FROM panel_domains");

        foreach ($domainlist as $domain)
        {
            $domains[] = "http://www." . $domain[0] . "/";
        }

        return $domains;
    }

    /**
     * return domains on an manual server
     *
     * @return array
     */
    private function getDomainsManual()
    {
        return array();
    }
}

// Execute
$app = new CheckSafebrowsing($config);
$app->execute();
