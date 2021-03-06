<?php

class buf_regex
{
    public $re;
    function __construct($re)
    {
        $this->re = $re;
    }
}

/*
 * A string buffer with functions for parsing
 */
class buf
{
    private $str;
    private $pos;
    private $len;

    /*
	 * Line and column counters
	 */
    private $line = 1;
    private $col = 1;
    private $linelengths = array();

    static function regex($re)
    {
        return new buf_regex($re);
    }

    function __construct($str)
    {
        $this->str = $str;
        $this->pos = 0;
        $this->len = strlen($str);
    }

    function ended()
    {
        return $this->pos >= $this->len;
    }

    function pos()
    {
        return "$this->line:$this->col";
    }

    function peek()
    {
        if ($this->pos >= $this->len) {
            return null;
        }
        return $this->str[$this->pos];
    }

    function get()
    {
        if ($this->pos >= $this->len) {
            return null;
        }
        $ch = $this->str[$this->pos++];
        if ($ch == "\n") {
            $this->linelengths[] = $this->col;
            $this->line++;
            $this->col = 1;
        } else {
            $this->col++;
        }
        return $ch;
    }

    function unget($ch)
    {
        if (strlen($ch) != 1) {
            throw new Exception("got " . $ch);
        }
        $this->pos--;
        assert($this->str[$this->pos] == $ch);
        if ($ch == "\n") {
            $this->line--;
            $this->col = array_pop($this->linelengths);
        } else {
            $this->col--;
        }
    }

    function context($n = 10)
    {
        $s = '';

        $len = $n;
        $p = $this->pos - $len;
        if ($p < 0) {
            $len += $p;
            $p = 0;
        }
        if ($len > 0) {
            $s .= substr($this->str, $p, $len);
        }

        $s .= $this->fcontext($n);
        return $s;
    }

    function fcontext($n = 10)
    {
        $s = '{' . $this->str[$this->pos] . '}';
        $s .= substr($this->str, $this->pos + 1, $n);
        $s = str_replace(array("\r", "\n", "\t"), array(
            "\\r",
            "\\n",
            "\\t"
        ), $s);
        return $s;
    }

    function expect($str)
    {
        $n = strlen($str);
        if ($n === 0) {
            return '';
        }
        $actual = substr($this->str, $this->pos, $n);
        if ($actual !== $str) {
            throw new Exception("expected '$str', got '$actual'");
        }
        $this->pos += $n;
        return $str;
    }

    /*
	 * Skips any sequence of characters from the given string.
	 * Returns the skipped string.
	 */
    function read_set($set)
    {
        $match = function ($ch) use ($set) {
            if ($set instanceof buf_regex) {
                return preg_match($set->re, $ch);
            }
            return strpos($set, $ch) !== false;
        };

        $s = '';
        while (($ch = $this->get()) !== null) {
            if (!$match($ch)) {
                $this->unget($ch);
                break;
            }
            $s .= $ch;
        }
        return $s;
    }

    function skip_literal($s)
    {
        $n = strlen($s);
        if (!$this->literal_follows($s)) {
            return '';
        }
        while ($n > 0) {
            $n--;
            $this->get();
        }
        return $s;
    }

    function until($str)
    {
        $s = '';
        while (!$this->ended() && !$this->literal_follows($str)) {
            $s .= $this->get();
        }
        return $s;
    }

    function rest()
    {
        $s = '';
        while (!$this->ended()) {
            $s .= $this->get();
        }
        return $s;
    }

    private function literal_follows($s)
    {
        $n = strlen($s);
        return substr($this->str, $this->pos, $n) == $s;
    }

    function skip_until($ch)
    {
        // Only single characters are supported
        assert(strlen($ch) == 1);

        $s = '';
        while (!$this->ended() && $this->peek() != $ch) {
            $s .= $this->get();
        }
        return $s;
    }
}
