var OPENED = 1;
var CLOSED = 0;
var mode = OPENED;

var sideBarWidthOpened = "280px";
var sideBarWidthClosed = "0px";
var flashAppMarginOpened = "300px";
var flashAppMarginClosed = "20px";

var buttonOpened = "url('./images/mobility/button_opened.png') no-repeat";
var buttonClosed = "url('./images/mobility/button_closed.png') no-repeat";

// TODO: If there is time, build in a transition
function resize()
{
	var sidebar = document.getElementById("sidebar");
	var flashApp = document.getElementById("flashapp");
	var button = document.getElementById("button");
		
	if(isOpen())
	{
		sidebar.style.width = sideBarWidthClosed;
		sidebar.style.visibility = "hidden";
		
		flashApp.style.marginLeft = flashAppMarginClosed;
		button.style.background = buttonClosed;
		
		mode = CLOSED;
	}
	else
	{
		sidebar.style.width = sideBarWidthOpened;
		sidebar.style.visibility = "visible";
		
		flashApp.style.marginLeft = flashAppMarginOpened
		button.style.background = buttonOpened;
		
		mode = OPENED;
	}
}

function isOpen()
{
	return mode == OPENED;
}