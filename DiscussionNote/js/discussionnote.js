jQuery(document).ready(function ($) {
	// This JS is not enable unless few lines are uncommented in the main plugin source.
	// See the plugin comments above the "Base_Render_Before" function for explanation.
	var refreshcurrent = 20;							//Seconds between checking on note changes
	//console.log("DNP0. refreshcurrent:" + refreshcurrent);		
	setInterval(function(){ RefreshtCurrentCount('Interval') }, refreshcurrent * 1000); 		// timer
	///////////////////////////////
	function RefreshtCurrentCount(Caller) {
		Lastid = getCookie("DNDID");
		var url = gdn.url('/plugin/discussionnote/DiscussionNoteRefresh&U=') + Lastid;
		//console.log("DNP.RCC.1.." + Lastid);
		//console.log("DNP.RCC.2.." + url);
		if (Lastid == "") return;
		if (Lastid == "0") return;
		$.ajax({
            url: url,
            global: false,
            type: "GET",
            data: null,
            dataType: "html",
			error: function (xhr) {
				console.log("RCC:An error occured: " + xhr.status + " " + xhr.statusText);
			},
            success: function (Datac) {								//<span>22</span>
				Newnote = Datac.replace("<span>", "");			//22</span>
				Newnote = Newnote.replace("</span>", "");	//22
				Newnote = Newnote.trim();					//safety...
				//console.log("DNP.RCC.success " + Caller + ": Newnote:" + Newnote);
				Lastid = getCookie("DNDID");
				Htmlid = "#Postit" + Lastid;
				//document.cookie = "DNDID=0";
				if (Newnote == "") {
					//console.log("DNP.RCC.success... Empty Note");
					$(Htmlid).text("").removeClass("Noteinmeta on").addClass("Hijack Noteinmeta");
				}
				else {
					$(Htmlid).html(Newnote).removeClass("Noteinmeta").addClass("Hijack Noteinmeta on");
				}
				//document.cookie = "DNDID=; expires=Thu, 01 Jan 1970 00:00:00 UTC";
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
	///////////////////////
});

	 