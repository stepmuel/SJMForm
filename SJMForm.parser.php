<?php 

/**
 * Simple fml parser based on http://lisperator.net/pltut/parser/
 */

class SJMFormParser {
	public function parse($fml) {
		$is = new FMLInputStream($fml);
		$ts = new FMLTokenStream($is);
		$p = new FMLParser($ts);
		$ast = $p->parse();
		return $ast;
	}
}

class FMLInputStream {
	public function __construct($input) {
		$this->input = $input;
		$this->len = strlen($input);
		$this->pos = 0;
		$this->line = 1;
		$this->col = 1;
	}
	public function next() {
		$ch = substr($this->input, $this->pos, 1);
		$this->pos += 1;
		if ($ch == "\n") {
			$this->line += 1;
			$this->col = 1;
		} else {
			$this->col += 1;
		}
		return $ch;
	}
	public function peek() {
		return substr($this->input, $this->pos, 1);
	}
	public function eof() {
		return $this->pos >= $this->len;
	}
	public function pos() {
		return [$this->pos, $this->line, $this->col];
	}
}

class FMLTokenStream {
	private $current = null;
	public function __construct($input) {
		$this->input = $input;
		$this->pos = $input->pos();
	}
	public function peek() {
		if (!$this->current) {
			$this->current = $this->readNext();
		}
		return $this->current;
	}
	public function next() {
		$tok = $this->peek();
		$this->current = null;
		return $tok;
	}
	public function eof() {
		return $this->peek() === null;
	}
	public function croak($msg) {
		list($pos, $line, $col) = $this->pos;
		$error = $msg . " @ (" . $line . ":" . $col . ")";
		throw new Exception($error);
	}
	private function readNext() {
		$in = $this->input;
		$this->readWhile('ctype_space');
		if ($in->eof()) return null;
		$ch = $in->peek();
		if ($ch == "#") {
			$this->readWhile(function($ch) {return $ch != "\n";});
			$in->next();
			return $this->readNext();
		}
		$this->pos = $this->input->pos();
		if ($ch == '"') return $this->readString('"');
		if (ctype_punct($ch)) return  (object)['type' => 'punct', 'value' => $in->next()];
		if (ctype_alnum($ch)) return  (object)['type' => 'id', 'value' => $this->readWhile([$this, 'isIdChar'])];
		// return (object)['type' => 'debug', 'value' => $in->next()];
		$this->croak("Unexpected character ".json_encode($ch));
	}
	private function readWhile($predicate) {
		$in = $this->input;
		$str = '';
		while (!$in->eof() && call_user_func($predicate, $in->peek())) {
			$str .= $in->next();
		}
		return $str;
	}
	private function readString($end) {
		$escaped = false;
		$str = "";
		$this->input->next();
		while (!$this->input->eof()) {
			$ch = $this->input->next();
			if ($escaped) {
				$str .= stripcslashes("\\$ch");
				$escaped = false;
			} elseif ($ch == "\\") {
				$escaped = true;
			} elseif ($ch == $end) {
				break;
			} else {
				$str .= $ch;
			}
		}
		return (object)['type' => 'str', 'value' => $str];
	}
	private function isIdChar($ch) {
		// also handles numbers
		return ctype_alnum($ch) || $ch == '.';
	}
}

class FMLParser {
	public $literals = [
		"true" => true,
		"false" => false,
		"null" => null,
	];
	public function __construct($input) {
		$this->input = $input;
	}
	public function parse() {
		$a = [];
		while (!$this->input->eof()) {
			if ($this->testPeek('punct', ';')) {
				$this->next(); // ';' are optional at the top level
			} else {
				$a []= $this->parseDef();
			}
		}
		return $a;
	}
	public function parseDef() {
		$type = $this->next('id');
		$name = $this->next('id');
		$args = $this->delimited('(', ')', ',', [$this, 'parseLiteral']);
		$def = (object)['type' => $type->value, 'name' => $name->value, 'args' => $args];
		if ($this->testPeek('punct', '{')) {
			$def->childNodes = $this->delimited('{', '}', ';', [$this, 'parseDef']);
		}
		return $def;
	}
	private function parseLiteral() {
		$tok = $this->input->next();
		if ($tok->type == 'str') return $tok->value;
		if ($tok->type == 'id') {
			$val = $tok->value;
			if (array_key_exists($val, $this->literals)) {
				return $this->literals[$val];
			}
			if (is_numeric($val)) {
				return $val + 0; // int or float
			}
		}
		$this->input->croak("Invalid literal ".json_encode($tok->value));
	}
	private function delimited($start, $stop, $separator, $parser) {
		$a = [];
		$first = true;
		$this->next('punct', $start);
		while (!$this->input->eof()) {
			if ($this->testPeek('punct', $stop)) break;
			if ($first) {
				$first = false;
			} else {
				$this->next('punct', $separator);
			}
			if ($this->testPeek('punct', $stop)) break;
			$a []= call_user_func($parser);
		}
		$this->next('punct', $stop);
		return $a;
	}
	private function testPeek($type, $value = null) {
		$tok = $this->input->peek();
		if (!$tok) return false;
		if ($tok->type !== $type) return false;
		if ($value !== null && $tok->value !== $value) return false;
		return true;
	}
	private function next($type = null, $value = null) {
		$tok = $this->input->next();
		if ($value !== null) {
			if (!is_array($value)) $value = [$value];
			if (!in_array($tok->value, $value)) {
				$values = implode(' or ', array_map('json_encode', $value));
				$this->input->croak("Unexpected value ".json_encode($tok->value)." when expecting $values");
			}
		}
		if ($type !== null) {
			if (!is_array($type)) $type = [$type];
			if (!in_array($tok->type, $type)) {
				$types = implode(' or ', array_map('json_encode', $type));
				$this->input->croak("Unexpected type ".json_encode($tok->type)." when expecting $types");
			}
		}
		return $tok;
	}
}



