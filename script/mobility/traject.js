function Traject(train) {
    this.train = train;

    this.traject = document.createElement('div');
    this.trajectTitle = document.createElement('div');
    this.trajectLijn = document.createElement('div');

    this.traject.setAttribute('class', 'traject');
    this.trajectTitle.setAttribute('class', 'traject-title');
    this.trajectLijn.setAttribute('class', 'traject-lijn');

    this.init();
}

Traject.prototype.init = function() {
    this.draw();

    $('#trajecten-container').append(this.traject);

}

Traject.prototype.draw = function() {
    this.writeTrainLabel();
    this.drawRouteBackground();
    this.writeIUstuff();
}

Traject.prototype.writeTrainLabel = function() {
    var trainType = document.createElement('h1');
    trainType.innerHTML = Traject.parseType(this.train.name);

    var trainRoute = document.createElement('h2');
    var parsedRoute = Traject.parseRoute(this.train.route);
    trainRoute.innerHTML = "" + parsedRoute;

    this.trajectTitle.appendChild(trainType);
    this.trajectTitle.appendChild(trainRoute);

    $(this.trajectTitle).attr('trainID', this.train.id);

    this.traject.appendChild(this.trajectTitle);
}

Traject.parseType = function(type) {
    switch (type) {
        case 'IC':
            type = "IC";
            break;
        case 'Spr':
            type = "Sprinter";
            break;
        default:
            break;
    }

    
    return type;
}

Traject.parseRoute = function(route) {
    return route.replace(/- /g, "- <br/>");
}

Traject.prototype.drawRouteBackground = function() {

    this.drawGrayBoxes(this.trajectLijn);

    this.drawYellowLine(this.trajectLijn);
    this.drawCircles(this.trajectLijn);
    this.traject.appendChild(this.trajectLijn);
}

Traject.prototype.drawGrayBoxes = function(trajectLijn) {
    for(var i = 0; i < stations.length; i++) {

        /* draw the gray boxes */
        var grayBox = document.createElement('div');
        grayBox.setAttribute('class', 'gray-box');
        var offset = getDistanceBetweenStations() * i;
        $(grayBox).css({
            left : offset
        });
        trajectLijn.appendChild(grayBox);
    }
}

Traject.prototype.drawCircles = function(trajectLijn) {
    for(var i = 0; i < stations.length; i++) {
        var trainStop = document.createElement('div');
        $(trainStop).addClass('train-stop');

        var offset = getDistanceBetweenStations() * i - 10;
        $(trainStop).css({
            left : offset
        });

        $(trainStop).attr('trainID', this.train.id);
        $(trainStop).attr('stopindex', i);

        if(this.train.stationStops[i] == 0 || this.train.stationStops[i] == null) {
            $(trainStop).addClass('invisible');
        } else {
            $(trainStop).append(this.train.stationStops[i]);
        }
        trajectLijn.appendChild(trainStop);
    }
}

Traject.prototype.drawYellowLine = function(trajectLijn) {
    /* draw the yellow line */
    var yellowLine = document.createElement('div');
    yellowLine.setAttribute('class', 'yellow-line');
    var offsetYellowLineBegin = getDistanceBetweenStations();
    var yellowLineWidth = offsetYellowLineBegin * stations.length - offsetYellowLineBegin;

    $(yellowLine).css({
        width : yellowLineWidth
    });
    trajectLijn.appendChild(yellowLine);
}

Traject.prototype.writeIUstuff = function() {

    var textBox = document.createElement('div');
    $(textBox).addClass('textBoxIU');

    
    
    $(textBox).append(this.train.maxIU);
    
    var colorClass = this.decideColorClass();   
    $(textBox).append('<br /><span class="'+ colorClass +'">' + this.train.currentAvgIU + '</span>');
    
    $(textBox).append('<br />' + this.train.minIU);
    this.traject.appendChild(textBox);
}

Traject.prototype.decideColorClass = function() {
    var colorClass;

    if (this.train.currentAvgIU < this.train.maxIU && this.train.currentAvgIU > this.train.minIU)
    {
        colorClass = "green-text";
    }
    else if (this.train.currentAvgIU > this.train.maxIU) {
        colorClass = "red-text";
    }
    else if (this.train.currentAvgIU < this.train.minIU) {
        colorClass = "blue-text"
    }
    else {
        colorClass = '';
    }
    
    return colorClass;
}

function getDistanceBetweenStations() {
    var canvasSize = 786;
    //magic number, i know, but its canvas size minus indent
    var xDistance = Math.round(canvasSize / stations.length);
    return xDistance;
}