<?php


namespace DBpediaClient\sparql_lib;

/**
 * Created by PhpStorm.
 * User: alaa
 * Date: 8/17/16
 * Time: 4:56 PM
 */

#	$timeout = 20;
#	$old = ini_set('default_socket_timeout', $timeout);
#	ini_set('default_socket_timeout', $old);
class sparqlConnection
{
    var $db;
    var $debug = false;
    var $errno = null;
    var $error = null;
    var $ns = array();
    var $params = null;

    # capabilities are either true, false or null if not yet tested.

    function __construct($endpoint)
    {
        $this->endpoint = $endpoint;
        global $sparql_last_connection;
        $sparql_last_connection = $this;
    }

    function ns($short, $long)
    {
        $this->ns[$short] = $long;
    }

    function errno()
    {
        return $this->errno;
    }

    function error()
    {
        return $this->error;
    }

    function cgiParams($params = null)
    {
        if ($params === null) {
            return $this->params;
        }
        if ($params === "") {
            $this->params = null;
            return;
        }
        $this->params = $params;
    }

    function query($query, $timeout = null)
    {
        $prefixes = "";
        foreach ($this->ns as $k => $v) {
            $prefixes .= "PREFIX $k: <$v>\n";
        }
        $output = $this->dispatchQuery($prefixes . $query, $timeout);
        if ($this->errno) {
            return;
        }
        $parser = new xx_xml($output, 'contents');
        if ($parser->error()) {
            $this->errno = -1; # to not clash with CURLOPT return; }
            $this->error = $parser->error();
            return;
        }
        return new sparql_result($this, $parser->rows, $parser->fields);
    }

    function alive($timeout = 3)
    {
        $result = $this->query("SELECT * WHERE { ?s ?p ?o } LIMIT 1", $timeout);

        if ($this->errno) {
            return false;
        }

        return true;
    }

    function dispatchQuery($sparql, $timeout = null)
    {
        $url = $this->endpoint . "?query=" . urlencode($sparql);
        if ($this->params !== null) {
            $url .= "&" . $this->params;
        }
        if ($this->debug) {
            print "<div class='debug'><a href='" . htmlspecialchars($url) . "'>" . htmlspecialchars($prefixes . $query) . "</a></div>\n";
        }
        $this->errno = null;
        $this->error = null;
        $ch = curl_init($url);
        #curl_setopt($ch, CURLOPT_HEADER, 1);
        if ($timeout !== null) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Accept: application/sparql-results+xml"
        ));

        $output = curl_exec($ch);
        $info = curl_getinfo($ch);
        if (curl_errno($ch)) {
            $this->errno = curl_errno($ch);
            $this->error = 'Curl error: ' . curl_error($ch);
            return;
        }
        if ($output === '') {
            $this->errno = "-1";
            $this->error = 'URL returned no data';
            return;
        }
        if ($info['http_code'] != 200) {
            $this->errno = $info['http_code'];
            $this->error = 'Bad response, ' . $info['http_code'] . ': ' . $output;
            return;
        }
        curl_close($ch);

        return $output;
    }

    ####################################
    # Endpoint Capability Testing
    ####################################

    # This section is very limited right now. I plan, in time, to
    # caching so it can save results to a cache to save re-doing them
    # and many more capability options (suggestions to cjg@ecs.soton.ac.uk)

    var $caps = array();
    var $caps_desc = array(
        "select" => "Basic SELECT",
        "constant_as" => "SELECT (\"foo\" AS ?bar)",
        "math_as" => "SELECT (2+3 AS ?bar)",
        "count" => "SELECT (COUNT(?a) AS ?n) ?b ... GROUP BY ?b",
        "max" => "SELECT (MAX(?a) AS ?n) ?b ... GROUP BY ?b",
        "sample" => "SELECT (SAMPLE(?a) AS ?n) ?b ... GROUP BY ?b",
        "load" => "LOAD <...>",
    );

    var $caps_cache;
    var $caps_anysubject;

    function capabilityCache($filename, $dba_type = 'db4')
    {
        $this->caps_cache = dba_open($filename, "c", $dba_type);
    }

    function capabilityCodes()
    {
        return array_keys($this->caps_desc);
    }

    function capabilityDescription($code)
    {
        return $this->caps_desc[$code];
    }

    # return true if the endpoint supports a capability
    # nb. returns false if connecion isn't authoriased to use the feature, eg LOAD
    function supports($code)
    {
        if (isset($this->caps[$code])) {
            return $this->caps[$code];
        }
        $was_cached = false;
        if (isset($this->caps_cache)) {
            $CACHE_TIMEOUT_SECONDS = 7 * 24 * 60 * 60;
            $db_key = $this->endpoint . ";" . $code;
            $db_val = dba_fetch($db_key, $this->caps_cache);
            if ($db_val !== false) {
                list($result, $when) = preg_split('/;/', $db_val);
                if ($when + $CACHE_TIMEOUT_SECONDS > time()) {
                    return $result;
                }
                $was_cached = true;
            }
        }
        $r = null;

        if ($code == "select") {
            $r = $this->test_select();
        } elseif ($code == "constant_as") {
            $r = $this->test_constant_as();
        } elseif ($code == "math_as") {
            $r = $this->test_math_as();
        } elseif ($code == "count") {
            $r = $this->test_count();
        } elseif ($code == "max") {
            $r = $this->test_max();
        } elseif ($code == "load") {
            $r = $this->test_load();
        } elseif ($code == "sample") {
            $r = $this->test_sample();
        } else {
            print "<p>Unknown capability code: '$code'</p>";
            return false;
        }
        $this->caps[$code] = $r;
        if (isset($this->caps_cache)) {
            $db_key = $this->endpoint . ";" . $code;
            $db_val = $r . ";" . time();
            if ($was_cached) {
                dba_replace($db_key, $db_val, $this->caps_cache);
            } else {
                dba_insert($db_key, $db_val, $this->caps_cache);
            }
        }
        return $r;
    }

    function anySubject()
    {
        if (!isset($this->caps_anysubject)) {
            $results = $this->query(
                "SELECT * WHERE { ?s ?p ?o } LIMIT 1");
            if (sizeof($results)) {
                $row = $results->fetch_array();
                $this->caps_anysubject = $row["s"];
            }
        }
        return $this->caps_anysubject;
    }

    # return true if the endpoint supports SELECT
    function test_select()
    {
        $output = $this->dispatchQuery(
            "SELECT ?s ?p ?o WHERE { ?s ?p ?o } LIMIT 1");
        return !isset($this->errno);
    }

    # return true if the endpoint supports AS
    function test_math_as()
    {
        $output = $this->dispatchQuery(
            "SELECT (1+2 AS ?bar) WHERE { ?s ?p ?o } LIMIT 1");
        return !isset($this->errno);
    }

    # return true if the endpoint supports AS
    function test_constant_as()
    {
        $output = $this->dispatchQuery(
            "SELECT (\"foo\" AS ?bar) WHERE { ?s ?p ?o } LIMIT 1");
        return !isset($this->errno);
    }

    # return true if the endpoint supports SELECT (COUNT(?x) as ?n) ... GROUP BY
    function test_count()
    {
        # assumes at least one rdf:type predicate
        $s = $this->anySubject();
        if (!isset($s)) {
            return false;
        }
        $output = $this->dispatchQuery(
            "SELECT (COUNT(?p) AS ?n) ?o WHERE { <$s> ?p ?o } GROUP BY ?o");
        return !isset($this->errno);
    }

    function test_max()
    {
        $s = $this->anySubject();
        if (!isset($s)) {
            return false;
        }
        $output = $this->dispatchQuery(
            "SELECT (MAX(?p) AS ?max) ?o WHERE { <$s> ?p ?o } GROUP BY ?o");
        return !isset($this->errno);
    }

    function test_sample()
    {
        $s = $this->anySubject();
        if (!isset($s)) {
            return false;
        }
        $output = $this->dispatchQuery(
            "SELECT (SAMPLE(?p) AS ?sam) ?o WHERE { <$s> ?p ?o } GROUP BY ?o");
        return !isset($this->errno);
    }

    function test_load()
    {
        $output = $this->dispatchQuery(
            "LOAD <http://graphite.ecs.soton.ac.uk/sparqllib/examples/loadtest.rdf>");
        return !isset($this->errno);
    }


}