<?php

namespace Hazaar;

/**
 * B-Tree key/value database file access class
 *
 * This class provides a high performance key/value storage mechanism that stores data to file. A B-tree is
 * a self-balancing tree data structure that keeps data sorted and allows searches, sequential access,
 * insertions, and deletions in logarithmic time.
 *
 * @since 2.3.17
 */
class Btree {

    /**
     * Size of header
     */
    const SIZEOF_HEADER = 6;

    /**
     * Header that has to be at end of every file
     */
    const HEADER = "\xffbtree";

    /**
     * Maximum number of keys per node (do not even think about to change it)
     */
    const NODE_SLOTS = 16;

    /**
     * Size of integer (pack type N)
     */
    const SIZEOF_INT = 4;

    /**
     * This is key-value node
     */
    const KVNODE = 'kv';

    /**
     * This is key-pointer node
     */
    const KPNODE = 'kp';

    /**
     * Size of node chache
     */
    const NODECHACHE_SIZE = 64;

    /**
     * The file resource
     *
     * @var \Hazaar\File
     */
    private $file;

    /**
     * @var array Node cache
     */
    private $nodecache = array();

    /**
     * Use static method open() to get instance
     */
    public function __construct($file, $ready_only = false) {

        if(!$this->open($file, $ready_only))
            throw new \Hazaar\Exception('Unable to open file: ' . $file);

    }

    /**
     * Open the file for access
     *
     * @param mixed $file Either a string file name, or a Hazaar\File object.
     *
     * @throws \Exception
     *
     * @return boolean
     */
    public function open($file = null, $read_only = false) {

        if($file === null){

            if(!$this->file)
                throw new \Hazaar\Exception('No file specified!');

            if($this->file->isOpen())
                return true;

        }else{

            if($this->file)
                $this->file->close();

            if(!$file instanceof \Hazaar\File)
                $file = new \Hazaar\File($file);

            $this->file = $file;

        }

        if($file->backend() !== 'local')
            throw new \Hazaar\Exception('The BTree file class currently only supports the local file manager backend!', 400);

        //Check if the file is too big.  The file size will be negative if PHP doesn't support the file.
        if($this->file->exists() && $this->file->size() < 0)
            throw new \Hazaar\Exception('File is too large.  On 32-bit PHP only files up to 2GB in size are supported.');

        $this->file->open((($read_only === true) ? 'rb' : 'a+b'));

        // write default node if neccessary
        if ($this->file->seek(0, SEEK_END) === -1) {

            $this->file->close();

            return false;

        }

        if ($this->file->tell() === 0) {

            if (!$this->file->lock(LOCK_EX)) {

                $this->file->close();

                return false;

            }

            $root = self::serialize(self::KVNODE, array());

            $to_write = pack('N', strlen($root)) . $root;

            if ($this->file->write($to_write, strlen($to_write)) !== strlen($to_write) || !self::header($this->file, 0) || !$this->file->lock(LOCK_UN)) {

                $this->file->truncate(0);

                $this->file->close();

                return false;

            }

        }

        return true;

    }

    /**
     * The the B-Tree source file.
     *
     * @return boolean
     */
    public function reset_btree_file(){

        $this->file->close();

        $this->file->unlink();

        return $this->open($this->file);

    }

    /**
     * Get value by key
     *
     * @param string $key The key to return data for
     *
     * @return mixed
     */
    public function get($key) {

        $lookup = $this->lookup($key);

        $leaf = end($lookup);

        if ($leaf !== null && isset($leaf[$key])) return $leaf[$key];

        return null;

    }

    /**
     * Get all values where startkey <= key < endkey
     *
     * To get all data, use:
     *
     * ```php
     * $values = $btree->range("\x00", "\xff");
     * ```
     *
     * @param string $startkey
     * @param string $endkey
     *
     * @return array
     */
    public function range($startkey, $endkey) {

        $start = $this->lookup($startkey);

        $end = $this->lookup($endkey);

        if (end($start) === null || end($end) === null) return null;

        $upnodes = array();

        while (!empty($start)) {

            $nodes = array();

            foreach (array_merge(array_shift($start), $upnodes, array_shift($end)) as $k => $v) {

                if (!(strcmp($k, $startkey) >= 0 && strcmp($k, $endkey) < 0))
                    continue;

                if (empty($start)){

                    $nodes[$k] = $v;

                } else {

                    list($node_type, $node) = $this->node($v);

                    if ($node_type === null || $node === null)
                        return null;

                    $nodes = array_merge($nodes, $node);

                }

            }

            $upnodes = $nodes;

        }

        return $upnodes;

    }

    /**
     * Set value under given key
     *
     * @param string $key The key to store the value under.
     * @param mixed $value The value to store. A NULL value deletes given key.
     *
     * @return boolean
     */
    public function set($key, $value) {

        // Obtain an exclusive file lock
        if (!$this->file->lock(LOCK_EX))
            return false;

        if ($this->file->seek(0, SEEK_END) === -1) {

            $this->file->lock(LOCK_UN);

            return false;

        }

        if (($pos = $this->file->tell()) === false) {

            $this->file->lock(LOCK_UN);

            return false;

        }

        $cursor = $pos;

        // key lookup
        $lookup = $this->lookup($key);

        $node = array_pop($lookup);

        if ($node === null)
            return false;

        // change value
        $index = current(array_keys($node));

        $node_type = self::KVNODE;

        $new_index = null;

        if ($value === null)
            unset($node[$key]);
        else
            $node[$key] = $value;

        // traverse tree up
        do {

            if (count($node) <= intval(self::NODE_SLOTS / 2) && !empty($lookup)) {

                $upnode = (array) array_pop($lookup);

                $new_index = current(array_keys($upnode));

                $sibling = $prev = array(null, null);

                foreach ($upnode as $k => $v) {

                    if ($index === $k)
                        $sibling = $prev; // left sibling
                    else if ($index === $prev[0])
                        $sibling = array($k, $v); // right sibling

                    if ($sibling[0] !== null) {

                        list($sibling_type, $sibling_node) = $this->node($sibling[1]);

                        if ($sibling_type === null || $sibling_node === null) {

                            $this->file->ftruncate($pos);

                            $this->file->lock(LOCK_UN);

                            return false;

                        }

                        $node = array_merge($node, $sibling_node);

                        unset($upnode[$sibling[0]]);

                    }

                    $prev = array($k, $v);

                    $sibling = array(null, null);

                }

                array_push($lookup, $upnode);

            }

            ksort($node, SORT_STRING);

            if (count($node) <= self::NODE_SLOTS)
                $nodes = array($node);
            else
                $nodes = array_chunk($node, ceil(count($node) / ceil(count($node) / self::NODE_SLOTS)), true);

            $upnode = array_merge(array(), (array) array_pop($lookup));

            if ($new_index === null)
                $new_index = current(array_keys($upnode));

            unset($upnode[$index]);

            foreach ($nodes as $_) {

                $serialized = self::serialize($node_type, $_);

                $to_write = pack('N', strlen($serialized)) . $serialized;

                if ($this->file->write($to_write, strlen($to_write)) !== strlen($to_write)) {

                    $this->file->truncate($pos);

                    $this->file->lock(LOCK_UN);

                    return false;

                }

                $upnode[current(array_keys($_))] = $cursor;

                $cursor += strlen($to_write);

            }

            $node_type = self::KPNODE;

            $index = $new_index;

            $new_index = null;

            if (count($upnode) <= 1) {

                $root = current(array_values($upnode));

                break;

            } else {

                array_push($lookup, $upnode);

            }

        } while (($node = array_pop($lookup)));

        //Write root header to the current database file
        if (!($this->file->flush() && self::header($this->file, $root) && $this->file->flush())) {

            $this->file->truncate($pos);

            $this->file->lock(LOCK_UN);

            return false;

        }

        $this->file->lock(LOCK_UN);

        return true;

    }

    public function remove($key){

        return $this->set($key, null);

    }

    /**
     * Look up a key
     *
     * @param string $key The key to lookup
     * @param string $node_type
     * @param array $node
     *
     * @return array traversed nodes
     */
    private function lookup($key, $node_type = null, $node = null) {

        if(!$this->file->lock(LOCK_SH))
            return false;

        if ($node_type === null || $node === null)
            list($node_type, $node) = $this->root();

        if ($node_type === null || $node === null)
            return array(null);

        $ret = array();

        do {

            array_push($ret, $node);

            if ($node_type === self::KVNODE){

                $node = null;

            } else {

                $keys = array_keys($node);

                $l = 0;

                $r = count($keys);

                while ($l < $r) {

                    $i = $l + intval(($r - $l) / 2);

                    if (strcmp($keys[$i], $key) < 0)
                        $l = $i + 1;
                    else
                        $r = $i;

                }

                $l = max(0, $l + ($l >= count($keys) ? -1 : (strcmp($keys[$l], $key) <= 0 ? 0 : -1)));

                list($node_type, $node) = $this->node($node[$keys[$l]]);

                if ($node_type === null || $node === null) return array(null);

            }

        } while ($node !== null);

        return $ret;

    }

    /**
     * Check if a given key exists in the database
     *
     * @param mixed $key The key to check
     *
     * @return boolean
     */
    public function has($key){

        return ($this->get($key) !== null);

    }

    /**
     * Get a list of all available keys in the database
     *
     * Warning: Unlike a search this will hit almost every part of the database file and can be a bit slow.
     *
     * @return array An array of available keys
     */
    public function keys(){

        $keys = array();

        if(is_array($leaves = $this->leaves())){

            foreach($leaves as $p){

                list(,$leaf) = $this->node($p);

                $keys = array_merge($keys, array_keys($leaf));

            }

        }

        return $keys;

    }

    /**
     * Get positions of all leaves
     *
     * @return array pointers to leaves; null on failure
     */
    public function leaves() {

        if (($root = $this->roothunt()) === null)
            return null;

        return $this->leafhunt($root);

    }

    /**
     * Find positions of all leaves
     *
     * @param int $p pointer to node
     *
     * @return array pairs of (leaf, pointer); null on failure
     */
    private function leafhunt($p) {

        list($node_type, $node) = $this->node($p);

        if ($node_type === null || $node === null)
            return null;

        $nodes = array(array($node_type, $node, $p));

        $ret = array();

        do {

            $new_nodes = array();

            foreach ($nodes as $_) {

                list($node_type, $node, $p) = $_;

                if ($node_type === self::KVNODE) {

                    $ret[current(array_keys($node))] = $p;

                } else {

                    foreach ($node as $i) {

                        list($child_type, $child) = $this->node($i);

                        if ($child_type === null || $child === null)
                            return null;

                        if ($child_type === self::KVNODE)
                            $ret[current(array_keys($child))] = $i;
                        else
                            $new_nodes[] = array($child_type, $child, $i);

                    }

                }

            }

            $nodes = $new_nodes;

        } while (count($nodes) > 1);

        return $ret;

    }

    /**
     * Remove old nodes from BTree file
     *
     * @return bool true if everything went well; false otherwise
     */
    public function compact() {

        $compact_file = new \Hazaar\File(uniqid($this->file . '~', true));

        if (!($compact_file->open('a+b')))
            return false;

        if (!(($compact_file->seek(0, SEEK_END) !== -1
            && $root = $this->copyto($compact_file)) !== null
            && self::header($compact_file, $root) !== false
            && $compact_file->flush()
            && $compact_file->close()
            && $this->file->close()
            && $this->file->unlink()
            && @$compact_file->rename((string)$this->file))) { /* will not work under windows, sorry */

            $compact_file->close();

            @$compact_file->unlink();

            dump(error_get_last());

            return false;

        }

        $this->nodecache = array();

        $this->file->close();

        $this->file = $compact_file;

        $this->file->open('a+b');

        return true;

    }

    /**
     * Copy node from opened file to another
     *
     * @param \Hazaar\File The file to copy everything to
     * @param string $node_type
     * @param array $node
     *
     * @return int new pointer to copied node;
     */
    private function copyto(\Hazaar\File $to, $node_type = null, $node = null) {

        if ($node_type === null || $node === null)
            list($node_type, $node) = $this->root();

        if ($node_type === null || $node === null)
            return null;

        if ($node_type === self::KPNODE){

            foreach ($node as $k => $v)
                $node[$k] = array($v);

        }

        $stack = array(array($node_type, $node));

        do {

            list($node_type, $node) = array_pop($stack);

            if ($node_type === self::KPNODE) {

                $pushed = false;

                foreach ($node as $i) {

                    if (is_array($i)) {

                        list($child_type, $child) = $this->node($i[0]);

                        if ($child_type === null || $child === null)
                            return null;

                        if ($child_type === self::KPNODE){

                            foreach ($child as $k => $v)
                                $child[$k] = array($v);

                        }

                        array_push($stack, array($node_type, $node));

                        array_push($stack, array($child_type, $child));

                        $pushed = true;

                        break;

                    }

                }

                if ($pushed) continue;

            }

            if (!empty($stack))
                list($upnode_type, $upnode) = array_pop($stack);
            else
                list($upnode_type, $upnode) = array(null, array());

            $serialized = self::serialize($node_type, $node);

            $to_write = pack('N', strlen($serialized)) . $serialized;

            if (($p = $to->tell()) === false)
                return null;

            if ($to->write($to_write, strlen($to_write)) !== strlen($to_write))
                return null;

            $upnode[current(array_keys($node))] = $p;

            if (!(empty($stack) && $upnode_type === null))
                array_push($stack, array($upnode_type, $upnode));

        } while (!empty($stack));

        return $p;

    }

    /**
     * Get root node
     *
     * @return array 0 => node type, 1 => node; array(null, null) on failure
     */
    private function root() {

        if (($p = $this->roothunt()) === null)
            return array(null, null);

        return $this->node($p);

    }

    /**
     * Try to get position of root
     *
     * @return int pointer to root; null on failure
     */
    private function roothunt() {

        // try EOF
        if ($this->file->seek(-(self::SIZEOF_HEADER + self::SIZEOF_INT), SEEK_END) === -1)
            return null;

        if (strlen($data = $this->file->read(self::SIZEOF_INT + self::SIZEOF_HEADER)) !== self::SIZEOF_INT + self::SIZEOF_HEADER)
            return null;

        $root = substr($data, 0, self::SIZEOF_INT);

        // header-hunting
        if (substr($data, self::SIZEOF_INT) !== self::HEADER) {

            $root = null;

            if (($size = $this->file->tell()) === false)
                return null;

            for ($i = -1; ($off = $i * 128) + $size > 128; --$i) {

                if ($this->file->seek($off, SEEK_END) === -1)
                    return null;

                $data = $this->file->read(256);

                if (($pos = strrpos($data, self::HEADER)) !== false) {

                    if ($pos === 0)
                        continue;

                    $root = substr($data, $pos - 4, 4);

                    break;

                }

            }

            if ($root === null)
                return null;

        }

        // unpack root pointer
        list(,$p) = unpack('N', $root);

        return $p;

    }

    /**
     * Get node
     *
     * @param int $p Pointer to node (offset in file)
     *
     * @retrun array 0 => node type, 1 => node; array(null, null) on failure
     */
    private function node($p) {

        if (!isset($this->nodecache[$p])) {

            while (count($this->nodecache) + 1 > self::NODECHACHE_SIZE)
                array_pop($this->nodecache);

            if ($this->file->seek($p, SEEK_SET) === -1)
                return array(null, null);

            if (strlen($data = $this->file->read(self::SIZEOF_INT)) !== self::SIZEOF_INT)
                return array(null, null);

            list(,$n) = unpack('N', $data);

            if (strlen($node = $this->file->read($n)) !== $n)
                return array(null, null);

            $this->nodecache[$p] = self::unserialize($node);

        }

        return $this->nodecache[$p];

    }

    /**
     * Serialize node
     *
     * @param string $type node type
     * @param array $node
     *
     * @return string
     */
    private static function serialize($type, array $node) {

        return $type . serialize($node);

    }

    /**
     * Unserialize node
     *
     * @param string $str serialized node
     *
     * @return array
     */
    private static function unserialize($str) {

        return array(substr($str, 0, 2), unserialize(substr($str, 2)));

    }

    /**
     * Writes header to file
     *
     * @param \Hazaar\File $file The file to write the header ro
     * @param int $root root position
     *
     * @return bool
     */
    private static function header(\Hazaar\File $file, $root) {

        $to_write = pack('N', $root) . self::HEADER;

        return ($file->write($to_write, strlen($to_write)) === strlen($to_write));

    }

    public function dropCache(){
        
        $this->nodecache = array();

    }

}