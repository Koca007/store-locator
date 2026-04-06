(function ($) {
    "use strict";

    function updateLiveMarkerPreview() {
        var markerColor = $(".sl-color-field").val() || "#2e7d32";
        var imageUrl = $(".sl-marker-image-url").val() || "";
        var activeImageUrl = $(".sl-marker-active-image-url").val() || "";

        var $img = $(".sl-live-marker-preview-image");
        var $pin = $(".sl-live-marker-preview-pin");
        var $pinInner = $(".sl-live-marker-preview-pin-inner");
        var $activeImg = $(".sl-live-marker-active-preview-image");
        var $activeFallback = $(".sl-live-marker-active-fallback");

        if (imageUrl) {
            $img.attr("src", imageUrl).show();
            $pin.hide();
            $pinInner.hide();
        } else {
            $img.attr("src", "").hide();
            $pin.css("background", markerColor).show();
            $pinInner.show();
        }

        if (activeImageUrl) {
            $activeImg.attr("src", activeImageUrl).show();
            $activeFallback.hide();
        } else {
            $activeImg.attr("src", "").hide();
            $activeFallback.css("background", markerColor).show();
        }
    }

    function initColorPicker() {
        $(".sl-color-field").wpColorPicker({
            change: function () {
                updateLiveMarkerPreview();
            },
            clear: function () {
                updateLiveMarkerPreview();
            }
        });

        $(document).on("input change", ".sl-color-field", function () {
            updateLiveMarkerPreview();
        });
    }

    function initMediaPicker() {
        var frame;
        var svgFrame;
        var activeFrame;
        var activeSvgFrame;

        $(document).on("click", ".sl-marker-image-select", function (event) {
            event.preventDefault();

            var $container = $(this).closest("td");
            var $input = $container.find(".sl-marker-image-url");
            var $preview = $container.find(".sl-marker-image-preview");

            if (frame) {
                frame.off("select");
            }

            frame = wp.media({
                title: (window.slAdminSettings && window.slAdminSettings.title) ? window.slAdminSettings.title : "Select Image",
                button: {
                    text: (window.slAdminSettings && window.slAdminSettings.buttonText) ? window.slAdminSettings.buttonText : "Use this image"
                },
                multiple: false
            });

            frame.on("select", function () {
                var attachment = frame.state().get("selection").first().toJSON();
                $input.val(attachment.url);
                $preview.attr("src", attachment.url).show();
                updateLiveMarkerPreview();
            });

            frame.open();
        });

        $(document).on("click", ".sl-marker-svg-select", function (event) {
            event.preventDefault();

            var $container = $(this).closest("td");
            var $input = $container.find(".sl-marker-image-url");
            var $preview = $container.find(".sl-marker-image-preview");

            if (svgFrame) {
                svgFrame.off("select");
            }

            svgFrame = wp.media({
                title: (window.slAdminSettings && window.slAdminSettings.svgTitle) ? window.slAdminSettings.svgTitle : "Select SVG File",
                button: {
                    text: (window.slAdminSettings && window.slAdminSettings.svgButtonText) ? window.slAdminSettings.svgButtonText : "Use this SVG"
                },
                multiple: false
            });

            svgFrame.on("select", function () {
                var attachment = svgFrame.state().get("selection").first().toJSON();
                var url = attachment.url || "";
                var isSvgByMime = attachment.mime && attachment.mime.toLowerCase() === "image/svg+xml";
                var isSvgByExt = /\.svg(\?.*)?$/i.test(url);

                if (!isSvgByMime && !isSvgByExt) {
                    var msg = (window.slAdminSettings && window.slAdminSettings.svgOnlyError) ? window.slAdminSettings.svgOnlyError : "Only SVG files are allowed.";
                    window.alert(msg);
                    return;
                }

                $input.val(url);
                $preview.attr("src", url).show();
                updateLiveMarkerPreview();
            });

            svgFrame.open();
        });

        $(document).on("click", ".sl-marker-image-remove", function (event) {
            event.preventDefault();

            var $container = $(this).closest("td");
            $container.find(".sl-marker-image-url").val("");
            $container.find(".sl-marker-image-preview").attr("src", "").hide();
            updateLiveMarkerPreview();
        });

        $(document).on("input change", ".sl-marker-image-url", function () {
            updateLiveMarkerPreview();
        });

        $(document).on("click", ".sl-marker-active-image-select", function (event) {
            event.preventDefault();

            var $container = $(this).closest("td");
            var $input = $container.find(".sl-marker-active-image-url");
            var $preview = $container.find(".sl-marker-active-image-preview");

            if (activeFrame) {
                activeFrame.off("select");
            }

            activeFrame = wp.media({
                title: (window.slAdminSettings && window.slAdminSettings.title) ? window.slAdminSettings.title : "Select Image",
                button: {
                    text: (window.slAdminSettings && window.slAdminSettings.buttonText) ? window.slAdminSettings.buttonText : "Use this image"
                },
                multiple: false
            });

            activeFrame.on("select", function () {
                var attachment = activeFrame.state().get("selection").first().toJSON();
                $input.val(attachment.url);
                $preview.attr("src", attachment.url).show();
                updateLiveMarkerPreview();
            });

            activeFrame.open();
        });

        $(document).on("click", ".sl-marker-active-svg-select", function (event) {
            event.preventDefault();

            var $container = $(this).closest("td");
            var $input = $container.find(".sl-marker-active-image-url");
            var $preview = $container.find(".sl-marker-active-image-preview");

            if (activeSvgFrame) {
                activeSvgFrame.off("select");
            }

            activeSvgFrame = wp.media({
                title: (window.slAdminSettings && window.slAdminSettings.svgTitle) ? window.slAdminSettings.svgTitle : "Select SVG File",
                button: {
                    text: (window.slAdminSettings && window.slAdminSettings.svgButtonText) ? window.slAdminSettings.svgButtonText : "Use this SVG"
                },
                multiple: false
            });

            activeSvgFrame.on("select", function () {
                var attachment = activeSvgFrame.state().get("selection").first().toJSON();
                var url = attachment.url || "";
                var isSvgByMime = attachment.mime && attachment.mime.toLowerCase() === "image/svg+xml";
                var isSvgByExt = /\.svg(\?.*)?$/i.test(url);

                if (!isSvgByMime && !isSvgByExt) {
                    var msg = (window.slAdminSettings && window.slAdminSettings.svgOnlyError) ? window.slAdminSettings.svgOnlyError : "Only SVG files are allowed.";
                    window.alert(msg);
                    return;
                }

                $input.val(url);
                $preview.attr("src", url).show();
                updateLiveMarkerPreview();
            });

            activeSvgFrame.open();
        });

        $(document).on("click", ".sl-marker-active-image-remove", function (event) {
            event.preventDefault();

            var $container = $(this).closest("td");
            $container.find(".sl-marker-active-image-url").val("");
            $container.find(".sl-marker-active-image-preview").attr("src", "").hide();
            updateLiveMarkerPreview();
        });

        $(document).on("input change", ".sl-marker-active-image-url", function () {
            updateLiveMarkerPreview();
        });
    }

    function initTabs() {
        var $tabs = $(".sl-settings-tabs .nav-tab");
        var $panels = $(".sl-settings-panel");
        var storageKey = "sl_settings_active_tab";

        if ($tabs.length === 0 || $panels.length === 0) {
            return;
        }

        function activateTab(tabKey) {
            $tabs.removeClass("nav-tab-active");
            $tabs.filter("[data-sl-tab='" + tabKey + "']").addClass("nav-tab-active");

            $panels.removeClass("is-active");
            $panels.filter("[data-sl-tab-panel='" + tabKey + "']").addClass("is-active");
            try {
                window.localStorage.setItem(storageKey, String(tabKey));
            } catch (err) {}
        }

        var hash = window.location.hash ? window.location.hash.replace("#", "") : "";
        var storedTab = "";
        try {
            storedTab = window.localStorage.getItem(storageKey) || "";
        } catch (err) {
            storedTab = "";
        }

        if (hash && $tabs.filter("[data-sl-tab='" + hash + "']").length > 0) {
            activateTab(hash);
        } else if (storedTab && $tabs.filter("[data-sl-tab='" + storedTab + "']").length > 0) {
            activateTab(storedTab);
        } else {
            activateTab($tabs.first().data("sl-tab"));
        }

        $tabs.on("click", function (event) {
            event.preventDefault();
            var tabKey = $(this).data("sl-tab");
            activateTab(tabKey);
            window.location.hash = String(tabKey);
        });
    }

    function initTypographyPresetToggles() {
        var dependencies = {
            search_button_typography_preset: [
                "search_button_font_size",
                "search_button_font_weight",
                "search_button_letter_spacing",
                "search_button_text_transform"
            ],
            search_input_typography_preset: [
                "search_input_font_size",
                "search_input_font_weight"
            ],
            info_title_typography_preset: [
                "info_title_font_family",
                "info_title_font_size",
                "info_title_font_weight"
            ],
            info_address_typography_preset: [
                "info_address_font_family",
                "info_address_font_size",
                "info_address_font_weight"
            ],
            info_phone_typography_preset: [
                "info_phone_font_family",
                "info_phone_font_size",
                "info_phone_font_weight"
            ],
            info_email_typography_preset: [
                "info_email_font_family",
                "info_email_font_size",
                "info_email_font_weight"
            ],
            info_hours_typography_preset: [
                "info_hours_font_family",
                "info_hours_font_size",
                "info_hours_font_weight"
            ]
        };

        function setNote($select, selectedValue) {
            var key = $select.data("setting-key");
            var $note = $(".sl-typography-preset-note[data-setting-key='" + key + "']");
            var options = window.slAdminSettings && window.slAdminSettings.typographyOptions ? window.slAdminSettings.typographyOptions : {};
            var prefix = window.slAdminSettings && window.slAdminSettings.typographySelectedPrefix ? window.slAdminSettings.typographySelectedPrefix : "Selected typography:";
            var label = options[selectedValue] ? options[selectedValue] : selectedValue;

            $note.text(prefix + " " + label);
        }

        function findRowBySettingKey(fieldKey) {
            var fieldName = "sl_settings[" + fieldKey + "]";
            var $field = $("[name='" + fieldName + "']");

            if ($field.length === 0) {
                return $();
            }

            return $field.first().closest("tr");
        }

        function toggleFields(settingKey, selectedValue) {
            var ids = dependencies[settingKey] || [];
            var showCustom = selectedValue === "custom";

            ids.forEach(function (fieldId) {
                var $row = findRowBySettingKey(fieldId);
                if (!$row.length) {
                    return;
                }

                if (showCustom) {
                    $row.show();
                    return;
                }

                $row.hide();
            });
        }

        $(".sl-typography-preset-select").each(function () {
            var $select = $(this);
            var settingKey = String($select.data("setting-key") || "");
            var selectedValue = String($select.val() || "custom");

            toggleFields(settingKey, selectedValue);
            setNote($select, selectedValue);
        });

        $(document).on("change", ".sl-typography-preset-select", function () {
            var $select = $(this);
            var settingKey = String($select.data("setting-key") || "");
            var selectedValue = String($select.val() || "custom");

            toggleFields(settingKey, selectedValue);
            setNote($select, selectedValue);
        });
    }

    $(function () {
        initTabs();
        initTypographyPresetToggles();
        initColorPicker();
        initMediaPicker();
        updateLiveMarkerPreview();
    });
})(jQuery);
