(function ($) {
    function stracof() {
        this.init();

        var mapOptions = {
            zoom: 3,
            center: new google.maps.LatLng(0, -180),
            mapTypeId: google.maps.MapTypeId.TERRAIN
        };

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
            if (this.polyline) {
                this.polyline.setMap(null);
            }

            var polyline_path = google.maps.geometry.encoding.decodePath(activity.map.polyline);

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
        },
        getActivity: function (activity_id) {
            var self = this;

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