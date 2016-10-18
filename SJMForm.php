<?php 

class SJMForm {
	private $tree = array();
	private $literals = array();
	
	public function __construct($fml) {
		$this->literals['true'] = true;
		$this->literals['false'] = false;
		$this->literals['null'] = null;
		$fml = $this->stringExtract($fml);
		$this->tree = $this->parse($fml);
		// echo json_encode($this->tree, JSON_PRETTY_PRINT);
		// echo "<pre>".print_r($this->literals,true)."</pre>\n"; 
		// echo "<pre>".print_r($this->tree,true)."</pre>\n"; 
	}
	
	// form markup language parse functions
	private function parse($fml) {
		$pattern = '/(\w+)\s+(\w+)\s*\((.*?)\)\s*(\{(.*?)\}\s*)?;/s';
		$offset = 0;
		$tree = array();
		while (preg_match($pattern, $fml, $m, PREG_OFFSET_CAPTURE, $offset)===1) {
			$node = new stdClass();
			$node->type = $m[1][0];
			$node->name = $m[2][0];
			$node->args = $this->arglist($m[3][0]);
			if (isset($m[5])) $node->childNodes = $this->parse($m[5][0]);
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
	
	// compile form
	public function __toString() {
		$out = '';
		foreach ($this->tree as $node) {
			$method = 'produce_'.$node->type;
			if(!method_exists($this, $method)) continue;
			$out .= $this->$method($node);
		}
		return $out;
	}
	
	// preprocess data received from the form
	public function preprocess($data) {
		$out = array();
		foreach ($this->tree as $node) {
			$name = $node->name;
			if ($node->type=='checkbox') {
				// checkboxes don't send anything for unchecked boxes
				foreach ($node->childNodes as $child) {
					$key = "{$name}_{$child->name}";
					$out[$key] = isset($data[$key]);
				}
			} else {
				$value = isset($data[$name]) ? $data[$name] : '';
				$out[$name] = $value;
			}
		}
		return $out;
	}
	
	// set form values
	public function set($data) {
		foreach ($this->tree as &$node) {
			$type = $node->type;
			$name = $node->name;
			// special handling for checkboxes
			if ($type=='checkbox') {
				foreach ($node->childNodes as &$child) {
					$key = $name.'_'.$child->name;
					if (!isset($data[$key])) continue;
					$child->args[1] = $data[$key];
				}
				continue;
			}
			// do we have a value for the current node?
			if (!isset($data[$name])) continue;
			$value = $data[$name];
			// handle radio buttons and select
			if ($type=='radio' || $type=='select') {
				foreach ($node->childNodes as $child) {
					$child->args[1] = $value == $child->name;
				}
				continue;
			}
			// hidden fields have no display name argument
			if ($type=='hidden') {
				$node->args[0] = $value;
				continue;
			}
			// all other types have their value as second argument
			$node->args[1] = $value;
		}
	}
	
	// html generators
	private function produce_hidden($e) {
		if (!isset($e->args[0])) $e->args[0] = '';
		return "<input type=\"hidden\" name=\"$e->name\" value=\"".htmlentities($e->args[0])."\" id=\"$e->name\">\n";
	}
	private function produce_text($e) {
		if (!isset($e->args[1])) $e->args[1] = '';
		$out = "<dt><label for=\"$e->name\">".htmlentities($e->args[0])."</label></dt>\n";
		$out .= "<dd><input type=\"text\" name=\"$e->name\" value=\"".htmlentities($e->args[1])."\"  size=\"30\" id=\"$e->name\"></dd>\n";
		return $out;
	}
	private function produce_password($e) {
		if (!isset($e->args[1])) $e->args[1] = '';
		$out = "<dt><label for=\"$e->name\">".htmlentities($e->args[0])."</label></dt>\n";
		$out .= "<dd><input type=\"password\" name=\"$e->name\" value=\"".htmlentities($e->args[1])."\"  size=\"30\" id=\"$e->name\"></dd>\n";
		return $out;
	}
	private function produce_textarea($e) {
		if (!isset($e->args[1])) $e->args[1] = '';
		$out = "<dt><label for=\"$e->name\">".htmlentities($e->args[0])."</label></dt>\n";
		$out .= "<dd><textarea name=\"$e->name\" rows=\"6\" cols=\"40\" id=\"$e->name\">".htmlentities($e->args[1])."</textarea></dd>\n";
		return $out;
	}
	private function produce_radio($e) {
		$out = "<dt><label>".htmlentities($e->args[0])."</label></dt>\n";
		foreach ($e->childNodes as $c) {
			if (!isset($c->args[1])) $c->args[1] = false;
			$checked = ($c->args[1]) ? ' checked' : '';
			$id = $e->name.'_'.$c->name;
			$out .= "<dd>\n";
			$out .= "\t<input type=\"radio\" name=\"$e->name\" value=\"$c->name\" id=\"$id\"$checked>\n";
			$out .= "\t<label for=\"$id\">".htmlentities($c->args[0])."</label>\n";
			$out .= "</dd>\n";
		}
		return $out;
	}
	private function produce_checkbox($e) {
		$out = "<dt><label>".htmlentities($e->args[0])."</label></dt>";
		foreach ($e->childNodes as $c) {
			if (!isset($c->args[1])) $c->args[1] = false;
			$checked = ($c->args[1]) ? ' checked' : '';
			$id = $e->name.'_'.$c->name;
			$out .= "<dd>\n";
			$out .= "\t<input type=\"checkbox\" name=\"$id\" value=\"1\" id=\"$id\"$checked>\n";
			$out .= "\t<label for=\"$id\">".htmlentities($c->args[0])."</label>\n";
			$out .= "</dd>\n";
		}
		return $out;
	}
	private function produce_select($e) {
		$out = "<dt><label for=\"$e->name\">".htmlentities($e->args[0])."</label></dt>\n";
		$out .= "<dd>\n";
		$out .= "\t<select size=\"1\" name=\"$e->name\" id=\"$e->name\">\n";
		foreach ($e->childNodes as $c) {
			if (!isset($c->args[1])) $c->args[1] = false;
			$selected = ($c->args[1]) ? ' selected=\"selected\"' : '';
			$out .= "\t<option value=\"$c->name\"$selected>".htmlentities($c->args[0])."</option>\n";
		}
		$out .= "</select>\n";
		$out .= "</dd>\n";
		return $out;
	}
}


