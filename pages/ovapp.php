<script type="text/javascript" src="script/mobility/paperjs/paper.js"></script>
<script type="text/javascript" src="script/mobility/station.js"></script>
<script type="text/javascript" src="script/mobility/train.js"></script>
<script type="text/javascript" src="script/mobility/traject.js"></script>
<script type="text/javascript" src="script/service/load.js"></script>
<script type="text/javascript" src="script/service/send.js"></script>
<script type="text/paperscript" src="script/mobility/paperjs/ovgraph.js" canvas="graphCanvas"></script>
<script type="text/javascript">
    /* ========================================================= */
    /* Initialization */
    var READ_ONLY = <?php echo isset($ovapp_readonly) && $ovapp_readonly == true ? 'true' : 'false'; ?>
        
    var locked = false;

    //global variable within the ovapp scope
    var stations = new Array();
    var trains = new Array();

</script>
<script type="text/javascript">
    /* Put the trains on the track */
    function startApp() {
        for(var id in trains) {
            new Traject(trains[id]);
        }

        if(!READ_ONLY) {
            $('.train-stop').click(function() {
                if(!locked){
                    handleTrainStopClick(this);
                }
            });

            $('.traject-title').click(function() {
                handleTrainClick(this);
            });
        }

    }

    function handleTrainStopClick(trainStop) {

        changeTrainStop(trainStop, true);
        var trainId = $(trainStop).attr('trainid');
        sendTrainToPHPService(trainId, refresh);

    }
	
    function sendTrainToPHPService(trainId, callback) {            
        var stationStops = trains[trainId].stationStops;
        Send.sendTrain(trainId, stationStops, callback);
    }

    function updateTrainStationStops(trainId, stopIndex, stopNum) {
        trains[trainId].setStationStop(stopIndex, stopNum);
        // console.log('train[' + trainId +'].stationStops['+stopIndex+'] ='+ stopNum + ' | CHECK: ' + trains[trainId].stationStops[stopIndex]);
    }

    function changeTrainStop(trainStop, allowZero) {
        var stopNum = $(trainStop).text();
        if(stopNum < 8) {
            stopNum++;
            $(trainStop).text(stopNum);
        } else {
            stopNum = (allowZero == true ? 0 : 1);
            $(trainStop).text(stopNum);
        }
        
         if(stopNum == 0) {
            $(trainStop).removeClass('invisible');
            $(trainStop).text("");
        }

        var trainId = $(trainStop).attr('trainid');
        var stopIndex = $(trainStop).attr('stopindex');

        updateTrainStationStops(trainId, stopIndex, stopNum);

    }

    function refresh() {
        console.log("refresh");
        Load.refreshAll();
    }

    function handleTrainClick(trainTitle) {
        /* Go inside container div and look for all divs with class train-stop
         * and fire handleTrainStopClick for each one.
         */

        $(trainTitle).parent().children('.traject-lijn').children('.train-stop').each(function() {
            if($(this).text() != "") {
                $(this).animate({
                    color : 'black'
                }, 20, "swing", function() {
                    $(this).animate({
                        color : '#f0098d'
                    }, 20, "swing");
                });
                changeTrainStop(this, false);
            }
        });
        var trainId = $(trainTitle).attr('trainid');
        sendTrainToPHPService(trainId, refresh);
    }
</script>
<!-- HTML STUFF -->
<div id="grafiek">
    <canvas id="graphCanvas" width=886 height=240></canvas>
</div>
<div id="station-names" width=886>
</div>
<div id="trajecten-container"></div>