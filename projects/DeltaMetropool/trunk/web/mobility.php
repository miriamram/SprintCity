<?phprequire_once './includes/master.inc.php';require_once 'mobilityheader.php';?><html>	<body>		<div id="wrapper">			<div id="sidebar">				<iframe src="mobilitysidebar.php" width="100%" height="100%">				  <p>Your browser does not support iframes.</p>				</iframe>			</div>			<div id="tab">				<div id="button" class="center" class="link" onClick="resize()">			</div>		</div>		<div id="flashapp">		 	<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=10,0,0,0" width="100%" height="100%" id="SprintStad" align="middle">				<param name="allowScriptAccess" value="sameDomain" />				<param name="allowFullScreen" value="true" />				<param name="movie" value="./flash/SprintStad.swf?session=<?php echo session_id(); ?>" />				<param name="menu" value="false" />				<param name="quality" value="high" />				<param name="bgcolor" value="#ffffff" />				<embed src="./flash/SprintStad.swf?session=<?php echo session_id(); ?>" menu="false" quality="high" bgcolor="#ffffff" width="100%" height="100%" name="SprintStad" align="middle" allowScriptAccess="sameDomain" allowFullScreen="true" type="application/x-shockwave-flash" pluginspage="http://www.adobe.com/go/getflashplayer" />			</object>		</div>	</body></html>