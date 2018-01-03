<?php

namespace Ions\Mail\Protocol;

/**
 * Class Imap
 * @package Ions\Mail\Protocol
 */
class Imap
{
    const TIMEOUT_CONNECTION = 30;

    /**
     * @var
     */
    protected $socket;

    /**
     * @var int
     */
    protected $tagCount = 0;

    /**
     * Imap constructor.
     * @param string $host
     * @param null $port
     * @param bool $ssl
     */
    public function __construct($host = '', $port = null, $ssl = false)
    {
        if ($host) {
            $this->connect($host, $port, $ssl);
        }
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->logout();
    }

    /**
     * @param $host
     * @param null $port
     * @param bool $ssl
     * @throws \RuntimeException
     */
    public function connect($host, $port = null, $ssl = false)
    {
        $isTls = false;
        if ($ssl) {
            $ssl = strtolower($ssl);
        }
        switch ($ssl) {
            case 'ssl':
                $host = 'ssl://' . $host;
                if (!$port) {
                    $port = 993;
                }
                break;
            case 'tls':
                $isTls = true;
            default:
                if (!$port) {
                    $port = 143;
                }
        }

        $this->socket = fsockopen($host, $port, $errno, $errstr, self::TIMEOUT_CONNECTION);

        if (!$this->socket) {
            throw new \RuntimeException('cannot connect to host');
        }

        if (!$this->assumedNextLine('* OK')) {
            throw new \RuntimeException('host doesn\'t allow connection');
        }

        if ($isTls) {
            $result = $this->requestAndResponse('STARTTLS');
            $result = $result && stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$result) {
                throw new \RuntimeException('cannot enable TLS');
            }
        }
    }

    /**
     * @return bool|string
     * @throws \RuntimeException
     */
    protected function nextLine()
    {
        $line = fgets($this->socket);
        if ($line === false) {
            throw new \RuntimeException('cannot read - connection closed?');
        }
        return $line;
    }

    /**
     * @param $start
     * @return bool
     */
    protected function assumedNextLine($start)
    {
        $line = $this->nextLine();
        return strpos($line, $start) === 0;
    }

    /**
     * @param $tag
     * @return bool|string
     */
    protected function nextTaggedLine(&$tag)
    {
        $line = $this->nextLine();
        list($tag, $line) = explode(' ', $line, 2);
        return $line;
    }

    /**
     * @param $line
     * @return array|mixed
     */
    protected function decodeLine($line)
    {
        $tokens = [];
        $stack = [];
        $line = rtrim($line) . ' ';
        while (($pos = strpos($line, ' ')) !== false) {
            $token = substr($line, 0, $pos);
            if (!strlen($token)) {
                continue;
            }
            while ($token[0] === '(') {
                array_push($stack, $tokens);
                $tokens = [];
                $token = substr($token, 1);
            }
            if ($token[0] === '"') {
                if (preg_match('%^\(*"((.|\\\\|\\")*?)" *%', $line, $matches)) {
                    $tokens[] = $matches[1];
                    $line = substr($line, strlen($matches[0]));
                    continue;
                }
            }
            if ($token[0] === '{') {
                $endPos = strpos($token, '}');
                $chars = substr($token, 1, $endPos - 1);
                if (is_numeric($chars)) {
                    $token = '';
                    while (strlen($token) < $chars) {
                        $token .= $this->nextLine();
                    }
                    $line = '';
                    if (strlen($token) > $chars) {
                        $line = substr($token, $chars);
                        $token = substr($token, 0, $chars);
                    } else {
                        $line .= $this->nextLine();
                    }
                    $tokens[] = $token;
                    $line = trim($line) . ' ';
                    continue;
                }
            }
            if ($stack && $token[strlen($token) - 1] === ')') {
                $braces = strlen($token);
                $token = rtrim($token, ')');
                $braces -= strlen($token) + 1;
                if (rtrim($token) !== '') {
                    $tokens[] = rtrim($token);
                }
                $token = $tokens;
                $tokens = array_pop($stack);
                while ($braces-- > 0) {
                    $tokens[] = $token;
                    $token = $tokens;
                    $tokens = array_pop($stack);
                }
            }
            $tokens[] = $token;
            $line = substr($line, $pos + 1);
        }
        while ($stack) {
            $child = $tokens;
            $tokens = array_pop($stack);
            $tokens[] = $child;
        }
        return $tokens;
    }

    /**
     * @param array $tokens
     * @param string $wantedTag
     * @param bool $dontParse
     * @return bool
     */
    public function readLine(&$tokens = [], $wantedTag = '*', $dontParse = false)
    {
        $tag = null;
        $line = $this->nextTaggedLine($tag);
        if (!$dontParse) {
            $tokens = $this->decodeLine($line);
        } else {
            $tokens = $line;
        }
        return $tag == $wantedTag;
    }

    /**
     * @param $tag
     * @param bool $dontParse
     * @return array|bool|null
     */
    public function readResponse($tag, $dontParse = false)
    {
        $lines = [];
        $tokens = null;
        while (!$this->readLine($tokens, $tag, $dontParse)) {
            $lines[] = $tokens;
        }
        if ($dontParse) {
            $tokens = [substr($tokens, 0, 2)];
        }
        if ($tokens[0] === 'OK') {
            return $lines ? $lines : true;
        } elseif ($tokens[0] === 'NO') {
            return false;
        }
        return null;
    }

    /**
     * @param $command
     * @param array $tokens
     * @param null $tag
     * @throws \RuntimeException
     */
    public function sendRequest($command, $tokens = [], &$tag = null)
    {
        if (!$tag) {
            ++$this->tagCount;
            $tag = 'TAG' . $this->tagCount;
        }
        $line = $tag . ' ' . $command;
        foreach ($tokens as $token) {
            if (is_array($token)) {
                if (fwrite($this->socket, $line . ' ' . $token[0] . "\r\n") === false) {
                    throw new \RuntimeException('cannot write - connection closed?');
                }
                if (!$this->assumedNextLine('+ ')) {
                    throw new \RuntimeException('cannot send literal string');
                }
                $line = $token[1];
            } else {
                $line .= ' ' . $token;
            }
        }
        if (fwrite($this->socket, $line . "\r\n") === false) {
            throw new \RuntimeException('cannot write - connection closed?');
        }
    }

    /**
     * @param $command
     * @param array $tokens
     * @param bool $dontParse
     * @return array|bool|null
     */
    public function requestAndResponse($command, $tokens = [], $dontParse = false)
    {
        $tag = null;
        $this->sendRequest($command, $tokens, $tag);
        return$this->readResponse($tag, $dontParse);
    }

    /**
     * @param $string
     * @return array|string
     */
    public function escapeString($string)
    {
        if (func_num_args() < 2) {
            if (strpos($string, "\n") !== false) {
                return ['{' . strlen($string) . '}', $string];
            } else {
                return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $string) . '"';
            }
        }
        $result = [];
        foreach (func_get_args() as $string) {
            $result[] = $this->escapeString($string);
        }
        return $result;
    }

    /**
     * @param $list
     * @return string
     */
    public function escapeList($list)
    {
        $result = [];
        foreach ($list as $v) {
            if (!is_array($v)) {
                $result[] = $v;
                continue;
            }
            $result[] = $this->escapeList($v);
        }
        return '(' . implode(' ', $result) . ')';
    }

    /**
     * @param $user
     * @param $password
     * @return array|bool|null
     */
    public function login($user, $password)
    {
        return $this->requestAndResponse('LOGIN', $this->escapeString($user, $password), true);
    }

    /**
     * @return array|bool|null
     */
    public function logout()
    {
        $result = false;
        if ($this->socket) {
            try {
                $result = $this->requestAndResponse('LOGOUT', [], true);
            } catch (\Exception $e) {
            }
            fclose($this->socket);
            $this->socket = null;
        }
        return $result;
    }

    /**
     * @return array|bool|null
     */
    public function capability()
    {
        $response = $this->requestAndResponse('CAPABILITY');
        if (!$response) {
            return $response;
        }
        $capabilities = [];
        foreach ($response as $line) {
            $capabilities = array_merge($capabilities, $line);
        }
        return $capabilities;
    }

    /**
     * @param string $command
     * @param string $box
     * @return array|bool
     */
    public function examineOrSelect($command = 'EXAMINE', $box = 'INBOX')
    {
        $tag = null;
        $this->sendRequest($command, [$this->escapeString($box)], $tag);
        $result = [];
        $tokens = null;
        while (!$this->readLine($tokens, $tag)) {
            if ($tokens[0] === 'FLAGS') {
                array_shift($tokens);
                $result['flags'] = $tokens;
                continue;
            }
            switch ($tokens[1]) {
                case 'EXISTS':
                case 'RECENT':
                    $result[strtolower($tokens[1])] = $tokens[0];
                    break;
                case '[UIDVALIDITY':
                    $result['uidvalidity'] = (int)$tokens[2];
                    break;
                default:
            }
        }
        if ($tokens[0] !== 'OK') {
            return false;
        }
        return $result;
    }

    /**
     * @param string $box
     * @return array|bool
     */
    public function select($box = 'INBOX')
    {
        return $this->examineOrSelect('SELECT', $box);
    }

    /**
     * @param string $box
     * @return array|bool
     */
    public function examine($box = 'INBOX')
    {
        return $this->examineOrSelect('EXAMINE', $box);
    }

    /**
     * @param $items
     * @param $from
     * @param null $to
     * @param bool $uid
     * @return array
     * @throws \RuntimeException
     */
    public function fetch($items, $from, $to = null, $uid = false)
    {
        if (is_array($from)) {
            $set = implode(',', $from);
        } elseif ($to === null) {
            $set = (int)$from;
        } elseif ($to === INF) {
            $set = (int)$from . ':*';
        } else {
            $set = (int)$from . ':' . (int)$to;
        }
        $items = (array)$items;
        $itemList = $this->escapeList($items);
        $tag = null;
        $this->sendRequest(($uid ? 'UID ' : '') . 'FETCH', [$set, $itemList], $tag);
        $result = [];
        $tokens = null;
        while (!$this->readLine($tokens, $tag)) {
            if ($tokens[1] !== 'FETCH') {
                continue;
            }
            if ($uid) {
                $count = count($tokens[2]);
                if ($tokens[2][$count - 2] === 'UID') {
                    $uidKey = $count - 1;
                } else {
                    $uidKey = array_search('UID', $tokens[2]) + 1;
                }
            }
            if ($to === null && !is_array($from) && ($uid ? $tokens[2][$uidKey] != $from : $tokens[0] != $from)) {
                continue;
            }
            if (count($items) == 1) {
                if ($tokens[2][0] == $items[0]) {
                    $data = $tokens[2][1];
                } elseif ($uid && $tokens[2][2] == $items[0]) {
                    $data = $tokens[2][3];
                } else {
                    $count = count($tokens[2]);
                    for ($i = 2; $i < $count; $i += 2) {
                        if ($tokens[2][$i] != $items[0]) {
                            continue;
                        }
                        $data = $tokens[2][$i + 1];
                        break;
                    }
                }
            } else {
                $data = [];
                while (key($tokens[2]) !== null) {
                    $data[current($tokens[2])] = next($tokens[2]);
                    next($tokens[2]);
                }
            }

            if ($to === null && !is_array($from) && ($uid ? $tokens[2][$uidKey] === $from : $tokens[0] === $from)) {
                while (!$this->readLine($tokens, $tag)) {}
                return $data;
            }

            $result[$tokens[0]] = $data;
        }

        if ($to === null && !is_array($from)) {
            throw new \RuntimeException('the single id was not found in response');
        }

        return $result;
    }

    /**
     * @param string $reference
     * @param string $mailbox
     * @return array
     */
    public function listMailbox($reference = '', $mailbox = '*')
    {
        $result = [];
        $list = $this->requestAndResponse('LIST', $this->escapeString($reference, $mailbox));
        if (!$list || $list === true) {
            return $result;
        }
        foreach ($list as $item) {
            if (count($item) != 4 || $item[0] !== 'LIST') {
                continue;
            }
            $result[$item[3]] = ['delim' => $item[2], 'flags' => $item[1]];
        }
        return $result;
    }

    /**
     * @param array $flags
     * @param $from
     * @param null $to
     * @param null $mode
     * @param bool $silent
     * @return array|bool|null
     */
    public function store(array $flags, $from, $to = null, $mode = null, $silent = true)
    {
        $item = 'FLAGS';
        if ($mode == '+' || $mode == '-') {
            $item = $mode . $item;
        }
        if ($silent) {
            $item .= '.SILENT';
        }
        $flags = $this->escapeList($flags);
        $set = (int)$from;
        if ($to !== null) {
            $set .= ':' . ($to == INF ? '*' : (int)$to);
        }
        $result = $this->requestAndResponse('STORE', [$set, $item, $flags], $silent);
        if ($silent) {
            return (bool)$result;
        }
        $tokens = $result;
        $result = [];
        foreach ($tokens as $token) {
            if ($token[1] !== 'FETCH' || $token[2][0] !== 'FLAGS') {
                continue;
            }
            $result[$token[0]] = $token[2][1];
        }
        return $result;
    }

    /**
     * @param $folder
     * @param $message
     * @param null $flags
     * @param null $date
     * @return array|bool|null
     */
    public function append($folder, $message, $flags = null, $date = null)
    {
        $tokens = [];
        $tokens[] = $this->escapeString($folder);
        if ($flags !== null) {
            $tokens[] = $this->escapeList($flags);
        }
        if ($date !== null) {
            $tokens[] = $this->escapeString($date);
        }
        $tokens[] = $this->escapeString($message);
        return $this->requestAndResponse('APPEND', $tokens, true);
    }

    /**
     * @param $folder
     * @param $from
     * @param null $to
     * @return array|bool|null
     */
    public function copy($folder, $from, $to = null)
    {
        $set = (int)$from;
        if ($to !== null) {
            $set .= ':' . ($to == INF ? '*' : (int)$to);
        }
        return $this->requestAndResponse('COPY', [$set, $this->escapeString($folder)], true);
    }

    /**
     * @param $folder
     * @return array|bool|null
     */
    public function create($folder)
    {
        return $this->requestAndResponse('CREATE', [$this->escapeString($folder)], true);
    }

    /**
     * @param $old
     * @param $new
     * @return array|bool|null
     */
    public function rename($old, $new)
    {
        return $this->requestAndResponse('RENAME', $this->escapeString($old, $new), true);
    }

    /**
     * @param $folder
     * @return array|bool|null
     */
    public function delete($folder)
    {
        return $this->requestAndResponse('DELETE', [$this->escapeString($folder)], true);
    }

    /**
     * @param $folder
     * @return array|bool|null
     */
    public function subscribe($folder)
    {
        return $this->requestAndResponse('SUBSCRIBE', [$this->escapeString($folder)], true);
    }

    /**
     * @return array|bool|null
     */
    public function expunge()
    {
        return $this->requestAndResponse('EXPUNGE');
    }

    /**
     * @return array|bool|null
     */
    public function noop()
    {
        return $this->requestAndResponse('NOOP');
    }

    /**
     * @param array $params
     * @return array|bool|mixed|null
     */
    public function search(array $params)
    {
        $response = $this->requestAndResponse('SEARCH', $params);
        if (!$response) {
            return $response;
        }
        foreach ($response as $ids) {
            if ($ids[0] === 'SEARCH') {
                array_shift($ids);
                return $ids;
            }
        }
        return [];
    }
}

