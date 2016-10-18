<?php 

/**
 * Simple regex parser as described in http://heap.ch/blog/2015/12/25/sjmform/
 */


class SJMFormRegex {
	private $literals = array();
	
	public function __construct() {
		$this->literals['true'] = true;
		$this->literals['false'] = false;
		$this->literals['null'] = null;
	}
	public function parse($fml) {
		$fml = $this->stringExtract($fml);
		$ast = $this->parseAtom($fml);
		return $ast;
	}
	
	// form markup language parse functions
	private function parseAtom($fml) {
		$pattern = '/(\w+)\s+(\w+)\s*\((.*?)\)\s*(\{(.*?)\}\s*)?;/s';
		$offset = 0;
		$tree = array();
		while (preg_match($pattern, $fml, $m, PREG_OFFSET_CAPTURE, $offset)===1) {
			$node = new stdClass();
			$node->type = $m[1][0];
			$node->name = $m[2][0];
			$node->args = $this->arglist($m[3][0]);
			if (isset($m[5])) $node->childNodes = $this->parseAtom($m[5][0]);
			$offset = $m[0][1] + strlen($m[0][0]);
			$tree []= $node;
		}
		return $tree;
	}
	private function arglist($argstring) {
		// replace placeholders with actual value
		$args = array();
		foreach (explode(',', $argstring) as $arg) {
			$literal = trim($arg);
			if (array_key_exists($literal, $this->literals)) {
				$args []= $this->literals[$literal];
			} elseif (is_numeric($literal)) {
				$args []= $literal + 0; // int or float
			} else {
				die ("invalid literal: $literal");
			}
		}
		return $args;
	}
	private function stringExtract($fml) {
		// remove comments (lines starting with #)
		$fml = preg_replace('/^\s*#.*$/m', '', $fml);
		// replace string literals with place holders
		$pattern = '/"(.*?)(?<!\\\\)"/';
		return preg_replace_callback($pattern, array($this, 'stringReplace'), $fml);
	}
	private function stringReplace($matches) {
		static $n = 1;
		// remove escape chars
		$string = str_replace('\\"', '"', $matches[1]);
		$key = "str$n";
		$n += 1;
		$this->literals[$key] = $string;
		return $key;
	}
}


