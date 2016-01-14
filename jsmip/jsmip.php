<?php 

require 'php-closure.php';

class JSMIP {
	public $javascript;
	public $allVariableNames = [];
	public $string_literal = [];
	public $variable_array = "x";
	public $variable_function = "z";
	public $scope_text_start = "(function(){})();";

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


	public function processFunction($function){
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
			$var_array = [];
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
			// echo "$var\n";
			if(!strpos($var, " in "))
				array_push($var_array, $var);
			//print_r($var_array);

			//process function for vaiable names
			foreach ($var_array as $key => $value) {
				$index_equalto = strpos($value, "=");
				if($index_equalto)
					$var_name = substr($value, 0, $index_equalto);
				else
					$var_name = $value;
				array_push($this->allVariableNames, $var_name);
			}
			// echo $variables."\n";
			// print_r($var_array);
			// exit;
		}
	}

	public function readAllFunctions(){

		$pattern = "/function\(\)/";
		preg_match_all($pattern, $this->javascript, $matches, PREG_OFFSET_CAPTURE, 0);

		//get function body
		foreach ($matches[0] as $match) {	
			$function_start = $match[1];
			$iterator = $function_start+10;
			$count_curly = 0;
			$isdone = false;

			//loop until count of curly bracket is zero 
			while(!$isdone){
				if($this->javascript[$iterator]=="{")
					$count_curly += 1;
				if($this->javascript[$iterator]=="}")
					$count_curly -= 1;
				if($count_curly==0)
					$isdone = true;
				$iterator++;
			}

			$function_end = $iterator;
			$function = substr($this->javascript, $function_start, $function_end-$function_start);
			$this->processFunction($function);
			// echo $function."\n\n";
		}
	}

	public function setGlobalVariables(){
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

	public function getMinifiedJavascript($filename){

		//read file content
		$this->javascript = file_get_contents($filename);

		//read variable declaration inside code
		$this->readAllFunctions();
		$this->allVariableNames = array_unique($this->allVariableNames);
		$this->setGlobalVariables();
		//print_r($this->allVariableNames);

		$this->processStringLiterals();
		echo "\n\n".$this->getJavascriptWithScope();
		exit;




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

		/*
		* list down strings and thier count
		* $file_content = "console.log(\"thatsme\")";
		*/
		$file_content = file_get_contents($filename);
		$pattern = "/\"[a-zA-Z0-9]*\"/";
		preg_match_all($pattern, $file_content, $matches, PREG_OFFSET_CAPTURE, 0);

		/*
		* create a dictionary in which array index is word and value occurance
		* example $this->string_literal["object"]=23;
		*/
		$this->string_literal = [];
		foreach ($matches[0] as $key => $value) {
			if(isset($this->string_literal[$value[0]]))
				$this->string_literal[$value[0]] += 1;
			else
				$this->string_literal[$value[0]] = 1;
		}
		//sort them		
		arsort($this->string_literal);

		//replace string with array index
		$javascript = $file_content;
		$array_index = 0;
		$array_values = [];

		foreach ($this->string_literal as $key => $value) {
			//do not process for single occurance
			if($value<2)
				continue;
			$temp_code = $javascript;
			$pattern = "/".$key."/";
			$replacement = "h[$array_index]";
			$temp_code = preg_replace($pattern, $replacement, $temp_code);			
			if(strlen($file_content) > strlen($temp_code)){
				array_push($array_values, $key);
				$javascript = $temp_code;
				$array_index++;
			}
		}
		$array_content = "";
		foreach ($array_values as $key => $value) {
			$array_content .= $value.",";
		}
		$array_content = substr($array_content, 0, strlen($array_content)-1);
		// echo $array_content;

		/*
		* create var j = Function;
		* replace function() -> j
		*/
		$javascript = $file_content; //remove this line
		$pattern = "/function\(\)/";
		$replacement = "j";
		preg_match_all($pattern, $javascript, $matches, PREG_OFFSET_CAPTURE, 0);
		
		$index_temp = 0;
		$temp_code = $javascript;
		foreach ($matches[0] as $match) {	
			$index_start = $match[1]+10;
			$count_curly = 0;
			$isdone = false;
			while(!$isdone){
				if($javascript[$index_start]=="{")
					$count_curly += 1;
				if($javascript[$index_start]=="}")
					$count_curly -= 1;
				if($count_curly==0)
					$isdone = true;
				$index_start++;
			}
			$function_original = substr($javascript, $match[1], $index_start-$match[1]);
			$function_body = substr($javascript, $match[1]+11, $index_start-$match[1]-11-1);
			$function = str_replace("\"", "'", $function_original);
			$function = "f(\"$function_body\");";
			
			$index_var = strpos($function_body, "var");
			$index_semicolon = strpos($function, ";", $index_var);
			if($index_var==true){
				$variables = substr($function_body, $index_var, $index_semicolon-$index_var-2);
				echo $variables."\n";
			}

			

			$function_start = $match[1];
			$function_end = $index_start; 
			// $javascript = substr($javascript, 0, $function_start).$function.substr($javascript, $function_end);
			//$javascript = str_replace($function_original, $function, $javascript);
			// echo $javascript;
			// break;
			if($index_temp++ == 5)
				break;
		}
		
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

// header("Content-type: text/javascript");
header("Content-type: text/html");
$jsmip = new JSMIP;
echo $jsmip->getMinifiedJavascript("../lib/jquery-1.12.0.min.js");
// echo " <pre>".JSMIP::getMinifiedJavascript("../lib/jquery-1.12.0.min.js")."</pre>";
// echo JSMIP::getMinifiedJavascript("../test/test02.js");
?>