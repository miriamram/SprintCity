<?phprequire_once './includes/master.inc.php';?><html>    <head>        <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">        <meta name="keywords" content="">        <meta name="description" content="">        <title>OV</title>        <link href="./images/mobility/ov-logo.ico" rel="shortcut icon"/>        <link rel="stylesheet" type="text/css" href="./style/reset-fonts-grids.css">        <link rel="stylesheet" type="text/css" href="./style/mobility.css">        <link rel="stylesheet" type="text/css" href="./style/ovapp.css">        <script type="text/javascript" src="script/jquery/jquery.min.js"></script>        <script type="text/javascript" src="script/jquery/jquery-ui.min.js"></script>        <script type="text/javascript" src="script/mobility/mobility.js"></script>    </head>    <body>        <div id="wrapper">            <div id="main">                <div id="tabs">                    <ul>                        <li>                            <a class="tab" href="#tabs-1">OV Scherm</a>                        </li>                        <li>                            <a class="tab" href="#tabs-2">Speelscherm</a>                        </li>                    </ul>                    <div id="tabs-1">                        <div id="ovapp">                            <?php require_once 'pages/ovapp.php'; ?>                        </div>                    </div>                    <div id="tabs-2">                        <div id="flashapp">                            <object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=10,0,0,0" width="100%" height="100%" id="SprintStad" align="middle">                                <param name="allowScriptAccess" value="sameDomain" />                                <param name="allowFullScreen" value="true" />                                <param name="movie" value="./flash/SprintStad.swf?session=<?php echo session_id(); ?>" />                                <param name="menu" value="false" />                                <param name="quality" value="high" />                                <param name="bgcolor" value="#ffffff" />                                <embed src="./flash/SprintStad.swf?session=<?php echo session_id(); ?>" menu="false" quality="high" bgcolor="#ffffff" width="100%" height="650px" name="SprintStad" align="left" allowScriptAccess="sameDomain" allowFullScreen="true" type="application/x-shockwave-flash" pluginspage="http://www.adobe.com/go/getflashplayer" />                            </object>                            <div style="clear: both"></div>                        </div>                    </div>                </div>            </div>            <div id="sidebar">                <h1>Openbaar Vervoer</h1>                <img src="images/mobility/ov-logo.png"/>                <div class="sidebarbox">                    <div class="title">                        Ambitie: <span>Kwantiteit</span>                    </div>                    <h1 class="bigpink">2012</h1>                </div>                <div class="sidebarbox">                    <div class="title">                        Motivatie                    </div>                    <textarea id="motivatie">Dit is mijn motivatie voor deze zet.</textarea>                </div>                <button id="doorvoeren">                    Doorvoeren                </button>            </div>        </div>    </body></html>