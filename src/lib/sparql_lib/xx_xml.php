<?php

namespace lib\sparql_lib;

/**
 * Created by PhpStorm.
 * User: alaa
 * Date: 8/17/16
 * Time: 4:58 PM
 */

# class xx_xml adapted code found at http://php.net/manual/en/function.xml-parse.php
# class is cc-by
# hello at rootsy dot co dot uk / 24-May-2008 09:30
class xx_xml
{

    // XML parser variables
    var $parser;
    var $name;
    var $attr;
    var $data = array();
    var $stack = array();
    var $keys;
    var $path;
    var $looks_legit = false;
    var $error;

    // either you pass url atau contents.
    // Use 'url' or 'contents' for the parameter
    var $type;

    // function with the default parameter value
    function xx_xml($url = 'http://www.opocot.com', $type = 'url')
    {
        $this->type = $type;
        $this->url = $url;
        $this->parse();
    }

    function error()
    {
        return $this->error;
    }

    // parse XML data
    function parse()
    {
        $this->rows = array();
        $this->fields = array();
        $data = '';
        $this->parser = xml_parser_create("UTF-8");
        xml_set_object($this->parser, $this);
        xml_set_element_handler($this->parser, 'startXML', 'endXML');
        xml_set_character_data_handler($this->parser, 'charXML');

        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);

        if ($this->type == 'url') {
            // if use type = 'url' now we open the XML with fopen

            if (!($fp = fopen($this->url, 'rb'))) {
                $this->error("Cannot open {$this->url}");
            }

            while (($data = fread($fp, 8192))) {
                if (!xml_parse($this->parser, $data, feof($fp))) {
                    $this->error = sprintf('XML error at line %d column %d',
                        xml_get_current_line_number($this->parser),
                        xml_get_current_column_number($this->parser));
                    return;
                }
            }
        } else if ($this->type == 'contents') {
            // Now we can pass the contents, maybe if you want
            // to use CURL, SOCK or other method.
            $lines = explode("\n", $this->url);
            foreach ($lines as $val) {
                $data = $val . "\n";
                if (!xml_parse($this->parser, $data)) {
                    $this->error = $data . "\n" . sprintf('XML error at line %d column %d',
                            xml_get_current_line_number($this->parser),
                            xml_get_current_column_number($this->parser));
                    return;
                }
            }
        }
        if (!$this->looks_legit) {
            $this->error = "Didn't even see a sparql element, is this really an endpoint?";
        }
    }

    function startXML($parser, $name, $attr)
    {
        if ($name == "sparql") {
            $this->looks_legit = true;
        }
        if ($name == "result") {
            $this->result = array();
        }
        if ($name == "binding") {
            $this->part = $attr["name"];
        }
        if ($name == "uri" || $name == "bnode") {
            $this->part_type = $name;
            $this->chars = "";
        }
        if ($name == "literal") {
            $this->part_type = "literal";
            if (isset($attr["datatype"])) {
                $this->part_datatype = $attr["datatype"];
            }
            if (isset($attr["xml:lang"])) {
                $this->part_lang = $attr["xml:lang"];
            }
            $this->chars = "";
        }
        if ($name == "variable") {
            $this->fields[] = $attr["name"];
        }
    }

    function endXML($parser, $name)
    {
        if ($name == "result") {
            $this->rows[] = $this->result;
            $this->result = array();
        }
        if ($name == "uri" || $name == "bnode" || $name == "literal") {
            $this->result[$this->part] = array("type" => $name, "value" => $this->chars);
            if (isset($this->part_lang)) {
                $this->result[$this->part]["lang"] = $this->part_lang;
            }
            if (isset($this->part_datatype)) {
                $this->result[$this->part]["datatype"] = $this->part_datatype;
            }
            $this->part_datatype = null;
            $this->part_lang = null;
        }
    }

    function charXML($parser, $data)
    {
        @$this->chars .= $data;
    }

}