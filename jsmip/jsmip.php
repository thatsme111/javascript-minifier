<?php 

require 'php-closure.php';

class JSMIP {
	public $javascript;
	public $allVariableNames = [];
	public $string_literal = [];
	public $variable_array = "xyz";
	public $variable_function = "o";
	public $scope_text_start = "(function(){})();";
	public $before_length;

	public function getJavascriptWithScope(){
		$output = "(function(){";
		$output .= "var o=Function,";
		if(count($this->string_literal)>0){
			$output .= "$this->variable_array=[";
			foreach ($this->string_literal as $key => $value) {
				$output .= "$key,";		
			}	
			$output = substr($output, 0, strlen($output)-1);
			$output .= "];";
		}
		$output .= $this->javascript;
		$output .= "})();";
		return $output;
	}

	public function getJavascriptWithScopeLength(){
		$output = "(function(){";
		if(count($this->string_literal)>0){
			$output .= "var $this->variable_array=[";
			foreach ($this->string_literal as $key => $value) {
				$output .= "$key,";		
			}	
			$output = substr($output, 0, strlen($output)-1);
			$output .= "];";
		}
		$output .= $this->javascript;
		$output .= "})();";
		return strlen($output);
	}


	public function processFunction($function){
		//array which contains all the declared variables in given scope of function
		//including function arguments
		$var_array = [];

		//process arguments and push them into var_array
		$round_start = strpos($function, "(");
		$round_end = strpos($function, ")");

		//if any argument process it as a variable
		if($round_end-$round_start > 1){
			$arguments = substr($function, $round_start+1, $round_end-$round_start-1);
			$arguments = explode(",", $arguments);	
			foreach ($arguments as $key => $value) {
				array_push($var_array, $value);
			}
		}

		//process variable if exists
		$index_var = strpos($function, "var ");
		if($index_var){
			$index_semicolon = strpos($function, ";", $index_var);
			$variables = substr($function, $index_var+4, $index_semicolon-$index_var-4);
			/*
			* create variable_array which contains all variables 
			* defined locally in given function
			* explode string on comma
			* avoid exploding on commas which are arguments to function call
			* a=init(window,json)
			* and object declaration 
			* a={b:"something"}
			*/
			$iterator = 0;
			$index_start = 0;
			$count_curly = 0;
			$count_round =0;
			$length = strlen($variables);
			while($iterator<$length){
				if($variables[$iterator] == "(")
					$count_round += 1;
				if($variables[$iterator] == ")")
					$count_round -= 1;
				if($variables[$iterator] == "{")
					$count_curly += 1;
				if($variables[$iterator] == "}")
					$count_curly -= 1;

				if($variables[$iterator] == "," && $count_curly==0 && $count_round==0){
					$var = substr($variables, $index_start, $iterator-$index_start);
					// echo "$var\n";
					if(!strpos($var, " in "))
						array_push($var_array, $var);
					$index_start = ++$iterator;
				}
				$iterator++;
			}
			$var = substr($variables, $index_start, $iterator-$index_start);

			if(!strpos($var, " in "))
				array_push($var_array, $var);
			//print_r($var_array);
		}

		//process function for vaiable names
		foreach ($var_array as $key => $value) {
			$index_equalto = strpos($value, "=");
			if($index_equalto)
				$var_name = substr($value, 0, $index_equalto);
			else
				$var_name = $value;
			array_push($this->allVariableNames, $var_name);
		}
	}

	public function readAllFunctions(){
		$pattern = "/function\(\)/";
		// $pattern = "/function\([_$,a-zA-Z0-9]*\)/";
		
		preg_match_all($pattern, $this->javascript, $matches, PREG_OFFSET_CAPTURE, 0);

		$index_temp=0;
		//get complete function declaration
		foreach ($matches[0] as $match) {	
			$function_start = $match[1];
			$iterator = strpos($this->javascript, "{", $function_start);
			$count_curly = 0;
			$isdone = false;

			//loop until count of curly bracket is zero 
			while(!$isdone){
				if($this->javascript[$iterator]=="{")
					$count_curly += 1;
				if($this->javascript[$iterator]=="}")
					$count_curly -= 1;
				if($count_curly==0 || !isset($this->javascript[$iterator]))
					$isdone = true;
				$iterator++;
			}

			$function_end = $iterator;
			$function = substr($this->javascript, $function_start, $function_end-$function_start);

			//process function for variable count
			$this->processFunction($function);
		}
	}

	public function setGlobalVariables(){
		$this->allVariableNames = array_unique($this->allVariableNames);
		if(array_search($this->variable_array, $this->allVariableNames)){
			//change it to something else
		}		
		if(array_search($this->variable_function, $this->allVariableNames)){
			//change it to something else
		}		
	}


	/*
		* find string literals in source and replace it with array[index]
		* create a dictionary in which array index is word and value occurance
		* example $this->string_literal["object"]=23;
	*/
	public function processStringLiterals(){

		$pattern = "/\"[a-zA-Z0-9]*\"/";
		preg_match_all($pattern, $this->javascript, $matches, PREG_OFFSET_CAPTURE, 0);
		
		$this->string_literal = [];
		foreach ($matches[0] as $key => $value) {
			if(isset($this->string_literal[$value[0]]))
				$this->string_literal[$value[0]] += 1;
			else
				$this->string_literal[$value[0]] = 1;
		}
		//sort them for max occurance of string to be listed first
		arsort($this->string_literal);

		/*
			* criteria, x[0] < "a"
			* psudo code: len($var_array)+1+len($key) < len($value)
		*/
		$js_array_index = 0;
		$iterator = 0;
		foreach ($this->string_literal as $literal => $count) {
			//addition in array
			$new_length = strlen($literal);
			//replace with array index ex. x[0] and multiply by count
			$new_length += (strlen($this->variable_array)+1+strlen($js_array_index+"")+1)*$count;
			
			//original length in code
			$original_length = strlen($literal)*$count;

			//if new length is more than original then remove that entry
			if($new_length > $original_length){
				unset($this->string_literal[$literal]);
			}else{
				// echo "$this->variable_array[$js_array_index]=$literal\n";
				$this->javascript = preg_replace("/$literal/", "$this->variable_array[$js_array_index]", $this->javascript);
				$js_array_index++;
			}
			$iterator++;
		}
	}

	public function redefineFunctionDeclaration(){

		$pattern = "/function\(\)/";
		$isFound = preg_match($pattern, $this->javascript, $matches, PREG_OFFSET_CAPTURE)==1?true:false;

		while($isFound){
			$function_start = $matches[0][1];
			$iterator = strpos($this->javascript, "{", $function_start);
			$count_curly = 0;
			$isdone = false;

			//loop until count of curly bracket is zero 
			while(!$isdone){
				if($this->javascript[$iterator]=="{")
					$count_curly += 1;
				if($this->javascript[$iterator]=="}")
					$count_curly -= 1;
				if($count_curly==0 || !isset($this->javascript[$iterator]))
					$isdone = true;
				$iterator++;
			}
			$function_end = $iterator;
			$function = substr($this->javascript, $function_start, $function_end-$function_start);
			$function_body = substr($this->javascript, $function_start+11, $function_end-$function_start-1-11);
			$redefined_function = "$this->variable_function(\"$function_body\");";
			if(strlen($function)>strlen($redefined_function))
				$this->javascript = str_replace($function, $redefined_function, $this->javascript);

			$isFound = preg_match($pattern, $this->javascript, $matches, PREG_OFFSET_CAPTURE)==1?true:false;
		}
	}

	public function getMinifiedJavascript($filename){
		//using google's closure compiler api
		$closure_compiler = new PhpClosure();
		$this->javascript = $closure_compiler->add($filename)
			->advancedMode()
			->useClosureLibrary()
			->hideDebugInfo()
			->write();
		
		// $filename = "../lib/jquery/jquery_closure_advance.js";
		// $this->javascript = file_get_contents($filename);

		//read variable declaration inside code
		$this->readAllFunctions();

		//set global variables
		$this->setGlobalVariables();
		
		//process literal and replace them by array position
		$this->processStringLiterals();

		//redefine function declaration replace function(){content} -> z("");
		$this->redefineFunctionDeclaration();

		// echo strlen($this->getJavascriptWithScope());		
		return $this->getJavascriptWithScope();
	}

	public static function getVariableDeclaration($filename){
		$file_content = file_get_contents($filename);
		$pattern = "/var/";
		
		print_r($matches);
	}
}

header("Content-type: application/javascript");

$jsmip = new JSMIP;
$minifiedJavascript = $jsmip->getMinifiedJavascript("../lib/jquery-1.12.0.js");
file_put_contents("output.js", $minifiedJavascript);
// echo strlen($minifiedJavascript);
echo $minifiedJavascript;

?>