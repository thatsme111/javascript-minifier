//input
window.addEventListener("DOMContentLoaded",function(){
	console.log("thatsme");
});
//output
d=document;w=window;f=Function;
k=["addEventListener","DOMContentLoaded"];
w[k[0]](k[1],f("console.log('thatsme')"));
//result 80 to 115
/*----------------------------------------------------*/


//input
window.addEventListener("DOMContentLoaded", function(){
	document.getElementById("demo").innerHTML = "Hello Dolly.";
});
//output
w[k[0]](k[1],f("d[k[2]]('demo')[k[3]]=\"Hello Dolly.\""));		
