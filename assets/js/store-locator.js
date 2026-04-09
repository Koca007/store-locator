(function () {
    "use strict";

    var locatorControllers = {};

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function buildDetailsHtml(store, labels) {
        var html = "<h3 class=\"sl-details-title\">" + escapeHtml(store.name || "") + "</h3>";
        var cityZipLine = [store.zip, store.city].filter(Boolean).join(" ");
        var addressLine = String(store.address || "");

        if (cityZipLine) {
            html += "<p class=\"sl-details-address-main\">" + escapeHtml(cityZipLine) + "</p>";
        }

        if (addressLine) {
            html += "<p class=\"sl-details-address-sub\">" + escapeHtml(addressLine) + "</p>";
        }

        if (store.phone) {
            html += "<p class=\"sl-details-contact sl-details-contact--phone\"><a href=\"tel:" + escapeHtml(store.phone) + "\">" + escapeHtml((labels && labels.phone) ? labels.phone : "Phone") + ": " + escapeHtml(store.phone) + "</a></p>";
        }

        if (store.email) {
            html += "<p class=\"sl-details-contact sl-details-contact--email\"><a href=\"mailto:" + escapeHtml(store.email) + "\">" + escapeHtml((labels && labels.email) ? labels.email : "Email") + "</a></p>";
        }

        if (store.website) {
            html += "<p class=\"sl-details-contact\"><a href=\"" + escapeHtml(store.website) + "\" target=\"_blank\" rel=\"noopener noreferrer\">" + escapeHtml(store.website) + "</a></p>";
        }

        var weekDayOrder = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"];
        var weekDayLabels = labels && labels.week_days ? labels.week_days : {};
        var openingHours = (store && store.opening_hours && typeof store.opening_hours === "object") ? store.opening_hours : {};

        html += "<div class=\"sl-details-hours\">";
        html += "<ul>";

        weekDayOrder.forEach(function (dayKey) {
            var dayLabel = weekDayLabels[dayKey] ? weekDayLabels[dayKey] : dayKey;
            var dayData = openingHours[dayKey] && typeof openingHours[dayKey] === "object" ? openingHours[dayKey] : {};
            var from = dayData.from ? String(dayData.from) : "";
            var to = dayData.to ? String(dayData.to) : "";
            var value = (from && to) ? (from + " - " + to) : "-";

            html += "<li><span class=\"sl-hours-day\">" + escapeHtml(dayLabel) + ":</span><span class=\"sl-hours-value\">" + escapeHtml(value) + "</span></li>";
        });

        html += "</ul>";
        html += "</div>";

        if (store.product_ranges) {
            html += "<p class=\"sl-details-product-ranges\">" + escapeHtml(store.product_ranges) + "</p>";
        }

        return html;
    }

    function openDetailsPanel(controller, store) {
        if (!controller || !controller.detailsNode || !store) {
            return;
        }

        controller.detailsContentNode.innerHTML = buildDetailsHtml(store, controller.labels || {});
        if (controller.detailsGpsLinkNode) {
            var destination = [store.address, store.zip, store.city].filter(Boolean).join(", ");
            var mapsUrl = "https://www.google.com/maps/dir/?api=1&destination=" + encodeURIComponent(destination);
            controller.detailsGpsLinkNode.setAttribute("href", mapsUrl);
        }
        controller.root.classList.add("sl-store-locator--details-open");
        window.setTimeout(function () {
            controller.map.invalidateSize();
        }, 430);
    }

    function closeDetailsPanel(controller) {
        if (!controller || !controller.detailsNode) {
            return;
        }

        controller.root.classList.remove("sl-store-locator--details-open");
        if (controller.detailsContentNode) {
            controller.detailsContentNode.innerHTML = "";
        }
        if (controller.detailsGpsLinkNode) {
            controller.detailsGpsLinkNode.setAttribute("href", "#");
        }
        window.setTimeout(function () {
            controller.map.invalidateSize();
        }, 430);
    }

    function createMarkerIcon(markerImage, markerColor) {
        if (markerImage) {
            return window.L.divIcon({
                className: "sl-custom-marker-icon",
                html: "<span class=\"sl-custom-marker sl-custom-marker--image\" style=\"background-image:url('" + escapeHtml(markerImage) + "');\"></span>",
                iconSize: [36, 36],
                iconAnchor: [18, 18],
                popupAnchor: [0, -18]
            });
        }

        return window.L.divIcon({
            className: "sl-custom-marker-icon",
            html: "<span class=\"sl-custom-marker sl-custom-marker--color\" style=\"background-color:" + escapeHtml(markerColor) + ";\"></span>",
            iconSize: [18, 18],
            iconAnchor: [9, 9],
            popupAnchor: [0, -9]
        });
    }

    function clearMapEmptyMessage(mapNode) {
        var node = mapNode.querySelector(".sl-map-empty");
        if (node) {
            node.parentNode.removeChild(node);
        }
    }

    function showMapEmptyMessage(mapNode, text) {
        clearMapEmptyMessage(mapNode);
        mapNode.insertAdjacentHTML("beforeend", "<div class=\"sl-map-empty\">" + escapeHtml(text || "") + "</div>");
    }

    function parseSearchQuery(query) {
        var raw = String(query || "").toLowerCase().trim();
        var tokens = raw.split(/[\s,;]+/).filter(Boolean);
        var zipParts = [];
        var cityParts = [];

        tokens.forEach(function (token) {
            if (/^\d{3,}$/.test(token)) {
                zipParts.push(token);
                return;
            }

            cityParts.push(token);
        });

        return {
            raw: raw,
            cityQuery: cityParts.join(" "),
            zipQuery: zipParts.join(" ")
        };
    }

    function matchesFilter(store, cityQuery, zipQuery, rawQuery) {
        var city = String(store.city || "").toLowerCase();
        var zip = String(store.zip || "").toLowerCase();
        var combined = (city + " " + zip).trim();

        if (!rawQuery) {
            return true;
        }

        if (combined.indexOf(rawQuery) !== -1) {
            return true;
        }

        var cityOk = cityQuery === "" || city.indexOf(cityQuery) !== -1;
        var zipOk = zipQuery === "" || zip.indexOf(zipQuery) !== -1;

        return cityOk && zipOk;
    }

    function levenshteinDistance(a, b) {
        var m = a.length;
        var n = b.length;
        var i;
        var j;
        var dp = [];

        if (m === 0) {
            return n;
        }
        if (n === 0) {
            return m;
        }

        for (i = 0; i <= m; i += 1) {
            dp[i] = [];
            dp[i][0] = i;
        }

        for (j = 0; j <= n; j += 1) {
            dp[0][j] = j;
        }

        for (i = 1; i <= m; i += 1) {
            for (j = 1; j <= n; j += 1) {
                var cost = a.charAt(i - 1) === b.charAt(j - 1) ? 0 : 1;
                dp[i][j] = Math.min(
                    dp[i - 1][j] + 1,
                    dp[i][j - 1] + 1,
                    dp[i - 1][j - 1] + cost
                );
            }
        }

        return dp[m][n];
    }

    function normalizedTextDistance(query, value) {
        var q = String(query || "").toLowerCase().trim();
        var v = String(value || "").toLowerCase().trim();

        if (!q) {
            return 0;
        }
        if (!v) {
            return 1;
        }
        if (v.indexOf(q) !== -1) {
            return 0.05;
        }

        var dist = levenshteinDistance(q, v);
        var maxLen = Math.max(q.length, v.length);
        return maxLen > 0 ? dist / maxLen : 1;
    }

    function zipDistance(queryZip, storeZip) {
        var q = String(queryZip || "").trim();
        var s = String(storeZip || "").trim();

        if (!q) {
            return 0;
        }
        if (!s) {
            return 1;
        }
        if (s.indexOf(q) !== -1) {
            return 0.01;
        }

        if (/^\d+$/.test(q) && /^\d+$/.test(s)) {
            var qNum = parseInt(q, 10);
            var sNum = parseInt(s, 10);
            return Math.min(Math.abs(qNum - sNum) / 1000, 1);
        }

        return normalizedTextDistance(q, s);
    }

    function selectNearestStore(stores, cityQuery, zipQuery) {
        var candidates = stores.filter(function (store) {
            var lat = parseFloat(store.latitude);
            var lng = parseFloat(store.longitude);
            return Number.isFinite(lat) && Number.isFinite(lng);
        });

        if (!candidates.length) {
            return null;
        }

        var best = null;
        var bestScore = Number.POSITIVE_INFINITY;

        candidates.forEach(function (store) {
            var cityScore = normalizedTextDistance(cityQuery, store.city || "");
            var zipScore = zipDistance(zipQuery, store.zip || "");
            var totalScore = (cityScore * 0.55) + (zipScore * 0.45);

            if (totalScore < bestScore) {
                bestScore = totalScore;
                best = store;
            }
        });

        return best;
    }

    function findMarkerByStoreId(markers, storeId) {
        var id = String(storeId);
        var found = null;

        markers.forEach(function (markerObj) {
            if (String(markerObj.store.id) === id) {
                found = markerObj;
            }
        });

        return found;
    }

    function openMarkerWithFocus(controller, markerObj, latLng, zoom) {
        controller.map.setView(latLng, zoom);

        if (
            controller.useClustering &&
            controller.clusterGroup &&
            typeof controller.clusterGroup.zoomToShowLayer === "function"
        ) {
            controller.clusterGroup.zoomToShowLayer(markerObj.marker, function () {});
        }
    }

    function activateMarker(controller, markerObj, store, latLng, zoom) {
        if (!controller || !markerObj) {
            return;
        }

        setActiveMarker(controller, markerObj);
        if (store) {
            openDetailsPanel(controller, store);
        }

        openMarkerWithFocus(controller, markerObj, latLng, zoom);
    }

    function setActiveMarker(controller, markerObj) {
        if (!controller || !markerObj) {
            return;
        }

        if (
            controller.activeMarkerObj &&
            controller.activeMarkerObj.marker &&
            controller.activeMarkerObj.marker !== markerObj.marker
        ) {
            controller.activeMarkerObj.marker.setIcon(controller.defaultIcon);
        }

        if (controller.activeIcon) {
            markerObj.marker.setIcon(controller.activeIcon);
            controller.activeMarkerObj = markerObj;
        }
    }

    function clearActiveMarker(controller) {
        if (!controller || !controller.activeMarkerObj) {
            return;
        }

        controller.activeMarkerObj.marker.setIcon(controller.defaultIcon);
        controller.activeMarkerObj = null;
        closeDetailsPanel(controller);
    }

    function resetMapView(controller) {
        if (!controller || !controller.map) {
            return;
        }

        if (Array.isArray(controller.allBounds) && controller.allBounds.length > 0) {
            controller.map.fitBounds(controller.allBounds, { padding: [24, 24] });
            return;
        }

        if (
            Array.isArray(controller.defaultCenter) &&
            controller.defaultCenter.length === 2
        ) {
            controller.map.setView(controller.defaultCenter, controller.defaultZoom);
        }
    }

    function rememberMapView(controller) {
        if (!controller || !controller.map) {
            return;
        }

        var center = controller.map.getCenter();
        var zoom = controller.map.getZoom();

        controller.preActivationView = {
            center: [center.lat, center.lng],
            zoom: zoom
        };
    }

    function restoreRememberedMapView(controller) {
        if (!controller || !controller.map || !controller.preActivationView) {
            resetMapView(controller);
            return;
        }

        controller.map.setView(
            controller.preActivationView.center,
            controller.preActivationView.zoom
        );
        controller.preActivationView = null;
    }

    function applyFilter(controller, query) {
        var parsedQuery = parseSearchQuery(query);
        var cityQuery = parsedQuery.cityQuery;
        var zipQuery = parsedQuery.zipQuery;
        var rawQuery = parsedQuery.raw;
        var filtered = controller.stores.filter(function (store) {
            return matchesFilter(store, cityQuery, zipQuery, rawQuery);
        });
        var bounds = [];
        var matchedMarkers = [];

        controller.markers.forEach(function (markerObj) {
            if (filtered.indexOf(markerObj.store) === -1) {
                return;
            }

            var lat = parseFloat(markerObj.store.latitude);
            var lng = parseFloat(markerObj.store.longitude);
            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                bounds.push([lat, lng]);
                matchedMarkers.push(markerObj);
            }
        });

        clearMapEmptyMessage(controller.mapNode);

        if (filtered.length > 0) {
            var selectedStore = filtered.length === 1 ? filtered[0] : selectNearestStore(filtered, cityQuery, zipQuery);

            if (selectedStore !== null) {
                var selectedMarker = findMarkerByStoreId(matchedMarkers, selectedStore.id);
                if (selectedMarker !== null) {
                    var selectedLat = parseFloat(selectedStore.latitude);
                    var selectedLng = parseFloat(selectedStore.longitude);
                    var selectedZoom = Math.min(18, controller.defaultZoom + 2);
                    activateMarker(controller, selectedMarker, selectedStore, [selectedLat, selectedLng], selectedZoom);
                    return;
                }
            }

            showMapEmptyMessage(controller.mapNode, controller.labels.no_map_data || "");
            return;
        }

        var nearest = selectNearestStore(controller.stores, cityQuery, zipQuery);

        if (nearest !== null) {
            var nearestMarker = findMarkerByStoreId(controller.markers, nearest.id);
            if (nearestMarker !== null) {
                var nearestLat = parseFloat(nearest.latitude);
                var nearestLng = parseFloat(nearest.longitude);
                var nearestZoom = Math.min(18, controller.defaultZoom + 2);
                activateMarker(controller, nearestMarker, nearest, [nearestLat, nearestLng], nearestZoom);
                showMapEmptyMessage(
                    controller.mapNode,
                    (controller.labels.nearest_fallback_prefix || "No exact match. Nearest shown:") + " " + nearest.name
                );
                return;
            }
        }

        if (controller.activeMarkerObj) {
            controller.activeMarkerObj.marker.setIcon(controller.defaultIcon);
            controller.activeMarkerObj = null;
        }
        closeDetailsPanel(controller);
        showMapEmptyMessage(controller.mapNode, controller.labels.no_results || controller.labels.no_map_data || "");
    }

    function initLocator(root) {
        if (typeof window.L === "undefined") {
            return;
        }

        var dataNode = root.querySelector(".sl-store-locator__data");
        var mapNode = root.querySelector(".sl-store-locator__map");
        var detailsNode = root.querySelector(".sl-store-locator__details");
        var detailsContentNode = root.querySelector(".sl-store-locator__details-content");
        var detailsGpsLinkNode = root.querySelector(".sl-store-locator__details-gps");
        var group = root.getAttribute("data-sl-group") || "default";

        if (!dataNode || !mapNode || !detailsNode || !detailsContentNode || !detailsGpsLinkNode) {
            return;
        }

        var payload;

        try {
            payload = JSON.parse(dataNode.textContent || "{}");
        } catch (err) {
            payload = {};
        }

        var config = payload.config || {};
        var labels = payload.labels || {};
        var stores = Array.isArray(payload.stores) ? payload.stores : [];

        var centerLat = Number(config.centerLat || 0);
        var centerLng = Number(config.centerLng || 0);
        var defaultZoom = Number(config.defaultZoom || 13);

        var map = window.L.map(mapNode, {
            scrollWheelZoom: true
        }).setView([centerLat, centerLng], defaultZoom);

        window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: "&copy; OpenStreetMap contributors"
        }).addTo(map);

        var markerImage = config.markerImage || "";
        var markerActiveImage = config.markerActiveImage || "";
        var markerColor = config.markerColor || "#2e7d32";
        var bounds = [];
        var markerItems = [];
        var useClustering = false;
        var clusterGroup = null;

        var icon = createMarkerIcon(markerImage, markerColor);
        var activeIcon = markerActiveImage ? createMarkerIcon(markerActiveImage, markerColor) : icon;

        stores.forEach(function (store) {
            var lat = parseFloat(store.latitude);
            var lng = parseFloat(store.longitude);

            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }

            var marker = window.L.marker([lat, lng], { icon: icon });
            var markerRef = {
                store: store,
                marker: marker
            };

            marker.on("click", function () {
                var controller = locatorControllers[group];

                if (!controller) {
                    return;
                }

                if (
                    controller.activeMarkerObj &&
                    controller.activeMarkerObj.marker &&
                    controller.activeMarkerObj.marker === marker
                ) {
                    clearActiveMarker(controller);
                    restoreRememberedMapView(controller);
                    return;
                }

                rememberMapView(controller);
                var clickZoom = Math.min(18, defaultZoom + 2);
                activateMarker(controller, markerRef, store, [lat, lng], clickZoom);
            });
            if (useClustering && clusterGroup) {
                clusterGroup.addLayer(marker);
            } else {
                marker.addTo(map);
            }
            bounds.push([lat, lng]);
            markerItems.push(markerRef);
        });

        if (useClustering && clusterGroup) {
            map.addLayer(clusterGroup);
        }

        if (bounds.length === 0) {
            showMapEmptyMessage(mapNode, labels.no_map_data || "");
        }

        locatorControllers[group] = {
            group: group,
            root: root,
            map: map,
            mapNode: mapNode,
            detailsNode: detailsNode,
            detailsContentNode: detailsContentNode,
            detailsGpsLinkNode: detailsGpsLinkNode,
            stores: stores,
            markers: markerItems,
            useClustering: useClustering,
            clusterGroup: clusterGroup,
            defaultIcon: icon,
            activeIcon: activeIcon,
            activeMarkerObj: null,
            labels: labels,
            defaultZoom: defaultZoom,
            defaultCenter: [centerLat, centerLng],
            allBounds: bounds,
            preActivationView: null
        };
    }

    function initSearch(root) {
        var group = root.getAttribute("data-sl-group") || "default";
        var queryInput = root.querySelector(".sl-filter-query");
        var cityInput = root.querySelector(".sl-filter-city");
        var zipInput = root.querySelector(".sl-filter-zip");
        var submit = root.querySelector(".sl-filter-submit");

        if (!submit) {
            return;
        }

        var run = function () {
            var controller = locatorControllers[group];

            if (!controller) {
                return;
            }

            var query = "";

            if (queryInput) {
                query = String(queryInput.value || "").trim().toLowerCase();
            } else {
                var cityQuery = cityInput ? String(cityInput.value || "").trim().toLowerCase() : "";
                var zipQuery = zipInput ? String(zipInput.value || "").trim().toLowerCase() : "";
                query = (cityQuery + " " + zipQuery).trim();
            }

            rememberMapView(controller);
            applyFilter(controller, query);
        };

        submit.addEventListener("click", run);
        if (queryInput) {
            queryInput.addEventListener("keydown", function (event) {
                if (event.key === "Enter") {
                    run();
                }
            });
        }
        if (cityInput) {
            cityInput.addEventListener("keydown", function (event) {
                if (event.key === "Enter") {
                    run();
                }
            });
        }
        if (zipInput) {
            zipInput.addEventListener("keydown", function (event) {
                if (event.key === "Enter") {
                    run();
                }
            });
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        var mapRoots = document.querySelectorAll(".sl-store-locator--map");
        mapRoots.forEach(initLocator);

        var searchRoots = document.querySelectorAll(".sl-store-locator--search");
        searchRoots.forEach(initSearch);
    });
})();
