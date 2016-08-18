<?php

namespace DBpediaClient;

/**
 * Class DBpediaClient
 * @package DBpediaClient
 */
class DBpediaClient
{

    const sparql_query_prefix = 'PREFIX dbp: <http://dbpedia.org/property/>
PREFIX dbo: <http://dbpedia.org/ontology/>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>';

    private $end_point = "http://dbpedia.org/sparql";


    public function validateEntities($entities, $type = null, $access_key = false, $matching_type = 'exact')
    {
        if (!$type) {
            throw new \Exception("invalid type:" . $type);
        }
        $query = $this->getSparqelFilterQuery($entities, $type, $access_key, $matching_type);
        $format = 'json';


        $searchUrl = $this->end_point . '?'
            . 'query=' . urlencode($query)
            . '&format=' . $format;

        $responseArray = json_decode(
            $this->request($searchUrl),
            true);

        if (isset($responseArray['results']['bindings']) && !count($responseArray['results']['bindings'])) {
            return false;
        }

        return $responseArray;
    }

    public function getFields($response)
    {
        if (!isset($response['head']['vars'])) {
            return false;
        }

        return $response['head']['vars'];
    }

    public function fetchArray($response)
    {

        $array = [];
        if (isset($response['results']['bindings'])) {
            foreach ($response['results']['bindings'] as $row) {
                if (isset($row['country_name'])) {
                    $array[$row['country_name']['value']][] =$row['label']['value'];
                } else {
                    $array[] = $row['label']['value'];
                }
            }
        } else {
            throw new \Exception("invalid data:");
        }
        return $array;
    }

    function request($url)
    {

        // is curl installed?
        if (!function_exists('curl_init')) {
            die('CURL is not installed!');
        }

        // get curl handle
        $ch = curl_init();


        // set request url
        curl_setopt($ch,
            CURLOPT_URL,
            $url);

        // return response, don't print/echo
        curl_setopt($ch,
            CURLOPT_RETURNTRANSFER,
            true);

        /*
        Here you find more options for curl:
        http://www.php.net/curl_setopt
        */

        $response = curl_exec($ch);
        $curl_errno = curl_errno($ch);

        if ($curl_errno > 0) {
            $this->request($url);
        }

        curl_close($ch);

        return $response;
    }

    public function getSparqelFilterQuery($entities, $entity_type, $access_key = false, $matching_type = 'exact', $filters_query = null)
    {
        $filters_query .= 'FILTER(';

        $filters = '';
        foreach ($entities as $entity) {
            $filters .= 'regex(?label,"';
            if ($access_key && isset($entity[$access_key])) {
                $entity = str_replace('(', '', $entity[$access_key]);
            } else {
                $entity = str_replace('(', '', $entity);
            }
            $entity = str_replace(')', '', $entity);
            if ($matching_type == 'exact') {
                $filters .= '^' . $entity . '$';
            }elseif($matching_type == 'partial'){
                $filters .= $entity;
            }
            $filters .= '",\'i\') ||';
        }
        $filters_query .= rtrim($filters, '||');
        $filters_query .= ')';


        switch ($entity_type) {
            case 'provinceorstate':
                return $query = self::sparql_query_prefix . " SELECT DISTINCT  ?label ?country_name
WHERE {
  {
    ?city rdf:type dbo:City ;
         rdfs:label ?label;
  		 dbo:country ?country.
  		 ?country rdfs:label ?country_name.
         FILTER ( lang(?country_name) = 'en' && lang(?label) = 'en').
         " . $filters_query . "
  }
   union
  {
     ?region rdf:type dbo:Region ;
         rdfs:label ?label;
         dbo:country ?country.
  		 ?country rdfs:label ?country_name.
         FILTER ( lang(?country_name) = 'en' && lang(?label) = 'en').
         " . $filters_query . "
  }
  }
 ";
                break;
            case 'company':

                return self::sparql_query_prefix . "
                     select DISTINCT ?label
where {
                    ?q a dbo:Company .
  ?q dbp:name ?name.
  ?q rdfs:label ?label.
    FILTER (LANG(?label)='en').
    " . $filters_query . "
    }";
                break;
            case 'organization':
                return self::sparql_query_prefix . "
                    select DISTINCT  ?label
where {
                    ?q a dbo:Organisation .
  ?q dbp:name ?name.
  ?q rdfs:label ?label.
  ?q rdf:type ?type.
    FILTER (LANG(?label)='en')
  FILTER (?type=<http://dbpedia.org/ontology/Organisation>).
    " . $filters_query . "
    }";
                break;

            default:
                throw new \Exception("invalid type:" . $entity_type);
                break;

        }
    }
}