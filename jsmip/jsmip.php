<?php 

require 'php-closure.php';

class JSMIP {
	public $javascript;
	public $allVariableNames = [];
	public $string_literal = [];
	public $variable_array = "x";
	public $variable_function = "z";
	public $scope_text_start = "(function(){})();";
	public $before_length;

	public function getJavascriptWithScope(){
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
			$this->processFunction($function);

			//redefine function with f("content");
			$this->redefineFunctionDeclaration($function);
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

	public function redefineFunctionDeclaration($function){
		$original_length = strlen($function);
		$function_body = substr($function, 11, strlen($function)-1-11);
		$function_body = str_replace("\"", "\\\"", $function_body);
		$after_length = strlen("$this->variable_function(\"$function_body\");");
		if($after_length < $original_length)
			$this->javascript=str_replace($function, "$this->variable_function(\"$function_body\");", $this->javascript);
		// else
		// 	return
		// $function = ;
		// $after_length = strlen($function);
		// echo $original_length."\n";
		// echo $after_length."\n";
		// echo $function."\n\n";
		
	}

	public function getMinifiedJavascript($filename){

		//read file content
		$this->javascript = file_get_contents($filename);
		// echo strlen($this->javascript)."\n";

		//read variable declaration inside code
		$this->readAllFunctions();

		//set global variables
		$this->setGlobalVariables();
		
		//process literal and replace them by array position
		$this->processStringLiterals();

		//process function and redefine it as Function("")
		$this->redefineFunctionDeclaration();

		// echo strlen($this->getJavascriptWithScope());		
		return $this->getJavascriptWithScope();




		//read file in $file array
		$file = file($filename);

		//remove extra white spaces and concatenate into single line
		//using google's closure compiler api
		/*$closure_compiler = new PhpClosure();
		$file_content = $closure_compiler->add($filename)
			->advancedMode()
			->useClosureLibrary()
			->hideDebugInfo()
			->write();*/

		// /*
		// * list down strings and thier count
		// * $file_content = "console.log(\"thatsme\")";
		// */
		// $file_content = file_get_contents($filename);
		// $pattern = "/\"[a-zA-Z0-9]*\"/";
		// preg_match_all($pattern, $file_content, $matches, PREG_OFFSET_CAPTURE, 0);

		// /*
		// * create a dictionary in which array index is word and value occurance
		// * example $this->string_literal["object"]=23;
		// */
		// $this->string_literal = [];
		// foreach ($matches[0] as $key => $value) {
		// 	if(isset($this->string_literal[$value[0]]))
		// 		$this->string_literal[$value[0]] += 1;
		// 	else
		// 		$this->string_literal[$value[0]] = 1;
		// }
		// //sort them		
		// arsort($this->string_literal);

		// //replace string with array index
		// $javascript = $file_content;
		// $array_index = 0;
		// $array_values = [];

		// foreach ($this->string_literal as $key => $value) {
		// 	//do not process for single occurance
		// 	if($value<2)
		// 		continue;
		// 	$temp_code = $javascript;
		// 	$pattern = "/".$key."/";
		// 	$replacement = "h[$array_index]";
		// 	$temp_code = preg_replace($pattern, $replacement, $temp_code);			
		// 	if(strlen($file_content) > strlen($temp_code)){
		// 		array_push($array_values, $key);
		// 		$javascript = $temp_code;
		// 		$array_index++;
		// 	}
		// }
		// $array_content = "";
		// foreach ($array_values as $key => $value) {
		// 	$array_content .= $value.",";
		// }
		// $array_content = substr($array_content, 0, strlen($array_content)-1);
		// // echo $array_content;

		/*
		* create var j = Function;
		* replace function() -> j
		*/
		
		
		// echo substr($file_content, $index_start+10, 20);
		
		// echo $index_start;

		//enclose javascript in scope
		$javascript = "(function(){var h=[$array_content],f=Function;$javascript})();";

		// return $temp_code;
		$percent = strlen($javascript)*100/strlen($file_content);
		return strlen($file_content)." & ".strlen($javascript)." & ".$percent;

		// return $file_content;
		// return print_r($this->string_literal, true);
	}

	public static function getVariableDeclaration($filename){
		$file_content = file_get_contents($filename);
		$pattern = "/var/";
		
		print_r($matches);
	}
}

header("Content-type: text/javascript");
// header("Content-type: text/html");
$jsmip = new JSMIP;
$minifiedJavascript = $jsmip->getMinifiedJavascript("../lib/jquery-1.12.0.min.js");
echo $minifiedJavascript;
// echo " <pre>".JSMIP::getMinifiedJavascript("../lib/jquery-1.12.0.min.js")."</pre>";
// echo JSMIP::getMinifiedJavascript("../test/test02.js");
?>