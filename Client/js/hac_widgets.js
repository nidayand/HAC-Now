 $(function() {
        $.widget( "custom.infobox", {
            // default options
            options: {
				headline: "title",
				subheadline: null,
				contentpadding: true,
				content: "content",
				links: null,
				buttons: null,
				priority: 0,
				timestamp: null,
				id:null,
				
				service: null,
				refreshInterval:null
            },
 
            // the constructor
            _create: function() {
                this.element
                    // add a class for theming
                    .addClass( "infobox" );
				this.headline = $( "<p>", {
					text: this.options.headline,
					"class" : "title"
				})
				.appendTo(this.element);		
 
                this._refresh();
            },
 
            // called when created, and later when changing options
            _refresh: function() {
            	
            	//Fix headline
            	this.headline.remove();
            	if (typeof this.options.headline == "object"){
            		var headline="";
            		for(var i=0;i<this.options.headline.length; i++){
            			if (i==0)
            				headline +="<p class='title'>"+this.options.headline[i]+"</p>";
            			else
            				headline +="<p class='title2'>"+this.options.headline[i]+"</p>";
            			if (i<(this.options.headline.length-1))
            				headline +="<p class='subtitle'></p>";
            		}
            		this.headline= $(headline);
            	} else {
    				this.headline = $( "<p class='title'>"+this.options.headline+"</p>");
            	}
            	this.headline.prependTo(this.element);
            	
				//Check if subtitle should be shown
				if (this.options.subheadline != null){
					if(!this.subheadline)
						this.subheadline = $( "<p>", {
							text: this.options.subheadline,
							"class" : "subtitle"
						}).insertAfter(this.headline[0]);
					else
						this.subheadline.html(this.options.subheadline);
				} else 
					if (this.options.contentpadding){
						if(!this.subheadline)
							this.subheadline = $( "<p>", {
								text: "",
								"class" : "subtitle"
							}).insertAfter(this.headline[0]);				
					} else
						this.subheadline.remove();
					
				//Check if text is to be shown
				if (this.options.content != null){
					if (!this.content){
						this.content = $( "<p>", {
							text: this.options.content,
							"class" : "boxtext"
						});
						if (this.subheadline)
							this.content.insertAfter(this.subheadline);
						else 
							this.content.insertAfter(this.headline);
					} else {
						this.content.html(this.options.content);
					}
					this.content.trigger("create");
				}
				
				//Check if buttons are to be shown
				if (this.options.buttons!=null && typeof this.buttons == "undefined"){
					//Add horizontal group first
					this.buttongroup = $("<div>", {
								"align":"center",
								"data-role":"controlgroup",
								"data-type":"horizontal"
							})
							.appendTo(this.element);
					this.buttons = [];
					for(var i=0; i<this.options.buttons.length; i++){
						this.buttons[i] = $("<a href='index.html' data-role='button'>"+this.options.buttons[i].text+"</a>")
							.appendTo(this.buttongroup).button();
						//Add function
						if (this.options.buttons[i].exec){
							var clickFunction = this.options.buttons[i].exec;
							this.buttons[i].click(function(event){
								clickFunction();
								event.preventDefault();
							});
						}						
					}
					this.buttongroup.controlgroup("refresh");
					
				}
							
				this.autoUpdate();
 
            },
            
            autoUpdate : function(){
            	if (this.options.refreshInterval != null && this.options.service != null){
            		var service = this.options.service;
            		var refreshInterval = this.options.refreshInterval;
            		
            		//Delete previous key
            		if (this.intervalKey){
            			clearInterval(this.intervalKey);
            		}
            		//Create a new one
                	this.intervalKey = setInterval(function(){
    					hac.getData(service, true);
                	}, refreshInterval);
            	}
            },
 
            // events bound via _on are removed automatically
            // revert other modifications here
            _destroy: function() {
            	
            	//Stop autoupdate if available
            	if (this.intervalKey)
            		clearInterval(this.intervalKey);
            },
 
            // _setOptions is called with a hash of all options that are changing
            // always refresh when changing options
            _setOptions: function() {
                // _super and _superApply handle keeping the right this-context
                this._superApply( arguments );
                this._refresh();
            },
 
            // _setOption is called for each individual option that is changing
            _setOption: function( key, value ) {
                this._super( key, value );
            }
        });	
    });