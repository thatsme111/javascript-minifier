<?php 

require 'php-closure.php';

class JSMIP {
	public static function getMinifiedJavascript($filename){
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
		* example $dictionary["object"]=23;
		*/
		$dictionary = [];
		foreach ($matches[0] as $key => $value) {
			if(isset($dictionary[$value[0]]))
				$dictionary[$value[0]] += 1;
			else
				$dictionary[$value[0]] = 1;
		}
		//sort them		
		arsort($dictionary);

		//replace string with array index
		$javascript = $file_content;
		$array_index = 0;
		$array_values = [];

		foreach ($dictionary as $key => $value) {
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
			if($index_var==true){}
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
		// return print_r($dictionary, true);
	}

	public static function getVariableDeclaration($filename){
		$file_content = file_get_contents($filename);
		$pattern = "/var/";
		
		print_r($matches);
	}
}

// header("Content-type: text/javascript");
header("Content-type: text/html");
echo " <pre>".JSMIP::getMinifiedJavascript("../lib/jquery-1.12.0.min.js")."</pre>";
// echo JSMIP::getMinifiedJavascript("../test/test02.js");
?>