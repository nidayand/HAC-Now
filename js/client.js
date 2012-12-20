/*
 * Global configuration
 */
var serverBaseURL = "http://10.0.1.23/HAC%20Server/";
var serviceFunctionCallURL = serverBaseURL+'serviceFunctionCall.php';

/**
 * Namespace for hac functions
 */
var hac = {
		/** Polls the server for updated information that is to 
		 * be displayed in the UI.
		 * @param svc Optional parameter. Only poll data for a specific service
		 * @param loaddata Optional parameter. If used, svc is mandatory. Before data is retrieved, the call will update the data for the service (data_load)
		 */
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
		/** Inserts or updates an item into the UI. Is called from getData.
		 * An update is only made if the timestamp differs
		 * @param data Object to be inserted/updated
		 */	
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
		/** Deletes an object from the UI
		 * @param data Object to be removed
		 */		
		deleteItem: function(data){
			$("#"+data.service).fadeOut("slow", function(){
					$("#"+data.service).infobox("destroy");
					this.remove();
			});
		},
		/** Sorts the objects in the UI based on the priority setting
		 * of the infobox widget
		 */			
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

/*
 * Methods that are to start upon complete loading of the page
 * It will poll for data as well as set the schedulers
 */
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
	
	/*
	 * Sliding pages function
	 */
	$("#homepage").on("click", function(event){
		var percent = ($(document).width()-event.pageX)/$(document).width()*100;
		if (percent<10)
			$.mobile.changePage($("#application"), {transition: "slide"}, false, true);
	});
	$("#application").on("click", function(event){
		var percent = event.pageX/$(document).width()*100;
		if (percent<10)
			$.mobile.changePage($("#homepage"), {transition: "slide", reverse: true}, false, true);
	});

	/*
	 * Return to homepage if the user is idle
	 */
	idleTimer = null;
	idleWait = 60000;
    $('*').bind('mousemove keydown scroll', function () {
    	clearTimeout(idleTimer);
       idleTimer = setTimeout(function () {

    	   //Goto homepage
    	   $.mobile.changePage($("#homepage"));
    	   
       	}, idleWait);
    });
});
