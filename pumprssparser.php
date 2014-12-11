<?php

/**
 * Copyright 2014 Masahiko tokita (github.com/tokitam)
 * 
 * 
 * Licensed under the Apache License, Version 2.0 (the "License"); 
 * you may not use this file except in compliance with the License. 
 * You may obtain a copy of the License at 
 * 
 *    http://www.apache.org/licenses/LICENSE-2.0 
 *        
 * Unless required by applicable law or agreed to in writing, software 
 * distributed under the License is distributed on an "AS IS" BASIS, 
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. 
 * See the License for the specific language governing permissions and 
 * limitations under the License.
 */

require_once 'xml_parser.inc';

class PumpRssParser {
    const TYPE_RSS1 = 1;
    const TYPE_RSS2 = 2;
    const TYPE_ATOM = 3;
    const TYPE_UNKNOWN = 10;
    const TYPE_ERROR = 11;
    
    private $url;
    private $parser;
    public $xml;
    public $type;
    public $body;
    public $result;
    public $detected_feed_url;

    function __construct() {
    }
    
    function parse($url) {
        $this->url = $url;
        $this->xml = @file_get_contents($this->url);
        if ($this->xml == false) {
            $this->type = self::TYPE_ERROR;
            return;
        }
        $this->parser = new xml_parser();
        $this->body = $this->parser->parse($this->xml);

        $this->detect_type();
        $this->analysis();
    }
    
    function detect_type() {
        if (isset($this->body['rdf:RDF'])) {
            $this->type = self::TYPE_RSS1;
        } else if (isset($this->body['rss'])) {
            $this->type = self::TYPE_RSS2;
        } else if (isset($this->body['feed'])) {
            $this->type = self::TYPE_ATOM;
        } else {
            $this->type = self::TYPE_UNKNOWN;
        }
    }
    
    function detect_feed_url() {
        if (preg_match('/(http)(:\/\/[-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)(\.rdf|\.xml|\?xml|\/feed\/?)/', $this->xml, $r)) {
            $this->detected_feed_url = $r[0];
        } else {
            $this->detected_feed_url = null;
        }
    }
    
    function analysis() {
        $this->result = array();
        
        if ($this->type == self::TYPE_RSS1) {
            $this->analysis_rss1();
        } else if ($this->type == self::TYPE_RSS2) {
            $this->analysis_rss2();
        } else if ($this->type == self::TYPE_ATOM) {
            $this->analysis_atom();
        }
    }
    
    function analysis_rss1() {
        $body = $this->body['rdf:RDF'];
        $this->result['title'] = $body['channel']['value']['title'];
        $this->result['url'] = $body['channel']['value']['link'];
        $this->result['items'] = array();

        $items = $body['item'];
        $last_update = 0;
        foreach ($items as $key => $item) {
            $i = array();
            $i['title'] = @$item['value']['title'];
            $i['url'] = @$item['value']['link'];
            $i['description'] = @$item['value']['description'];
            $i['time'] = $this->date2unixtime(@$item['value']['dc:date']);
            if ($last_update < $i['time']) {
                $last_update = $i['time'];
            }
            
            array_push($this->result['items'], $i);
        }
        $this->result['last_update'] = $last_update;
    }
    
    function analysis_rss2() {
        $body = $this->body['rss']['channel'];
        $this->result['title'] = $body['title'];
        $this->result['url'] = $body['link'];
        $this->result['items'] = array();
        
        $items = $body['item'];
        $last_update = 0;
        foreach ($items as $key => $item) {
            $i = array();
            $i['title'] = $item['title'];
            $i['url'] = $item['link'];
            $i['description'] = @$item['description'];
            $i['time'] = $this->date2unixtime($item['pubDate']);
            if ($last_update < $i['time']) {
                $last_update = $i['time'];
            }
            
            array_push($this->result['items'], $i);
        }
        $this->result['last_update'] = $last_update;
    }
    
    function analysis_atom() {
        $body = $this->body['feed'];
        if (isset($body['title']['value'])) {
            $this->result['title'] = $body['title']['value'];
        } else {
            $this->result['title'] = $body['title'];
        }
        $this->result['url'] = @$body['link']['_attrs']['href'];
        $this->result['items'] = array();

        $items = $body['entry'];
        $last_update = 0;
        foreach ($items as $key => $item) {
            $i = array();
            $i['title'] = trim($item['title']);
            if ($i['title'] == '') {
                $i['title'] = $item['title']['value'];
            }
            $i['url'] = $item['link']['_attrs']['href'];
            $i['description'] = trim($item['content']['value']);
            if ($i['description'] == '') {
                $i['description'] = $item['summary']['value'];
            }
            if (@$item['issued']) {
                $i['time'] = $this->date2unixtime($item['issued']);
            } else {
                $i['time'] = $this->date2unixtime($item['published']);
            }
            if ($last_update < $i['time']) {
                $last_update = $i['time'];
            }
            
            array_push($this->result['items'], $i);
        }
        $this->result['last_update'] = $last_update;
    }
    
    function date2unixtime($date) {
        if(preg_match( '/^[a-zA-Z]/' , trim($date))) {
            $unixtime = strtotime( $date ) ;
            if(0 < $unixtime) {
                return $unixtime;
            }
        }

        if (preg_match('/^(\d\d\d\d)-(\d\d)-(\d\d)T(\d\d):(\d\d):(\d\d)/', $date, $r)) {
            $unixtime = mktime($r[4], $r[5], $r[6], $r[2], $r[3], $r[1]);
            return $unixtime;
        }
        
        return 0;
    }
    
    function is_successful() {
        if ($this->type == self::TYPE_RSS1 || $this->type == self::TYPE_RSS2 || $this->type == self::TYPE_ATOM) {
            return true;
        } else {
            return false;
        }
    }
}

