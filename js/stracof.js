(function ($) {
    function stracof() {
        this.init();

        var mapOptions = {
            zoom: 3,
            center: new google.maps.LatLng(0, -180),
            mapTypeId: google.maps.MapTypeId.TERRAIN
        };

        this.markers = [];
        this.map = new google.maps.Map(document.getElementById('map'), mapOptions);
    }

    stracof.prototype = {
        constructor: stracof,
        init: function () {
            var self = this;

            $("#activities").on('change', function () {
                self.getActivity(this.value);
            });
        },
        displayActivity: function (activity) {
            // Clear the polyline
            if (this.polyline) {
                this.polyline.setMap(null);
            }

            // Clear the markers
            for (var i = 0; i < this.markers.length; i++) {
                this.markers[i].setMap(null);
            }

            this.markers = [];

            var polyline_path = google.maps.geometry.encoding.decodePath(activity.polyline);

            this.polyline = new google.maps.Polyline({
                path: polyline_path,
                geodesic: true,
                strokeColor: '#FF0000',
                strokeOpacity: 1.0,
                strokeWeight: 2
            });

            // To fit the line, we need to create a bounding box using all of the line's points.
            var bounds = new google.maps.LatLngBounds();

            for (var i = 0; i < polyline_path.length; i++){
                bounds.extend(polyline_path[i]);
            }

            this.map.fitBounds(bounds);
            this.polyline.setMap(this.map);

            var pointsHtml = "";

            for (var i = 0; i < activity.businesses.length; i++){
                if (activity.businesses[i].stracof_points) {
                    var points = activity.businesses[i].stracof_points;
                    var points_class = (points > 0) ? "positive" : "negative";

                    pointsHtml += "<div><h4>" + activity.businesses[i].name + "</h4>Distance: " + activity.businesses[i].stracof_distance + "m<br />Rating: " + activity.businesses[i].rating + "<br />Points: <span class='" + points_class + "'>" + activity.businesses[i].stracof_points + "</span></div>";
                    this.mapActivity(activity.businesses[i]);
                }
            }

            var total_class = (activity.total_points > 0) ? "positive" : "negative";
            var totalHtml = "<h2 class='" + total_class + "'>" + activity.total_points + " points!</h2>";

            $(".points_container").html(pointsHtml);
            $(".total_container").html(totalHtml);
        },
        mapActivity: function (business) {
            var marker = new google.maps.Marker({
                position: {lat: business.location.coordinate.latitude, lng: business.location.coordinate.longitude},
                map: this.map
            });

            this.markers.push(marker);
        },
        getActivity: function (activity_id) {
            var self = this;

            $(".points_container").html("Loading...");
            $(".total_container").html("");

            $.getJSON("route.php", {id: activity_id})
                .done(function (response) {
                    if (response.error) {
                        alert(response.message);
                    } else {
                        self.displayActivity(response);
                    }
                });
        }
    };

    $(function () {
        window.stracof = new stracof();
    });
})(jQuery);