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

		//list down strings and thier count
		// $file_content = "console.log(\"thatsme\")";
		$file_content = file_get_contents($filename);
		$pattern = "/\"[a-zA-Z0-9]*\"/";
		preg_match($pattern, $file_content, $matches, PREG_OFFSET_CAPTURE);

		// return $file_content;
		return print_r($matches, true);
	}
}

header("Content-type: text/javascript");
echo JSMIP::getMinifiedJavascript("../lib/jquery-1.12.0.min.js");
// echo JSMIP::getMinifiedJavascript("../test/test02.js");
?>