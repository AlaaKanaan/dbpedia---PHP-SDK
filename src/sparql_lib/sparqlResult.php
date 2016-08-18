<?php
/**
 * Created by PhpStorm.
 * User: alaa
 * Date: 8/17/16
 * Time: 4:58 PM
 */

namespace DBpediaClient\sparql_lib;

class sparqlResult
{
    var $rows;
    var $fields;
    var $db;
    var $i = 0;

    function __construct($db, $rows, $fields)
    {
        $this->rows = $rows;
        $this->fields = $fields;
        $this->db = $db;
    }

    function fetch_array()
    {
        if (!@$this->rows[$this->i]) {
            return;
        }
        $r = array();
        foreach ($this->rows[$this->i++] as $k => $v) {
            $r[$k] = $v["value"];
            $r["$k.type"] = $v["type"];
            if (isset($v["language"])) {
                $r["$k.language"] = $v["language"];
            }
            if (isset($v["datatype"])) {
                $r["$k.datatype"] = $v["datatype"];
            }
        }
        return $r;
    }

    function fetch_all()
    {
        $r = new sparqlResult();
        $r->fields = $this->fields;
        foreach ($this->rows as $i => $row) {
            $r [] = $this->fetch_array();
        }
        return $r;
    }

    function num_rows()
    {
        return sizeof($this->rows);
    }

    function field_array()
    {
        return $this->fields;
    }

    function field_name($i)
    {
        return $this->fields[$i];
    }
}