<?php

/**
 * TPB_PARSER
 * 
 * This class Allows you to get and parse thepiratebay torrent pages and get all
 * usefull informations at once and/or comments in a nice array form. 
 * @author     Krzysztof Płaczek <kplaczek@wi.ps.pl>
 */
class TPB_parser {

    private $page = null;
    private $id;

    const BASEURL = 'http://thepiratebay.sx';

    /**
     * Pretty starndard function that returns content of a webpage using CURL. 
     * 2nd parameter is an array of post parameters. 
     * 
     * @param string $url webpage url
     * @param array $post array of post params
     * @return string raw webpage 
     */
    private static function c($url, $post = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        if (!empty($post)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    /**
     * This one returns a number of comments posted to a speccific torrent. 
     * @return int number of comments
     */
    private function getCommentsNumber() {
        //comments number
        preg_match('#NumComments">(\d+)</span>#ism', $this->page, $found);
        if (isset($found[1])) {
            return $found[1];
        } else {
            return 0;
        }
    }

    /**
     * This one is looking for a comments on a given as a parameters webpage and 
     * returns them as an assoc array that contains 3 keys to each entry. 
     * Username for name of a user. Comments time and comment itself.
     * @param string $page
     * @return array of comments 
     */
    private function searchForComments($page) {
        $comments = [];
        preg_match_all('#<div id="comment-\d{1,2}"><p class="byline">\s*?<a href="/user/(?P<username>.*?)/" title="Browse .*?">.*?</a> at (?P<time>.*?) CET:\s*</p><div class="comment">(?P<comment>.*?)</div>#ims', $page, $found);

        foreach ($found[0] as $key => $item) {
            $comments[] = ['username' => $found['username'][$key], 'time' => $found['time'][$key], 'comment' => $found['comment'][$key]];
        }
        return $comments;
    }

    /**
     * If private variable page is not empty then function returns the value 
     * but if it is function retrieve fresh content of a webpage.
     * @return string content of a webpage
     */
    private function getPage() {
        return ($this->page == null) ? $this->page = self::c(self::BASEURL . '/torrent/' . $this->id) : '';
    }

    /**
     * Function formats raw byte style value to human readable version 
     * eg. 1577747483 to 1.47GB Is't it nicer?
     * @param int $size_bytes
     * @return float
     */
    private function format_human($size_bytes) {
        $unit = 0;
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        while ($size_bytes > 1024) {
            $size_bytes /= 1024;
            $unit++;
        }
        return number_format($size_bytes, 2) . $units[$unit];
    }

    /**
     * Setter for torrent id. When current id is diffrent than new one $page is set to null
     * @param type $id
     * @throws \InvalidArgumentException if the id is nor numeric or positive
     */
    public function setId($id) {
        if (!is_numeric($id) || $id < 0) {
            throw new \InvalidArgumentException('Id must be numeric value');
        }
        if ($this->id != $id) {
            $this->page = null;
            $this->id = $id;
        }
    }

    /**
     *  Main parsing funcion. A lot of regex and other things 
     * @return array
     */
    public function torrentInfo() {
        $this->getPage();

        $result = [];

        //title
        preg_match('#<div id="title">(.*?)</div>#ims', $this->page, $found);
        if (isset($found[1]))
            $result['title'] = trim($found[1]);

        //category
        preg_match('#<a href="/browse/(\d*)" title="More from this category">(.*?)</a>#ism', $this->page, $found);
        if (isset($found[1]) && isset($found[2])) {
            $result['category_id'] = $found[1];
            $result['category_name'] = $found[2];
        }

        //size
        preg_match('#<dt>Size:</dt>\s*?<dd>.*?\((\d*)&nbsp;Bytes\)</dd>#ism', $this->page, $found);
        if (isset($found[1])) {
            $result['size_bytes'] = $found[1];
            $result['size_human'] = $this->format_human($result['size_bytes']);
        }

        //time
        preg_match('#<dt>Uploaded:</dt>\s*?<dd>(.*?)GMT</dd>#ism', $this->page, $found);
        if (isset($found[1])) {
            $date = new \DateTime($found[1]);
            $date->setTimezone(new \DateTimeZone('GMT'));
            $result['time'] = $date;
        }

        //seeders
        preg_match('#<dt>Seeders:</dt>\s*?<dd>(\d*)</dd>#ism', $this->page, $found);
        if (isset($found[1])) {
            $result['seeders'] = $found[1];
        }

        //leechers
        preg_match('#<dt>Leechers:</dt>\s*?<dd>(\d*)</dd>#ism', $this->page, $found);
        if (isset($found[1])) {
            $result['leechers'] = $found[1];
        }

        //infohash
        preg_match('#<dt>Info Hash:</dt><dd>&nbsp;</dd>\s*(.*?)\t#ism', $this->page, $found);
        if (isset($found[1])) {
            $result['hash'] = $found[1];
        }

        //magnet
        preg_match('#href="(magnet:?[^"]*)#ism', $this->page, $found);
        if (isset($found[1])) {
            $result['magnet_link'] = $found[1];
        }

        //info
        preg_match('#<div class="nfo">\s*?<pre>(.*?)</pre>\s*?</div>#ism', $this->page, $found);
        if (isset($found[1])) {
            $result['info'] = $found[1];
        }
        //author
        preg_match('#<dt>By:</dt>\s*?<dd>\s*?<a href="/user/(.*?)/"#ism', $this->page, $found);
        if (isset($found[1])) {
            $result['user'] = $found[1];
        }

        //tags
        preg_match_all('#href="/tag/(.*?)"#ism', $this->page, $found);
        if (isset($found[1][0])) {
            $result['tags'] = $found[1];
        }
        //files
        preg_match('#return false;">(\d*)</a>#ism', $this->page, $found);
        if (isset($found[1])) {
            $result['files'] = $found[1];
        }

        $result['comments_number'] = $this->getCommentsNumber();

        //texted languages
        preg_match('#<dt>Texted language\(s\):</dt>\s*<dd>(.+?)</dd>#ism', $this->page, $found);
        if (isset($found[1])) {
            $array = explode(',', $found[1]);
            array_walk($array, function(&$value, $index) {
                $value = trim($value);
            });
            $result['texted_languages'] = $array;
        }

        //spoken languages
        preg_match('#<dt>Spoken language\(s\):</dt>\s*<dd>(.+?)</dd>#ism', $this->page, $found);
        if (isset($found[1])) {
            $array = explode(',', $found[1]);
            array_walk($array, function(&$value, $index) {
                $value = trim($value);
            });
            $result['spoken_languages'] = $array;
        }

        //picture
        preg_match('#<img src="([^"]*)" title="picture" alt="picture" \/>#ism', $this->page, $found);
        if (isset($found[1])) {
            $result['picture'] = $found[1];
        }

        return $result;
    }

    /**
     * Returns comments from torrent. See *searchForComments* function
     * @see searchForComments function 
     * @return array with all of the comments posted to the specific torrent 
     */
    public function getComments() {
        $this->getPage();
        $pages = ceil($this->getCommentsNumber() / 25);

        $comments = [];

        //retrieve crc checksum 
        preg_match("#comPage\(\d+,\d+,'(?P<crc>\w{32})', '\d+'\)#ism", $this->page, $found);

        if (isset($found['crc']) && $pages > 1) {
            $crc = $found['crc'];
            for ($i = 1; $i <= $pages; $i++) {
                $post = ['id' => $this->id, 'page' => $i, 'pages' => $pages, 'crc' => $crc];
                $page = self::c(self::BASEURL . '/ajax_details_comments.php', $post);
                $comments = array_merge($comments, $this->searchForComments($page));
            }
        } else {
            $comments = array_merge($comments, $this->searchForComments($this->page));
        }

        return $comments;
    }

}
