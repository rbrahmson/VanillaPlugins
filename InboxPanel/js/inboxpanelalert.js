jQuery(document).ready(function ($) {
	var Frequency = gdn.definition('InboxPanel.Refresh');
	var currentcount = gdn.definition('InbooxPanelLastAlerts');
	var Lastcount = currentcount;
	document.cookie = "IBPCurrentAlerts=" + currentcount;
	document.cookie = "IBPLastAlerts=" + currentcount;
	var currentcount = 0;
	var refreshcurrent = 10;							//Seconds between checking on alert counter
	//console.log("IBP0. Lastcount:" + Lastcount + " Frequency:" + Frequency);		
	if (Frequency != 0) {
		if (Frequency > 20) {
			refreshcurrent = Frequency / 2;					//No need to check counter too often
		}
		setTimeout(function() { Checkforupdate('Init Timer') }, Frequency * 1000);	//Also do the panel update (hopefully slower intervals)
	}
	setInterval(function(){ RefreshtCurrentCount('Interval') }, refreshcurrent * 1000); 		//10-second timer
	// 													// Allow update by clicking on the counter bubble
	$("#InboxAlert").click(function(){					//If user clicks on bubble
		UpdateInbox("Bubble");							//then refresh as well
	});
	$("#InboxRefresh").click(function(){				//If user clicks on envelope
		UpdateInbox("Button");							//then refresh as well
	});
	///////////////////////////////
	function Checkforupdate(Caller){							//Spin through the frequncy time, refresh panel if the counts were updated
		//console.log("IBP.CFU..." + Caller);
		Lastcount = getCookie("IBPLastAlerts");
		currentcount = getCookie("IBPCurrentAlerts");	//current count set asynchrounesly by Ajax		
		//console.log("IBP.CFU... Lastcount:" + Lastcount + " currentcount>" + currentcount + "<");
		if (currentcount == "0") {
			//console.log("IBP.CFU... ZEROcount");
			$("#InboxAlert").text("").css("display", "none");
		}
		else {			
			//console.log("IBP.CFU... notzero");
		}
		if (currentcount != Lastcount) {
			//console.log("IBP.CFU...Requesting update. currentcount:" + currentcount);
			$("#InboxAlert").css("display", "block");
			$("#InboxAlert").text(currentcount);
			UpdateInbox('C4U');
			document.cookie = "IBPLastAlerts=" + currentcount;
		}
		if (Frequency != 0) {
			setTimeout(function() { Checkforupdate('CFU.Recursive') }, Frequency * 1000);//spin again
		}	
	}
	///////////////////////////////
	function RefreshtCurrentCount(Caller) {
		var url = gdn.url('/plugin/inboxpanel/InboxPanelCount');
		Lastcount = getCookie("IBPLastAlerts");
		currentcount = Lastcount;
		//console.log("IBP.RCC..." + Caller);
		$.ajax({
            url: url,
            global: false,
            type: "GET",
            data: null,
            dataType: "text",
			error: function (xhr) {
				console.log("RCC:An error occured: " + xhr.status + " " + xhr.statusText);
			},
            success: function (Datac) {								//<span>22</span>
				currentcount = Datac.replace("<span>", "");			//22</span>
				currentcount = currentcount.replace("</span>", "");	//22
				currentcount = currentcount.trim();					//safety...
				//console.log("IBP.RCC.success " + Caller + ": CurrentCount:" + currentcount);
				document.cookie = "IBPCurrentAlerts=" + currentcount;
				Lastcount = getCookie("IBPLastAlerts");
				///
				if (currentcount == "0") {
					//console.log("IBP.RCC.success... ZEROcount");
					$("#InboxAlert").text("").css("display", "none");
				}
				else {
					$("#InboxAlert").css("display", "block");
				}
				if (currentcount != Lastcount) {
					$("#InboxAlert").text(currentcount).css("display", "block");			//Update bubble
				}					
			}
        });
	}
	///////////////////////////////
	function UpdateInbox(Caller){
		//console.log("IBP.UI..."+Caller);
		var url = gdn.url('/plugin/inboxpanel/InboxPanelUpdate');
		$.ajax({
            url: url,
            global: false,
            type: "GET",
            data: null,
            dataType: "html",
			error: function (xhr) {
				console.log("An error occured: " + xhr.status + " " + xhr.statusText);
			},
            success: function (Data) {
				//console.log("IBP.UI... Frequency:" + Frequency);
				/*<div class="Box InboxPanel" id="Boxinbox">*/
				$("#Boxinbox").html(Data);
            }
        });
	}
	///////////////////////////////
	function getCookie(cname) {
		var name = cname + "=";
		var ca = document.cookie.split(';');
		for(var i=0; i<ca.length; i++) {
			var c = ca[i];
			while (c.charAt(0)==' ') c = c.substring(1);
			if (c.indexOf(name) == 0) return c.substring(name.length,c.length);
		}
		return "";
	}
	
});

	 