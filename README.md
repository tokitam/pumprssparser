# pumprssparser

General RSS/FEED parser library for PHP.

EXAMPLE
=========================
```
<?php
    $rssparser = new PumpRssParser();

    $url = 'http://www.example.com/rss.xml';
    $rssparser->parse($url);

    if ($rssparser->is_successful()) {
        var_dump($rssparser->result['items']);
    } else {
        echo 'error';
    }

```
