/*
 * Global configuration
 */
var serverBaseURL = "http://localhost/HAC%20Server/";

/**
 * Namespace for hac functions
 */
var hac = {
		getData: function (svc, loaddata){
			var url = serverBaseURL+'data.php';
			if (typeof svc !== "undefined")
				url +="?service="+svc;
			if (typeof loaddata !== "undefined" && loaddata)
				url += "&loaddata=1";
			
			$.getJSON(url, function(data){
				for(var i=0; i<data.length; i++){
					if (data[i].state == 0)
						hac.deleteItem(data[i]);
					else
						hac.updateItem(data[i]);
				}
				
				//Sort the widgets
				hac.sort();
			});
		},
		updateItem: function(data){
			//Check for id of service
			if ($("#"+data.service).length === 0){
				//Does not exist, create
				$('<div>').attr("id", data.service).infobox({
					id: data.id,
				}).hide().appendTo($("#homepage"));
			}
			//Update the content if needed
			if ($("#"+data.service).infobox("option", "timestamp") != data.updated){
				//Set timestamp
				$("#"+data.service).infobox("option", {"timestamp": data.updated});
				
				//Call the content load service
				var item = new hac[data.service]();
				item.content($("#"+data.service), data.ui, data.data);
			}
		},
		deleteItem: function(data){
			$("#"+data.service).fadeOut("slow", function(){
					$("#"+data.service).infobox("destroy");
					this.remove();
			});
		},
		sort: function(){
			//Sort
			$('div[id$="_hacsvc"]').sortElements(function(a,b){

				return $("#"+b.id).infobox("option","priority") - $("#"+a.id).infobox("option","priority");
			});
			//Show all
			$('div[id$="_hacsvc"]').fadeIn("slow");
		}
		
};

var js = {
		/*
			A help function for replacing a set of characters with information
			strings.
			str = text string as input. E.g. "Hi, my name is ? ?"
			chr = character to be replaced. E.g. "?"
			paramsA = array of strings. E.g. ["Erik", "Persson"]
			will return "Hi, my name is Erik Persson"
		*/
		stringChrParams: function (str, chr, paramsA){
			for(var i=0;i<paramsA.length;i++){
				pos = str.indexOf(chr);
				str = str.substring(0,pos)+paramsA[i]+str.substring(pos+1);
			}
			return str;
		}
};


$(document).ready(function(){
	//Set data load interval
	var url = serverBaseURL+'getkvp.php?key=data_load';
	$.getJSON(url, function(data){
		var timer_load = setInterval(hac.getData,data*1000);
	});
	//Set data update interval
	var url = serverBaseURL+'getkvp.php?key=data_update';
	$.getJSON(url, function(data){
		if (data>0){
			var timer_update = setInterval(function(){
				$.getJSON(serverBaseURL+'cron.php');
			}, data*1000);			
		}
	});
	hac.getData();
	
});
