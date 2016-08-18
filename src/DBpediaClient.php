<?php

namespace DBpediaClient;


use DBpediaClient\sparql_lib\sparqlConnection;

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
    private $connection;
    private $types;

    public function __construct()
    {
        $this->connection = new sparqlConnection($this->end_point);

        $this->types = ['company', 'provinceorstate', 'organization'];
    }


    public function validateEntities($entities, $type, $matching_type = 'exact')
    {
        if (!in_array($type, $this->types)) {
            throw new \Exception("invalid type:" . $type);
        }
        $query = $this->getSparqelFilterQuery($entities, $type, $matching_type);
        $result = $this->sparql_query($query);

        $response = [];
        while ($row = $this->sparql_fetch_array($result)) {
            $response[] = $row;
        }

        return $response;
    }

    private function sparql_ns($short, $long, $db = null)
    {
        return $this->_sparql_a_connection($db)->ns($short, $long);
    }

    private function sparql_query($sparql, $db = null)
    {
        return $this->_sparql_a_connection($db)->query($sparql);
    }

    private function sparql_errno($db = null)
    {
        return $this->_sparql_a_connection($db)->errno();
    }

    private function sparql_error($db = null)
    {
        return $this->_sparql_a_connection($db)->error();
    }

    private function sparql_fetch_array($result)
    {
        return $result->fetch_array();
    }

    private function sparql_num_rows($result)
    {
        return $result->num_rows();
    }

    private function sparql_field_array($result)
    {
        return $result->field_array();
    }

    private function sparql_field_name($result, $i)
    {
        return $result->field_name($i);
    }

    private function sparql_fetch_all($result)
    {
        return $result->fetch_all();
    }

    private function sparql_get($endpoint, $sparql)
    {
        $db = $this->sparql_connect($endpoint);
        if (!$db) {
            return;
        }
        $result = $db->query($sparql);
        if (!$result) {
            return;
        }
        return $result->fetch_all();
    }

    private function _sparql_a_connection($db)
    {
        global $sparql_last_connection;
        if (!isset($db)) {
            if (!isset($sparql_last_connection)) {
                print("No currect SPARQL connection (connection) in play!");
                return;
            }
            $db = $sparql_last_connection;
        }
        return $db;
    }


    public function getSparqelFilterQuery($entities, $entity_type, $matching_type = 'exact', $filters_query = null)
    {
        $filters_query .= 'FILTER(';

        $filters = '';
        foreach ($entities as $entity) {
            $filters .= 'regex(?label,"';
            if ($matching_type == 'exact') {
                $entity = str_replace('(', '', $entity['value']);
                $entity = str_replace(')', '', $entity);

                $filters .= '^' . $entity . '$';
            }
            $filters .= '",\'i\') ||';
        }
        $filters_query .= rtrim($filters, '||');
        $filters_query .= ')';


        switch ($entity_type) {
            case 'provinceorstate':
                return $query = self::sparql_query_prefix . " SELECT DISTINCT ?label ?country_name
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
                     select DISTINCT ?country_name ?label
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
                    select DISTINCT ?label
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

        }
    }


}